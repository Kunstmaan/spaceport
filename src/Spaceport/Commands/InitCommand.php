<?php

namespace Spaceport\Commands;

use Spaceport\Exceptions\NotASymfonyProjectException;
use Spaceport\Model\DatabaseConnection;
use Spaceport\Model\Shuttle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Parser;

class InitCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('init')
            ->setDescription('Initialize the project to run with docker')
            ->addOption('force', null, InputOption::VALUE_OPTIONAL, 'Force the regeneration of the docker files');
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
        if (!$this->isDockerized(true) || $input->getOption('force')) {
            $this->writeDockerComposeFile();
            $this->writeConfigDockerFile();
        }
        $this->checkAppFile();
        $this->checkAppKernelFile();
        $this->setDinghySSLCerts();
        $this->fetchDatabase($output);
        $this->logSuccess("You can now run `spaceport start` to run the development environment");
        $this->io->newLine();
    }

    private function fetchDatabase(OutputInterface $output)
    {
        if ($this->io->confirm('Should I fetch the database from your server?', false)) {

            $command = $this->getApplication()->find("db");

            $arguments = ['command' => 'db', '--fetch' => true];

            $dbInput = new ArrayInput($arguments);
            $command->run($dbInput, $output);
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
        if (file_exists("web/app.php") && strpos(file_get_contents("web/app.php"), 'docker') === false) {
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
        if (file_exists("app/AppKernel.php") && strpos(file_get_contents("app/AppKernel.php"), 'docker') === false) {
            $this->logWarning('The app/AppKernel.php file is not setup for Docker. Change the section where the dev and test bundles are loaded to look like this:

if (in_array($this->getEnvironment(), array(\'dev\', \'test\', \'docker\'), true)) {'
            );
        }
    }

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
            $this->shuttle->setElasticsearchVersion($this->io->choice('What version of Elasticsearch do you need?', ['6', '5', '2'], '5'));
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
        $question = new Question('What is the Apache DocumentRoot?', $this->shuttle->getApacheDocumentRoot());
        $this->shuttle->setApacheDocumentRoot($this->io->askQuestion($question));
        $question = new Question('What server should be used as the fallback domain ? (Can be left empty)', '/');
        $fallbackDomain = $this->io->askQuestion($question);
        $fallbackDomain = preg_replace('#^https?://#', '', rtrim($fallbackDomain,'/'));
        $this->shuttle->setApacheFallbackDomain($fallbackDomain);
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
            $this->io->writeln("No parameters.yml or parameters.ini file found at " . $parametersFile);
            return;
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
}
