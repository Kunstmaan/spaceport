<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StopCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('stop')
            ->addOption('all', null, null, 'Stop all containers from all projects running on docker')
            ->setDescription('Stop the development environment');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);

        if ($input->getOption('all')) {
            return $this->stopAllContainers();
        }
        return $this->stopProjectContainers();
    }

    private function stopAllContainers(): int
    {
        $this->logStep("Stopping all containers");
        $this->runCommand('docker container stop $(docker container ls -a -q)');

        return 0;
    }

    private function stopProjectContainers(): int
    {
        $this->logStep("Stopping project containers");
        $dockerFile = $this->getDockerComposeFileName();
        if (!file_exists($dockerFile)) {
            $this->logError("There is no docker-compose file present. Run `spaceport init` first");

            return 1;
        }

        $this->runCommand('docker-compose -f ' . $dockerFile . ' stop');

        return 0;
    }
}
