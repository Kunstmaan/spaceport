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
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Remove everything')
            ->setDescription('Clean docker containers, images and volumes')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $output = $this->runCommand('docker ps -a -f status=exited -q', null, [], true);
        if (!empty($output)) {
            $this->runCommand('docker rm $(docker ps -a -f status=exited -q)');
        }
        $this->runCommand('docker network prune -f');

        $removeImages = $input->getOption('images');
        $removeAll = $input->getOption('all');
        if ($removeAll) {
            $this->runCommand('docker rmi $(docker images -a -q)');
            $this->runCommand('docker volume rm $(docker volume ls -q)');
        }

        if (!$removeAll && $removeImages) {
            $this->runCommand('docker rmi $(docker images -a -q)');
        }


        $this->logSuccess('All gone :)');
    }
}
