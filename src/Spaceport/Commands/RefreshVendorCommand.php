<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RefreshVendorCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('vendor')
            ->setDescription('Runs a composer install on the container.')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);

        $this->logStep("Initializing vendor folder on container.");
        $this->logWarning("This can be a potentially long process.");
        $this->runCommand('docker exec $(docker-compose ps -q php) composer install');
        $this->logSuccess("Vendor folder initialized!");
    }
}
