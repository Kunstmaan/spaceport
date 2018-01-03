<?php

namespace Spaceport\Commands;

use Spaceport\Exceptions\NotASymfonyProjectException;
use Spaceport\Model\DatabaseConnection;
use Spaceport\Model\Shuttle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Parser;

class InitCommand extends AbstractCommand
{

    /**
     * @var Shuttle
     */
    private $shuttle;

    protected function configure()
    {
        $this
            ->setName('init')
            ->setDescription('Initialize the project to run with docker');
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
        $this->setDinghySSLCerts();
        $this->fetchDatabase();
        $this->logSuccess("You can now run `spaceport start` to run the development environment");
        $this->io->newLine();
    }

    private function fetchDatabase()
    {
        //Always create the .spaceport/mysql dir
        $this->runCommand(sprintf('mkdir -p ~/.spaceport/mysql/%s', $this->shuttle->getName()));
        if (!$this->shuttle->hasServer()) {
            $this->logStep('Not Fetching databases because there is no server configured');

            return;
        }

        if ($this->shuttle->shouldRunSync()) {
            $this->logStep('Fetching the production databases');
            $this->runCommand(
                sprintf(
            'rsync --no-acls -rLDhz --delete --size-only %s:/home/projects/%s/backup/mysql.dmp.gz ~/.spaceport/mysql/%s',
                    $this->shuttle->getServer(),
                    $this->shuttle->getName(),
                    $this->shuttle->getName()
                ), 60);
            $this->runCommand(
                sprintf(
            'mv ~/.spaceport/mysql/%s/mysql.dmp.gz ~/.spaceport/mysql/%s/mysql.sql.gz',
                    $this->shuttle->getName(),
                    $this->shuttle->getName()
                ), 60);
        }
    }

    private function writeConfigDockerFile()
    {
        $this->logStep('Generating the app/config/config_docker.yml file');
        $configDockerFileName = 'app/config/config_docker.yml';
        $this->twig->renderAndWriteTemplate('symfony/config_docker.yml.twig', $configDockerFileName, ['shuttle' => $this->shuttle]);
    }

    private function checkAppFile()
    {
        $this->logStep('Checking if the web/app.php file is setup for Docker');
        if (strpos(file_get_contents("web/app.php"), 'docker') === false) {
            $this->logWarning('The web/app.php file is not setup for Docker. Change the section where the AppKernel is loaded to look like this:

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
            $this->logWarning('The app/AppKernel.php file is not setup for Docker. Change the section where the dev and test bundles are loaded to look like this:

if (in_array($this->getEnvironment(), array(\'dev\', \'test\', \'docker\'), true)) {'
            );
        }
    }

    /**
     * @return array
     */
    private function writeDockerComposeFile()
    {
        $this->findMySQLSettings();
        $this->findApacheSettings();
        $this->findPHPSettings();
        $this->askElasticVersion();
        $this->askNodeVersion();
        $this->logStep('Generating the docker-compose file');
        $this->twig->renderAndWriteTemplate('symfony/' . parent::DOCKER_COMPOSE_LINUX_FILE_NAME . '.twig', parent::DOCKER_COMPOSE_LINUX_FILE_NAME, ['shuttle' => $this->shuttle]);
        $this->twig->renderAndWriteTemplate('symfony/' . parent::DOCKER_COMPOSE_MAC_FILE_NAME . '.twig', parent::DOCKER_COMPOSE_MAC_FILE_NAME, ['shuttle' => $this->shuttle]);
        $this->twig->renderAndWriteTemplate('symfony/' . parent::DOCKER_COMPOSE_SYNC_FILE_NAME . '.twig', parent::DOCKER_COMPOSE_SYNC_FILE_NAME, ['shuttle' => $this->shuttle]);
    }

    private function findPHPSettings($ask = true)
    {
        $php = [];
        if ($ask) {
            $this->shuttle->setPhpVersion($this->io->choice('What version of PHP do you need?', ['7.2', '7.1', '7.0', '5.6'], '7.2'));
        }

        return $php;
    }

    private function askElasticVersion($ask = true)
    {
        if ($ask) {
            $this->shuttle->setElasticsearchVersion($this->io->choice('What version of Elasticsearch do you need?', ['5', '2'], '5'));
        }
    }

    private function askNodeVersion($ask = true)
    {
        if ($ask) {
            $this->shuttle->setNodeVersion($this->io->choice('What version of Node do you need?', ['8', '7', '6.9'], '8'));
        }
    }

    private function findApacheSettings()
    {
        $jenkinsFile = '.skylab/jenkins.yml';
        if (file_exists($jenkinsFile)) {
            $yaml = new Parser();
            $value = $yaml->parse(file_get_contents($jenkinsFile));
            $this->shuttle->setName($value["deploy_matrix"][$value["database_source"]]["project"]);
            $this->shuttle->setServer($value["deploy_matrix"][$value["database_source"]]["server"]);
            $this->shuttle->setApacheFallbackDomain($this->shuttle->getName() . '.' . $this->shuttle->getServer());
            $this->shuttle->setRunSync($this->io->confirm('Should I fetch the database from your server?'));
        } else {
            $question = new Question('What is the name of the project?', $this->shuttle->getName());
            $this->shuttle->setName($this->io->askQuestion($question));
            if ($this->io->confirm('Is your project online available?', false)) {
                $question = new Question('What is the domain name of your project?');
                $this->shuttle->setServer($this->io->askQuestion($question));
                $this->shuttle->setApacheFallbackDomain($this->shuttle->getServer());
                $this->shuttle->setRunSync($this->io->confirm('Should I fetch the database from your server?'));
            }
        }
        $question = new Question('What is the Apache DocumentRoot?', $this->shuttle->getApacheDocumentRoot());
        $this->shuttle->setApacheDocumentRoot($this->io->askQuestion($question));
        $this->shuttle->setApacheVhost($this->shuttle->getName() . Shuttle::DOCKER_EXT);
    }

    /**
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

        if (
            !array_key_exists('database_name', $parameters['parameters']) &&
            !array_key_exists('database_user', $parameters['parameters']) &&
            !array_key_exists('database_password', $parameters['parameters'])) {
            $question = new Question('How many databases do you want to configure?', 1);
            $question->setValidator(function ($answer) {
                if (!is_numeric($answer)) {
                    throw new \RuntimeException(
                        'The answer should be a number.'
                    );
                }

                return $answer;
            });
            $loops = $this->io->askQuestion($question);

            for ($i = 0; $i < $loops; $i++) {
                $this->io->writeln(sprintf('Configuring database %d:', $i+1));
                $databaseConnection = new DatabaseConnection();
                $question = new Question('What is the database name?');
                $databaseConnection->setMysqlDatabase($this->io->askQuestion($question));
                $question = new Question('What is the database user?');
                $databaseConnection->setMysqlUser($this->io->askQuestion($question));
                $question = new Question('What is the database password?');
                $databaseConnection->setMysqlPassword($this->io->askQuestion($question));

                $this->shuttle->addDatabaseConnection($databaseConnection);
            }
        } else {
            $databaseConnection = new DatabaseConnection();
            $databaseConnection->setMysqlDatabase($parameters['parameters']['database_name']);
            $databaseConnection->setMysqlUser($parameters['parameters']['database_user']);
            $databaseConnection->setMysqlPassword($parameters['parameters']['database_password']);
            $this->shuttle->addDatabaseConnection($databaseConnection);
        }
    }

    private function setDinghySSLCerts()
    {
        if ($this->io->confirm('Do you want to enable SSL for your Apache vhost ?', true)) {
            //Check if dinghy dir exists
            if (!file_exists("~/.dinghy/certs/")) {
                $this->runCommand("mkdir -p ~/.dinghy/certs/");
            }
            $sslFilesLocation = $this->getSSLFileLocation();
            if ($sslFilesLocation) {
                //$command = 'sudo -s -p "Please enter your sudo password:" ' . $command;
                $this->runCommand("sudo -s -p \"Please enter your sudo password:\" cp " . $sslFilesLocation . "*.crt ~/.dinghy/certs/" . $this->shuttle->getApacheVhost() . ".crt");
                $this->runCommand("sudo -s -p \"Please enter your sudo password:\" cp " . $sslFilesLocation . "*.key ~/.dinghy/certs/" . $this->shuttle->getApacheVhost() . ".key");
            }
        }
    }

    private function getSSLFileLocation()
    {
        $question = new Question('What is the location of the SSL cert file and key file ? (Note: the dir should only contain 1 crt and 1 key file)', '/etc/ssl/docker_certs/');
        $sslFilesLocation = $this->io->askQuestion($question);
        if (!substr($sslFilesLocation, -1) == '/') {
            $sslFilesLocation = $sslFilesLocation . '/';
        }
        if (!file_exists($sslFilesLocation)) {
            $this->logError("The location " . $sslFilesLocation . " does not exist");

            return false;
        }
        if (count(glob($sslFilesLocation . "*.crt")) == 0) {
            $this->logError("There is no crt file available in the location " . $sslFilesLocation . ".");

            return false;
        }
        if (count(glob($sslFilesLocation . "*.crt")) > 1) {
            $this->logError("There are multiple crt files available in the location " . $sslFilesLocation . ". There should only be one crt file.");

            return false;
        }
        if (count(glob($sslFilesLocation . "*.key")) == 0) {
            $this->logError("There is no key file available in the location " . $sslFilesLocation . ".");

            return false;
        }
        if (count(glob($sslFilesLocation . "*.key")) > 1) {
            $this->logError("There are multiple key files available in the location " . $sslFilesLocation . ". There should only be one crt file.");

            return false;
        }

        return $sslFilesLocation;
    }
}