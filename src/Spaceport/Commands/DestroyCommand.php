<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class DestroyCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('destroy')
            ->setDescription('Remove all container, volumes, networks, images (and restart docker and Nfsd when on MacOs)')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);

        // Look for containers
        $process = new Process('docker ps -a -q');
        $process->start();
        $process->wait();
        $response = $process->getOutput();

        if (!empty($response)) {
            // Stop all containers
            $this->runCommand('docker stop $(docker ps -a -q)');
            // Delete all containers
            $this->runCommand('docker rm $(docker ps -a -q)');
        }

        // Prune all networks (there are some predefined ones from docker itself which can't be removed)
        $this->runCommand('docker network prune -f');

        // Look for images
        $process = new Process('docker images -q');
        $process->start();
        $process->wait();
        $response = $process->getOutput();

        if (!empty($response)) {
            // Delete all images
            $this->runCommand('docker rmi $(docker images -q)');
        }

        // Look for volumes
        $process = new Process('docker volume ls -q');
        $process->start();
        $process->wait();
        $response = $process->getOutput();

        if (!empty($response)) {
            // Delete all volumes
            $this->runCommand('docker volume rm $(docker volume ls -q)');
        }

        $this->logSuccess('You sunk your battleship. Please wait until we build you a new one...');

        // restart services
        if ($this->isMacOs()) {
            $this->runCommand('sudo nfsd restart');
            $this->runCommand('osascript -e \'quit app "Docker"\'');
            $this->runCommand('open -a Docker');

            $process = new Process('while ! docker ps > /dev/null 2>&1 ; do sleep 2; done');
            $process->start();
            $process->wait();
        }

        $this->logSuccess('Your new battleship is ready');
    }
}
