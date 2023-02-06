<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('clean')
            ->addOption('images', 'i', InputOption::VALUE_NONE, 'Remove images')
            ->addOption('volumes', 'o', InputOption::VALUE_NONE, 'Remove volumes')
            ->addOption('mysql', 'm', InputOption::VALUE_NONE, 'Remove mysql')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Remove everything')
            ->setDescription('Clean docker containers, images and volumes')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $outputString= $this->runCommand('docker container ls -a -f status=exited -f status=created -q', null, [], true);
        if (!empty($outputString)) {
            $this->runCommand('docker container rm $(docker container ls -a -f status=exited -f status=created -q)');
        }
        $this->runCommand('docker network prune -f');

        $removeImages = $input->getOption('images');
        $removeVolumes = $input->getOption('volumes');
        $removeMysql = $input->getOption('mysql');
        $removeAll = $input->getOption('all');

        if($removeMysql) {
            $outputString = $this->runCommand('docker volume ls -q | grep mysql', null, [], true);
            if (!empty($outputString)) {
                $this->runCommand('docker volume rm $(docker volume ls -q | grep mysql)');
            }
        }

        if ($removeAll || $removeImages) {
            $outputString = $this->runCommand('docker image ls -a -q', null, [], true);
            if (!empty($outputString)) {
                $this->runCommand('docker image rm $(docker image ls -a -q)');
            }
        }

        if ($removeAll || $removeVolumes) {
            $outputString = $this->runCommand('docker volume ls -q', null, [], true);
            if (!empty($outputString)) {
                $this->runCommand('docker volume rm $(docker volume ls -q)');
            }
        }

        $this->logSuccess('All gone :)');

        return 0;
    }
}
