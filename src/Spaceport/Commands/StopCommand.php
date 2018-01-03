<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class StopCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('stop')
            ->addOption('all', null, null, 'Stop all containers from all projects running on docker')
            ->setDescription('Stop the development environment')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        if(!file_exists(parent::DOCKER_COMPOSE_LINUX_FILE_NAME) || !file_exists(parent::DOCKER_COMPOSE_MAC_FILE_NAME)) {
            $this->logError("There is no docker-compose file present. Run `spaceport init` first");

            return;
        }

        if($input->getOption('all')) {
            $this->stopAllContainers();
        } else {
            $this->stopProjectContainers();
        }
    }

    private function stopAllContainers()
    {
        $this->logStep("Stopping all containers");
        $this->runCommand('docker stop $(docker ps -aq)');
    }

    private function stopProjectContainers()
    {
        $this->logStep("Stopping project containers");
        $serverInfo = php_uname('s');
        if (strpos($serverInfo, 'Darwin') !== false && file_exists(parent::DOCKER_COMPOSE_MAC_FILE_NAME)) {
            $this->runCommand('docker-compose -f ' . parent::DOCKER_COMPOSE_MAC_FILE_NAME . ' stop');
            $this->runCommand('docker-sync stop');
        } else {
            $this->runCommand('docker-compose -f ' . parent::DOCKER_COMPOSE_LINUX_FILE_NAME . ' stop');
        }

        $this->logSuccess('Docker stopped');
    }
}
