<?php

namespace Spaceport\Helpers;


use Spaceport\Model\DatabaseConnection;
use Spaceport\Model\Shuttle;
use Spaceport\Traits\IOTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

abstract class SfInitHelper
{

    use IOTrait;

    abstract public function getConfigDockerFilePath();
    abstract public function getTwigTemplateNameConfigDockerFile();
    abstract public function getDatabaseSettings();
    abstract public function getApacheDocumentRoot();

    /**
     * Method where you make changes to the app to make it docker ready.
     * E.g. Change app.php (sf3) or config/bundles.php (sf4)
     */
    abstract public function dockerizeApp();

    public function __construct(InputInterface $input, OutputInterface $output)
    {
        $this->setUpIO($input, $output);
    }

    public function findMySQLSettings(Shuttle $shuttle)
    {
        $databaseSettings = $this->getDatabaseSettings();

        if (empty($databaseSettings)) {
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
                $question = new Question('What is the database name?', $shuttle->getName());
                $db_name = $this->io->askQuestion($question);
                $databaseConnection->setMysqlDatabase($db_name);
                $question = new Question('What is the database user?', $db_name);
                $databaseConnection->setMysqlUser($this->io->askQuestion($question));
                $question = new Question('What is the database password?', $db_name);
                $databaseConnection->setMysqlPassword($this->io->askQuestion($question));
                $databaseConnection->setMysqlPort($this->getRandomMysqlPort($shuttle->getName()) + $i);

                $shuttle->addDatabaseConnection($databaseConnection);
            }
        } else {
            $databaseConnection = new DatabaseConnection();
            $databaseConnection->setMysqlDatabase($databaseSettings['database_name']);
            $databaseConnection->setMysqlUser($databaseSettings['database_user']);
            $databaseConnection->setMysqlPassword($databaseSettings['database_password']);
            $databaseConnection->setMysqlPort($this->getRandomMysqlPort($shuttle->getName()));
            $shuttle->addDatabaseConnection($databaseConnection);
        }
    }

    protected function writeDir($reflectionClass, $reflectionMethod, array $file, $returnValue)
    {
        $method = new \ReflectionMethod($reflectionClass, $reflectionMethod);
        if ($method->getDeclaringClass()->getName() === $reflectionClass) {
            $slice = array_slice($file, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);
            $contents = implode('', $slice);
            if (strpos($contents, 'docker') === false) {
                $tmp = ["        if (\$this->getEnvironment() === 'docker') {\n            return '" . $returnValue . "';\n        }\n\n"];
                foreach ($slice as $key => $line) {
                    if (strpos($line, '{') !== false) {
                        $slice = array_merge(array_slice($slice, 0, $key + 1), $tmp, array_slice($slice, $key + 1));
                        break;
                    }
                }
                array_splice($file, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1, $slice);
            }
        } else {
            $method = ["\n    public function " . $reflectionMethod . "()\n    {\n        if (\$this->getEnvironment() === 'docker') {\n            return '" . $returnValue . "';\n        }\n\n        return parent::" . $reflectionMethod . "();\n    }\n"];
            array_splice($file, -1, null, $method);
        }

        return $file;
    }

    /**
     * Generate a "random" mysql port number based on the seed
     *
     * @param $seed
     * @return int
     */
    private function getRandomMysqlPort($seed)
    {
        $count=0;
        foreach (str_split(md5($seed)) as $char) {
            $count+=hexdec($char);
        }

        //We add 33000 so we get a port like 33306 to map the docker mysql instance port 3306 onto
        return 33000 + $count % 1000;
    }

}