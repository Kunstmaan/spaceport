<?php
namespace Spaceport\Commands;

use Spaceport\Exceptions\NotAJenkinsBuiltProject;
use Spaceport\Exceptions\NotASymfonyProjectException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Parser;

class RunCommand extends AbstractCommand
{

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
        $variables = $this->writeDockerComposeFile();
        $this->checkAppFile();
        $this->checkAppKernelFile();
        $this->writeConfigDockerFile();
        $this->generateCertificate();
        $this->fetchDatabase($variables);
        $this->runDocker();
        $this->io->newLine();
    }

    private function generateCertificate(){
            $this->logStep('Generate selfsigned certificate');
            $this->runCommand('openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout ~/.dinghy/certs/'.basename(getcwd()).'.docker.key -out ~/.dinghy/certs/'.basename(getcwd()).'.docker.crt -subj "/C=BE/ST=Vlaams-Brabant/L=Leuven/O=Kunstmaan/OU=Smarties/CN='.basename(getcwd()).'.docker"');
    }

    /**
     * @param array $variables
     */
    private function fetchDatabase(array $variables){
        $this->logStep('Fetching the production databases');
        $this->runCommand('rsync --no-acls -rLDhz --delete --size-only ' . $variables['production_server'] . ':/home/projects/' . $variables['project_name'] . '/backup/mysql.dmp.gz .skylab/backup/', 60);
        $this->runCommand('mv .skylab/backup/mysql.dmp.gz .skylab/backup/mysql.sql.gz', 60);
    }

    private function runDocker(){
        $this->logStep('Starting Docker');
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
        $variables = array_merge($this->findMySQLSettings(), $this->findApacheSettings(), $this->findPHPSettings(!file_exists($dockerComposeFileName)));
        if (!file_exists($dockerComposeFileName)){
            $this->logStep('Generating the docker-compose.yml file');
            $this->twig->renderAndWriteTemplate('symfony/' . $dockerComposeFileName . '.twig', $dockerComposeFileName, $variables);
        }
        return $variables;
    }

    private function findPHPSettings($ask=true)
    {

        $php = array();
        if ($ask){
            $php['php_version'] = $this->io->choice('What version of PHP do you need?', array('7.0','5.6','5.5','5.4'));;
        }
        return $php;
    }


    /**
     * @return array
     * @throws NotAJenkinsBuiltProject
     */
    private function findApacheSettings()
    {
        $apache = array();
        $apache['apache_vhost'] = basename(getcwd()) . '.docker';
        $apache['apache_webroot'] = "web/";

        $jenkinsFile = '.skylab/jenkins.yml';
        if (file_exists($jenkinsFile)) {
            $yaml = new Parser();
            $value = $yaml->parse(file_get_contents($jenkinsFile));
            $apache['apache_fallbackdomain'] = basename(getcwd()) . '.' . $value["deploy_matrix"][$value["database_source"]]["server"];
            $apache['production_server'] = $value["deploy_matrix"][$value["database_source"]]["server"];
            $apache['project_name'] = $value["deploy_matrix"][$value["database_source"]]["project"];
        } else {
            throw new NotAJenkinsBuiltProject("No jenkins.yml file found at " . $jenkinsFile);
        }
        return $apache;
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
            $value = $yaml->parse(file_get_contents($parametersFile));
            $mysql = array();
            $mysql['mysql_database'] = $value["parameters"]["database_name"];
            $mysql['mysql_user'] = $value["parameters"]["database_user"];
            $mysql['mysql_password'] = $value["parameters"]["database_password"];
            return $mysql;
        } elseif (file_exists($oldParametersFile)) {
            $ini = parse_ini_file($oldParametersFile, true);
            $mysql = array();
            $mysql['mysql_database'] = $ini["parameters"]["database_name"];
            $mysql['mysql_user'] = $ini["parameters"]["database_user"];
            $mysql['mysql_password'] = $ini["parameters"]["database_password"];
            return $mysql;
        } else {
            throw new NotASymfonyProjectException("No parameters.yml or parameters.ini file found at " . $parametersFile);
        }
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
