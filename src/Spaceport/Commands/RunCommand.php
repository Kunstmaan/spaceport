<?php
namespace Spaceport\Commands;

use Spaceport\Exceptions\NotAJenkinsBuiltProject;
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
        $dockerComposeFileName = 'docker-compose.yml';
        #if (!file_exists($dockerComposeFileName)) {
            $variables = array_merge($this->findMySQLSettings(), $this->findApacheSettings(), $this->findPHPSettings());
            $this->twig->renderAndWriteTemplate('symfony/' . $dockerComposeFileName . '.twig', $dockerComposeFileName, $variables);
        #}
    }

    private function findPHPSettings(){
        $php = array();
        $php['php_version'] = '7.0';
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
            $apache['apache_fallbackdomain'] = basename(getcwd()) . '.' . $value["deploy_matrix"]["production"]["server"];
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
        if (file_exists($parametersFile)) {
            $yaml = new Parser();
            $value = $yaml->parse(file_get_contents($parametersFile));
            $mysql = array();
            $mysql['mysql_database'] = $value["parameters"]["database_name"];
            $mysql['mysql_user'] = $value["parameters"]["database_user"];
            $mysql['mysql_password'] = $value["parameters"]["database_password"];
            return $mysql;
        } else {
            throw new NotASymfonyProjectException("No parameters.yml file found at " . $parametersFile);
        }
    }

}