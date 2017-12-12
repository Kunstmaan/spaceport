<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Run the development environment');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        if(!file_exists(parent::DOCKER_COMPOSE_FILE_NAME)) {
            $this->logError("There is no docker-composer.yml file present. Run `spaceport init` first");

            return;
        }
        $this->runDocker();
    }

    private function runDocker()
    {
        $this->logStep("Stopping containers");
        $this->runCommand('docker-compose down');
        $this->logStep("Pulling required images");
        $this->runCommand('docker-compose pull');
        $this->logStep("Building required containers");
        $this->runCommand('docker-compose up -d');
        $this->startProxy();
        $this->copyApacheConfig();
        $this->logSuccess('Docker is up and running');
    }

    private function startProxy()
    {
        //Check if http-proxy container is present
        $containerId = $this->runCommand('docker ps -a --filter="name=http-proxy" -q');
        if (empty($containerId)) {
            $this->logStep('Starting proxy');
            $this->runCommand('docker run -d --restart=always -v /var/run/docker.sock:/tmp/docker.sock:ro -v ~/.dinghy/certs:/etc/nginx/certs -p 80:80 -p 443:443 -p 19322:19322/udp -e CONTAINER_NAME=http-proxy -e DOMAIN_TLD=dev.kunstmaan.be --name http-proxy codekitchen/dinghy-http-proxy');

            return;
        }

        //Check if http-proxy is running
        $containerRunning = $this->runCommand('docker inspect -f \'{{.State.Running}}\' ' . $containerId);
        if ($containerRunning == 'false') {
            $this->logStep("Starting proxy");
            $this->runCommand('docker start ' . $containerId);
        } else {
            $this->logStep("Proxy already running -- Skipping");
        }
    }

    private function copyApacheConfig()
    {
        $apacheConfigFile = 'docker-apache.conf';
        if (file_exists($apacheConfigFile)) {
            $this->logStep("Docker Apache config find. Going to swap the default.");
            $containerId = $this->runCommand('docker-compose ps -q apache');
            if (!empty($containerId)) {
                $this->logStep("Swapping default Apache config with docker-apache.conf file");
                $this->runCommand('docker cp docker-apache.conf ' . $containerId . ":/etc/apache2/sites-available/000-default.conf");
            } else {
                $this->logWarning("No running Apache container found!.");
            }
        }
    }
}
