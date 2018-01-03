<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BowerCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('bower')
            ->addArgument(
                'commandArgs',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Your bower command, for instance: "install"'
            )
            ->setDescription('Run bower commands')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $command = $input->getArgument('commandArgs');
        $this->runCommand('docker-compose run --rm --no-deps node /usr/local/bin/bower --allow-root ' . implode(" ", $command));
    }
}
