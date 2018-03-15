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
            ->setDescription('Runs a composer install on the container and copies the files back to the host.')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);

        $freshVendor = $input->getOption('fresh-vendor');
        if ($freshVendor) {
            $this->logStep("Initializing vendor folder on container and copying them onto the host.");
            $this->logWarning("This can be a potentially long process.");
            $this->runCommand('docker exec $(docker-compose ps -q php) composer install');
            $this->runCommand('docker cp $(docker-compose ps -q php):/app/vendor vendor/');
            $this->logSuccess("Vendor folder initialized and copied to your host system!");
        }
    }
}
