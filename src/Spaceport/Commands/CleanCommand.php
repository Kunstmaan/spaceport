<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('clean')
            ->addOption('images', 'i', InputOption::VALUE_NONE, 'Remove images')
            ->addOption('volumes', 'o', InputOption::VALUE_NONE, 'Remove volumes')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Remove everything')
            ->setDescription('Clean docker containers, images and volumes')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $output = $this->runCommand('docker container ls -a -f status=exited -f status=created -q', null, [], true);
        if (!empty($output)) {
            $this->runCommand('docker container rm $(docker container ls -a -f status=exited -f status=created -q)');
        }
        $this->runCommand('docker network prune -f');

        $removeImages = $input->getOption('images');
        $removeVolumes = $input->getOption('volumes');
        $removeAll = $input->getOption('all');
        if ($removeAll) {
            $output = $this->runCommand('docker image ls -a -q', null, [], true);
            if (!empty($output)) {
                $this->runCommand('docker image rm $(docker image ls -a -q)');
            }

            $output = $this->runCommand('docker volume ls -q', null, [], true);
            if (!empty($output)) {
                $this->runCommand('docker volume rm $(docker volume ls -q)');
            }
        }

        if (!$removeAll && $removeImages) {
            $output = $this->runCommand('docker image ls -a -q', null, [], true);
            if (!empty($output)) {
                $this->runCommand('docker image rm $(docker image ls -a -q)');
            }
        }

        if (!$removeAll && $removeVolumes) {
            $output = $this->runCommand('docker volume ls -q', null, [], true);
            if (!empty($output)) {
                $this->runCommand('docker volume rm $(docker volume ls -q)');
            }
        }

        $this->logSuccess('All gone :)');
    }
}
