<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class SshCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('ssh')
            ->addArgument(
                'containerName',
                InputArgument::OPTIONAL,
                'The container you want to connect to',
                'php'
            )
            ->setDescription('Run any command on your container')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $container = $input->getArgument('containerName');
        $command = $this->io->ask('Please enter your command:');
        $this->runCommand(sprintf('docker exec $(docker-compose ps -q %s) %s', $container, $command));
    }
}
