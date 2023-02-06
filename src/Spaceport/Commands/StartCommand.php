<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StartCommand extends AbstractCommand
{
    const MAILDEV_NETWORK = "isolated_maildev";

    protected function configure(): void
    {
        $this
            ->setName('start')
            ->setDescription('Start the development environment')
            ->addOption('clean', null, null, 'Start with clean containers.')
            ->addOption('fresh-images', null, null, 'Pull new images from dockerhub.');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $dockerFile = $this->getDockerComposeFullFileName();
        if (!file_exists($dockerFile)) {
            $this->logError(sprintf("There is no %s file present. Run `spaceport init` first", $dockerFile));
            exit(1);
        }

        $this->isOwnerOfFilesInDirectory();

        $clean = $input->getOption('clean');
        if ($clean) {
            $this->logStep("Cleaning containers");
            $this->runCommand('docker-compose down --remove-orphans');
        }

        $freshImages = $input->getOption('fresh-images');
        if ($freshImages) {
            $this->logStep("Pulling required images");
            $this->runCommand('docker-compose pull', 600);
        }

        $this->runDocker($input, $output);
        return 0;
    }

    private function runDocker(InputInterface $input, OutputInterface $output): void
    {
        $this->logStep("Building required containers");
        $this->startWatchtower();
        $this->startMaildev();
        $this->startContainers($output);
        $this->logSuccess($this->getDockerRunningTextMessage());
    }

    private function startContainers(OutputInterface $output): void
    {
        $this->runCommand('docker-compose -f ' . $this->getDockerComposeFileName() . ' up -d', 600);
    }

    private function startMaildev(): void
    {
        //Check if the network is already created
        $output = $this->runCommand('docker network ls | grep ' . self::MAILDEV_NETWORK, null, [], true);
        if (empty($output)) {
            $this->logStep("Creating maildev network");
            $this->runCommand("docker network create isolated_maildev");
        }
        //Check if maildev container is present
        $containerId = $this->runCommand('docker container ls -a -f name=maildev -q');
        if (empty($containerId)) {
            $this->logStep('Starting maildev');
            $this->runCommand('docker container run --network isolated_maildev -d --restart=always -p 1080:1080 -e CONTAINER_NAME=maildev --name maildev schickling/mailcatcher', 600);

            return;
        }

        //Check if maildev is running
        $containerRunning = $this->runCommand('docker container inspect -f {{.State.Running}} ' . $containerId);
        if ($containerRunning === 'false') {
            $this->logStep("Starting maildev");
            $this->runCommand('docker container start ' . $containerId);
        } else {
            $this->logStep("maildev already running -- Skipping");
        }
    }

    private function startWatchtower()
    {
        $containerId = $this->runCommand('docker container ls -a -f name=watchtower -q');
        //Check if watchtower container is present
        if (empty($containerId)) {
            $this->logStep('Starting watchtower');
            $this->runCommand('docker container run -d --name watchtower --restart always -v /var/run/docker.sock:/var/run/docker.sock:ro containrrr/watchtower --cleanup --include-stopped --interval 3600', 600);

            return;
        }

        //Check if watchtower is running
        $containerRunning = $this->runCommand('docker container inspect -f {{.State.Running}} ' . $containerId);
        if ($containerRunning === 'false') {
            $this->logStep("Starting watchtower");
            $this->runCommand('docker start ' . $containerId);
        } else {
            $this->logStep("Watchtower already running -- Skipping");
        }
    }

    private function getDockerRunningTextMessage(): string
    {
        return  "Docker is up and running.\n\nWebsite ==> localhost:8080\n\nMaildev ==> http://localhost:1080";
    }
}
