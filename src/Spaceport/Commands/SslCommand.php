<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SslCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('ssl')
            ->setDescription('Install/Reinstall ssl certs for your project')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $this->createSSLCerts();
    }
}
