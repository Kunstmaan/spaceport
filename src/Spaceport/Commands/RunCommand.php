<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Run the development environment');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        if(!file_exists(parent::DOCKER_COMPOSE_FILE_NAME)) {
            $this->logError("There is no docker-composer.yml file present. Run `spaceport init` first");

            return;
        }
        $this->runDocker();
    }

    private function runDocker()
    {
        $this->logStep("Stopping containers");
        $this->runCommand('docker-compose down');
        $this->logStep("Pulling required impages");
        $this->runCommand('docker-compose pull');
        $this->logStep("Building required containers");
        $this->runCommand('docker-compose up -d');
        $this->logSuccess('Docker is up and running');
    }
}
