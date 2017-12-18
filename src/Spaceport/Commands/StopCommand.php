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
            ->setDescription('Stop the development environment')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        if(!file_exists(parent::DOCKER_COMPOSE_FILE_NAME)) {
            $this->logError("There is no docker-compose.yml file present. Run `spaceport init` first");

            return;
        }
        $this->stopDocker();
    }

    private function stopDocker()
    {
        $this->logStep("Stopping containers");
        $this->runCommand('docker-compose stop');
        $serverInfo = php_uname('s');
        if (strpos($serverInfo, 'Darwin') !== false && file_exists('docker-compose-dev.yml')) {
            $this->runCommand('docker-sync stop');
        }

        $this->logSuccess('Docker stopped');
    }
}
