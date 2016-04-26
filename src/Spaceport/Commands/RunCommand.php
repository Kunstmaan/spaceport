<?php
namespace Spaceport\Commands;

use Spaceport\Exceptions\NotAJenkinsBuiltProject;
use Spaceport\Exceptions\NotASymfonyProjectException;
use Spaceport\Model\Shuttle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Parser;

class RunCommand extends AbstractCommand
{
    /**
     * @var Shuttle
     */
    private $shuttle;

    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Run the development environment');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $this->shuttle = new Shuttle();
        $this->writeDockerComposeFile();
        $this->checkAppFile();
        $this->checkAppKernelFile();
        $this->writeConfigDockerFile();
        $this->generateCertificate();
        $this->fetchDatabase();
        $this->runDocker();
        $this->io->newLine();
    }

    private function generateCertificate(){
            $this->logStep('Generate selfsigned certificate');
            $this->runCommand('openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout ~/.dinghy/certs/'.basename(getcwd()).'.docker.key -out ~/.dinghy/certs/'.basename(getcwd()).'.docker.crt -subj "/C=BE/ST=Vlaams-Brabant/L=Leuven/O=Kunstmaan/OU=Smarties/CN='.basename(getcwd()).'.docker"');
    }

    private function fetchDatabase(){
        if (!$this->shuttle->hasServer()) {
            $this->logStep('Not Fetching databases because there is no .skylab/jenkins.yml present');
            return;
        }
        
        if ($this->shuttle->shouldRunSync()) {
            $this->logStep('Fetching the production databases');
            $this->runCommand('rsync --no-acls -rLDhz --delete --size-only ' . $this->shuttle->getServer() . ':/home/projects/' . $this->shuttle->getName() . '/backup/mysql.dmp.gz .skylab/backup/', 60);
            $this->runCommand('mv .skylab/backup/mysql.dmp.gz .skylab/backup/mysql.sql.gz', 60);
        }
    }

    private function runDocker(){
        $this->logStep('Starting Docker (this can take a while ...)');
        $this->runCommand('docker-compose down');
        $this->runCommand('docker-compose pull');
        $this->runCommand('docker-compose up -d');
    }

    private function writeConfigDockerFile()
    {
        $this->logStep('Generating the app/config/config_docker.yml file');
        $configDockerFileName = 'app/config/config_docker.yml';
        $this->twig->renderAndWriteTemplate('symfony/config_docker.yml.twig', $configDockerFileName);
    }

    private function checkAppFile()
    {
        $this->logStep('Checking if the web/app.php file is setup for Docker');
        if (strpos(file_get_contents("web/app.php"), 'docker') === false) {
            $this->io->block(
                'The web/app.php file is not setup for Docker. Change the section where the AppKernel is loaded to look like this:

if (getenv(\'APP_ENV\') === \'dev\' || getenv(\'APP_ENV\') === \'docker\') {
    umask(0000);
    Debug::enable();
    $kernel = new AppKernel(getenv(\'APP_ENV\'), true);
} else {
   $kernel = new AppKernel(\'prod\', false);
}'
            );
        }
    }

    private function checkAppKernelFile()
    {
        $this->logStep('Checking if the app/AppKernel.php file is setup for Docker');
        if (strpos(file_get_contents("app/AppKernel.php"), 'docker') === false) {
            $this->io->block(
                'The app/AppKernel.php file is not setup for Docker. Change the section where the dev and test bundles are loaded to look like this:

if (in_array($this->getEnvironment(), array(\'dev\', \'test\', \'docker\'), true)) {'
            );
        }
    }

    /**
     * @return array
     */
    private function writeDockerComposeFile()
    {
        $dockerComposeFileName = 'docker-compose.yml';
        $this->findMySQLSettings();
        $this->findApacheSettings();
        $this->findPHPSettings(!file_exists($dockerComposeFileName));
        if (!file_exists($dockerComposeFileName)){
            $this->logStep('Generating the docker-compose.yml file');
            $this->twig->renderAndWriteTemplate('symfony/' . $dockerComposeFileName . '.twig', $dockerComposeFileName, array('shuttle' => $this->shuttle));
        }
    }

    private function findPHPSettings($ask=true)
    {
        $php = array();
        if ($ask){
            $this->shuttle->setPhpVersion($this->io->choice('What version of PHP do you need?', array('7.0','5.6','5.5','5.4')));
        }
        return $php;
    }


    /**
     * @return array
     * @throws NotAJenkinsBuiltProject
     */
    private function findApacheSettings()
    {
        $jenkinsFile = '.skylab/jenkins.yml';
        if (file_exists($jenkinsFile)) {
            $yaml = new Parser();
            $value = $yaml->parse(file_get_contents($jenkinsFile));
            $this->shuttle->setName($value["deploy_matrix"][$value["database_source"]]["project"]);
            $this->shuttle->setServer($value["deploy_matrix"][$value["database_source"]]["server"]);
            $this->shuttle->setApacheVhost($this->shuttle->getName() . Shuttle::DOCKER_EXT);
            $this->shuttle->setApacheFallbackDomain($this->shuttle->getName() . '.' . $this->shuttle->getServer());
            $this->shuttle->setRunSync($this->io->confirm('Should I fetch the database from your server?'));
        }
    }

    /**
     * @return array
     * @throws NotASymfonyProjectException
     */
    private function findMySQLSettings()
    {
        $parametersFile = 'app/config/parameters.yml';
        $oldParametersFile = 'app/config/parameters.ini';
        if (file_exists($parametersFile)) {
            $yaml = new Parser();
            $parameters = $yaml->parse(file_get_contents($parametersFile));
        } elseif (file_exists($oldParametersFile)) {
            $parameters = parse_ini_file($oldParametersFile, true);
        } else {
            throw new NotASymfonyProjectException("No parameters.yml or parameters.ini file found at " . $parametersFile);
        }

        $this->shuttle->setMysqlDatabase($parameters["parameters"]["database_name"]);
        $this->shuttle->setMysqlUser($parameters["parameters"]["database_user"]);
        $this->shuttle->setMysqlPassword($parameters["parameters"]["database_password"]);
    }

    private function runCommand($command, $timeout=null, $env=array())
    {
        $this->logCommand($command);
        $env = array_replace($_ENV, $_SERVER, $env);
        $process = new Process($command, null, $env);
        $process->setTimeout($timeout);
        $process->run(function ($type, $buffer) {
            if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_VERBOSE) {
                strlen($type); // just to get rid of the scrutinizer error... sigh
                echo $buffer;
            }
        });
    }

}
