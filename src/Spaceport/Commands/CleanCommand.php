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
            ->setDescription('Run npm commands')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $this->runCommand('docker rm $(docker ps -a -f status=exited -q)');
        $removeImages = $input->getOption('images');
        $removeAll = $input->getOption('all');
        if ($removeAll) {
            $this->runCommand('docker rmi $(docker images -a -q)');
            $this->runCommand('docker volume rm $(docker volume ls -qf');
        }

        if (!$removeAll && $removeImages) {
            $this->runCommand('docker rmi $(docker images -a -q)');
        }

        $this->logSuccess('All gone :)');
    }
}
