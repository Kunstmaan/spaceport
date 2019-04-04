<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class StartCommand extends AbstractCommand
{

    const MAILDEV_NETWORK = "isolated_maildev";

    protected function configure()
    {
        $this
            ->setName('start')
            ->setDescription('Start the development environment')
            ->addOption('clean', null, null, 'Start with clean containers.')
            ->addOption('fresh-images', null, null, 'Pull new images from dockerhub.');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $dockerFile = $this->getDockerComposeFullFileName();
        if (!file_exists($dockerFile)) {
            $this->logError(sprintf("There is no %s file present. Run `spaceport init` first", $dockerFile));
            exit(1);
        }

        $this->isOwnerOfFilesInDirectory();

        $clean = $input->getOption('clean');
        if ($clean) {
            $this->logStep("Cleaning containers");
            $this->runCommand('docker-compose down --remove-orphans');
        }

        $freshImages = $input->getOption('fresh-images');
        if ($freshImages) {
            $this->logStep("Pulling required images");
            $this->runCommand('docker-compose pull');
        }

        $this->setDinghySSLCerts();
        $this->runDocker($output);
    }

    private function runDocker(OutputInterface $output)
    {
        $this->logStep("Building required containers");
        $this->startMaildev();
        $this->tryToPrepDatabase();
        $this->startContainers($output);
        $this->startProxy();
        $this->copyApacheConfig();
        $this->runComposerInstall();
        $this->runBuildUI();
        $text = "Docker is up and running.\n\nWebsite ==> " . $this->shuttle->getApacheVhost() . "\n\nMaildev ==> localhost:1080";
        $this->logSuccess($text);
    }

    private function tryToPrepDatabase()
    {
        $home = getenv("HOME");
        $projectName = $this->shuttle->getName();
        $sqlDir = $home . '/.spaceport/mysql/' . $projectName;
        $this->logStep('Looking for database entrypoint in: ' . $sqlDir);
        if((count(glob("$sqlDir/*")) == 0)) {
            $this->logStep("Database entrypoint not yet on pc --Syncing");
            if (`which dsync`) {
                $this->runCommand("dsync db --only-fetch-db", null, [], true);
            }
        }
    }

    private function startContainers(OutputInterface $output)
    {
        $dockerFile = $this->getDockerComposeFileName();
        $this->runCommand('docker-compose -f ' . $dockerFile . ' up -d');

    }

    private function startMaildev()
    {
        //Check if the network is already created
        $output = $this->runCommand('docker network ls | grep ' . self::MAILDEV_NETWORK, null, [], true);
        if (empty($output)) {
            $this->logStep("Creating maildev network");
            $this->runCommand("docker network create isolated_maildev");
        }
        //Check if maildev container is present
        $containerId = $this->runCommand('docker ps -a --filter="name=maildev" -q');
        if (empty($containerId)) {
            $this->logStep('Starting maildev');
            $this->runCommand('docker run --network isolated_maildev -d --restart=always -p 1080:80 -e CONTAINER_NAME=maildev --name maildev djfarrelly/maildev');

            return;
        }

        //Check if maildev is running
        $containerRunning = $this->runCommand('docker inspect -f \'{{.State.Running}}\' ' . $containerId);
        if ($containerRunning == 'false') {
            $this->logStep("Starting maildev");
            $this->runCommand('docker start ' . $containerId);
        } else {
            $this->logStep("maildev already running -- Skipping");
        }
    }

    private function startProxy()
    {
        $containerId = $this->getProxyContainerId();
        //Check if http-proxy container is present
        if (empty($containerId)) {
            $this->logStep('Starting proxy');
            $this->runCommand('docker run -d --restart=always -v /var/run/docker.sock:/tmp/docker.sock:ro -v ~/.dinghy/certs:/etc/nginx/certs -p 80:80 -p 443:443 -p 8080:80 -p 19322:19322/udp -e CONTAINER_NAME=http-proxy -e DOMAIN_TLD=dev.kunstmaan.be --name http-proxy codekitchen/dinghy-http-proxy');

            return;
        }

        //Check if http-proxy is running
        if (!$this->isProxyRunning($containerId)) {
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
            $containerId = $this->runCommand('docker-compose -f ' . $this->getDockerComposeFileName() . ' ps -q apache');
            if (!empty($containerId)) {
                $this->logStep("Swapping default Apache config with docker-apache.conf file");
                $this->runCommand('docker cp docker-apache.conf ' . $containerId . ":/etc/apache2/sites-available/000-default.conf");
            } else {
                $this->logWarning("No running Apache container found!.");
            }
        }
    }

    private function runComposerInstall()
    {
        if (file_exists("composer.json") && !file_exists("vendor")) {
            $this->logStep("composer.json file found but no vendor dir. Trying to run composer install");
            $containerId = $this->runCommand("docker-compose -f " . $this->getDockerComposeFileName() . " ps -q php");
            if (!empty($containerId)) {
                $this->logStep("Running composer install");
                $this->runCommand("docker exec " . $containerId . " composer install");
            } else {
                $this->logWarning("No running Php container found!.");
            }
        }
    }

    private function runBuildUI()
    {
        if (file_exists("buildUI.sh") && !file_exists("node_modules")) {
            $this->logStep("buildUI.sh file found but no node_modules dir. Trying to run buildUI script");
            $this->runCommand("bash buildUI.sh");
        }
    }

}
