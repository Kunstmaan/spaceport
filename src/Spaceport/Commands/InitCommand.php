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
        $this->logSuccess("You can now run `spaceport start` to run the development environment");
        $this->io->newLine();
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
            if ($this->io->confirm('The web/app.php file is not setup for Docker. Should I modify it for you? You need to verify after..', false)) {
                $this->writeAppPhp();
            }
        }
    }

    private function checkAppKernelFile()
    {
        $this->logStep('Checking if the app/AppKernel.php file is setup for Docker');

        require getcwd().'/app/autoload.php';
        include_once getcwd().'/var/bootstrap.php.cache';

        if (file_exists("app/AppKernel.php") && strpos(file_get_contents("app/AppKernel.php"), 'docker') === false) {
            if ($this->io->confirm('The app/AppKernel.php file is not setup for Docker. Should I modify it for you? You need to verify after..', false)) {
                $file = \file("app/AppKernel.php");
                $file = $this->writeLogDir($file);
                $file = $this->writeCacheDir($file);
                $contents = implode("", $file);
                $contents = str_replace('in_array($this->getEnvironment(), array(\'dev\'', 'in_array($this->getEnvironment(), array(\'dev\',\'docker\'', $contents);

                file_put_contents("app/AppKernel.php", $contents);
            }
        }
    }

    private function writeAppPhp($filename = 'web/app.php')
    {
        $contents = file_get_contents($filename);

        $this->logStep('Modifying '.$filename);
        $contents = str_replace('if (getenv(\'APP_ENV\') === \'dev\'', 'if (getenv(\'APP_ENV\') === \'dev\' || getenv(\'APP_ENV\') === \'docker\'', $contents);
        $contents = str_replace('if (getenv(\'APP_ENV\') !== \'dev\'', 'if (getenv(\'APP_ENV\') !== \'dev\' || getenv(\'APP_ENV\') !== \'docker\'', $contents);
        file_put_contents($filename, $contents);
    }

    private function writeLogDir(array $file)
    {
        $method = new \ReflectionMethod('AppKernel', 'getLogDir');
        $slice = array_slice($file, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);
        $contents = implode('', $slice);

        if (strpos($contents, 'docker') === false) {
            $this->logStep('Modifying the logDir function in AppKernel');
            $tmp = ["        if (\$this->getEnvironment() === 'docker') {\n          return '/tmp/symfony/var/logs/';\n        }\n\n"];
            foreach ($slice as $key => $line) {
                if (strpos($line, '{') !== false) {
                    $slice = array_merge(array_slice($slice, 0, $key + 1), $tmp, array_slice($slice, $key + 1));
                    break;
                }
            }
            array_splice($file, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1, $slice);
        }

        return $file;
    }

    private function writeCacheDir(array $file)
    {
        $method = new \ReflectionMethod('AppKernel', 'getCacheDir');
        $slice = array_slice($file, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);
        $contents = implode('', $slice);

        if (strpos($contents, 'docker') === false) {
            $this->logStep('Modifying the cacheDir function in AppKernel');
            $tmp = ["        if (\$this->getEnvironment() === 'docker') {\n          return '/tmp/symfony/var/cache/';\n        }\n\n"];
            foreach ($slice as $key => $line) {
                if (strpos($line, '{') !== false) {
                    $slice = array_merge(array_slice($slice, 0, $key + 1), $tmp, array_slice($slice, $key + 1));
                    break;
                }
            }
            array_splice($file, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1, $slice);
        }

        return $file;
    }

    private function writeDockerComposeFile()
    {
        $this->findMySQLSettings();
        $this->findApacheSettings();
        $this->findPHPSettings();
        $this->askElasticVersion();
        $this->logStep('Generating the docker-compose file');
        if ($this->isMacOs()) {
            $twig = 'symfony/' . parent::DOCKER_COMPOSE_MAC_FILE_NAME . '.twig';
        } else {
            $twig = 'symfony/' . parent::DOCKER_COMPOSE_LINUX_FILE_NAME . '.twig';
        }

        $this->twig->renderAndWriteTemplate($twig, 'docker-compose.yml', ['shuttle' => $this->shuttle]);
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
        $fallbackDomain = preg_replace('#^https?://#', '', rtrim($fallbackDomain, '/'));
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

        if (!array_key_exists('database_name', $parameters['parameters']) &&
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
