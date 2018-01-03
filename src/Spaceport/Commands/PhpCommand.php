<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PhpCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('php')
            ->addArgument(
                'commandArgs',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Your PHP command, for instance: "./bin/phpunit'
            )
            ->setDescription('Run PHP commands')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $command = $input->getArgument('commandArgs');
        $this->runCommand('docker exec $(docker-compose ps -q php) /usr/bin/php ' . implode(" ", $command));
    }
}
