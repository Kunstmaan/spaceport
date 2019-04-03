<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        $dockerFile = $this->getDockerComposeFileName();
        if (!file_exists($dockerFile)) {
            $this->logError("There is no docker-compose file present. Run `spaceport init` first");

            return;
        }

        if ($input->getOption('all')) {
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
        $dockerFile = $this->getDockerComposeFileName();
        $this->runCommand('docker-compose -f ' . $dockerFile . ' stop');

        $this->logSuccess('Docker stopped');
    }
}
