<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NpmCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('npm')
            ->addArgument(
                'commandArgs',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Your NPM command, for instance: "run build" or "start"'
            )
            ->setDescription('Run npm commands')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $command = $input->getArgument('commandArgs');
        $this->runCommand('docker-compose run --rm --no-deps node /usr/local/bin/npm --allow-root ' . implode(" ", $command));
    }
}
