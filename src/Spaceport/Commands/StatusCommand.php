<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('status')
            ->setDescription('See if all necessary services are up and running')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        // check if docker is running
        // check if apache is running
        // checks above are done by abstractcommand

        // check docker version
        $version = $this->runCommand("docker version | grep Version | cut -d \":\" -f2 | sed '1d'");
        $this->logSuccess('Your docker version is ' . $version);

        // check if nfsd is running when on MacOs
        if ($this->isMacOs()) {
            if ($this->isNfsdRunning()) {
                $this->logSuccess('Nfsd is running');
            } else {
                $this->logError('Nfsd is not running');
            }
        }

        // check if http proxy is running
        if ($this->isProxyRunning()) {
            $this->logSuccess('Http proxy container is running');
        } else {
            $this->logError('Http proxy container is not running');
        }

        // Compose files present
        if ($this->isDockerized()) {
            $this->logSuccess('Docker compose files present');
        }
    }
}
