<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
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
        $process = new Process('docker container ls -a -q');
        $process->start();
        $process->wait();
        $response = $process->getOutput();

        if (!empty($response)) {
            // Stop all containers
            $this->runCommand('docker container stop $(docker container ls -a -q)');
            // Delete all containers
            $this->runCommand('docker container rm $(docker container ls -a -q)');
        }

        // Prune all networks (there are some predefined ones from docker itself which can't be removed)
        $this->runCommand('docker network prune -f');

        // Look for images
        $process = new Process('docker image ls -q');
        $process->start();
        $process->wait();
        $response = $process->getOutput();

        if (!empty($response)) {
            // Delete all images
            $this->runCommand('docker image rm $(docker image ls -q)');
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

            $process = new Process('while ! docker container ls > /dev/null 2>&1 ; do sleep 2; done');
            $process->start();
            $process->wait();
        }

        $this->logSuccess('Your new battleship is ready');
    }
}
