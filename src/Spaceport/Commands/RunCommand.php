<?php
namespace Spaceport\Commands;

use Spaceport\Exceptions\NotASymfonyProjectException;
use Spaceport\Traits\IOTrait;
use Spaceport\Traits\TwigTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;

class RunCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Run the development environment')
        ;
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
        $dockerComposeFileName = 'docker-compose.yml';
        if (!file_exists($dockerComposeFileName)){
            $mysql = $this->findMySQLSettings();

            $this->twig->renderAndWriteTemplate('symfony/'.$dockerComposeFileName, $dockerComposeFileName);
        }
    }

    /**
     * @return array
     * @throws NotASymfonyProjectException
     */
    private function findMySQLSettings(){
        $parametersFile = 'app/config/parameters.yml';
        if (file_exists($parametersFile)) {
            $yaml = new Parser();
            $value = $yaml->parse(file_get_contents($parametersFile));
            $mysql = array();
            $mysql['mysql.database'] = $value["parameters"]["database_name"];
            $mysql['mysql.user'] = $value["parameters"]["database_user"];
            $mysql['mysql.password'] = $value["parameters"]["database_password"];
            return $mysql;
        } else {
            throw new NotASymfonyProjectException("No parameters.yml file found at " . $parametersFile);
        }
    }

}