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
            ->addOption('skip-db-prep', null, null, 'Skip the step to fetch the database from the server')
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
            $this->runCommand('docker-compose pull', 600);
        }

        $this->setDinghySSLCerts();
        $this->runDocker($input, $output);
    }

    private function runDocker(InputInterface $input, OutputInterface $output)
    {
        $this->logStep("Building required containers");
        $this->startMaildev();
        $this->tryToPrepDatabase($input);
        $this->startContainers($output);
        $this->startProxy();
        $this->copyApacheConfig();
        $this->runComposerInstall();
        $this->runBuildUI();
        $this->logSuccess($this->getDockerRunningTextMessage());
    }

    private function tryToPrepDatabase(InputInterface $input)
    {
        if (!$input->getOption('skip-db-prep')) {
            $containerId = $this->getMysqlContainerId();
            if (empty($containerId)) {
                $home = getenv("HOME");
                $projectName = $this->shuttle->getName();
                $sqlDir = $home . '/.spaceport/mysql/' . $projectName;
                $this->logStep('Looking for database entrypoint in: ' . $sqlDir);
                if ((count(glob("$sqlDir/*")) == 0)) {
                    if (`which dsync`) {
                        $this->logStep("Database entrypoint not yet on pc --Syncing");
                        $this->runCommand("dsync db --only-fetch-db", null, [], true);
                    }
                    if ((count(glob("$sqlDir/*")) == 0)) {
                        $this->logError("Failed to sync the database. Make sure the mysql dump file is present in " . $sqlDir);
                        exit(1);
                    }
                }
            }
        }
    }

    private function startContainers(OutputInterface $output)
    {
        if ($this->isMacOs()) {
            $this->configureNfsExports($output);
        }

        $this->runCommand('docker-compose -f ' . $this->getDockerComposeFileName() . ' up -d', 600);
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
            $this->runCommand('docker run --network isolated_maildev -d --restart=always -p 1080:80 -e CONTAINER_NAME=maildev --name maildev djfarrelly/maildev', 600);

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
            $this->runCommand('docker run -d --restart=always -v /var/run/docker.sock:/tmp/docker.sock:ro -v ~/.dinghy/certs:/etc/nginx/certs -p 80:80 -p 443:443 -p 8080:80 -p 19322:19322/udp -e CONTAINER_NAME=http-proxy -e DOMAIN_TLD=dev.kunstmaan.be --name http-proxy codekitchen/dinghy-http-proxy', 600);

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

    private function runBuildUI()
    {
        if (file_exists("buildUI.sh") && !file_exists("node_modules")) {
            $this->logStep("buildUI.sh file found but no node_modules dir. Trying to run buildUI script");
            $this->runCommand("bash buildUI.sh");
        }
    }

    private function configureNfsExports(OutputInterface $output)
    {
        $command = $this->getApplication()->find('setup-nfs');
        $command->run(new ArrayInput([]), $output);

        $projectRoot = getcwd();
        $uid = $this->runCommand('id -u');
        $gid = $this->runCommand('stat -f \'%g\' /etc/exports');

        $projectExportsConfig = sprintf('\"%s\" localhost -alldirs -mapall=%s:%s', $projectRoot, $uid, $gid);

        if ($this->runCommand(sprintf('grep -qF -- "%s" "/etc/exports"', $projectExportsConfig), null, [], true) === false) {
            $lines = sprintf('# SPACEPORT-BEGIN: %s %s', $uid, $projectRoot) . '\n';
            $lines .= $projectExportsConfig . '\n';
            $lines .= sprintf('# SPACEPORT-END: %s %s', $uid, $projectRoot) . '\n';

            if ($this->runCommand(sprintf('echo "%s" | sudo tee -a /etc/exports', $lines), null, [], true) === false) {
                $this->logError('Unable to setup nfs exports config for project');
                exit(1);
            }

            $this->logStep('Nfs config change done. Restarting docker..');

            $this->runCommand('sudo nfsd restart');
            $this->runCommand('osascript -e \'quit app "Docker"\'');
            $this->runCommand('open -a Docker');

            $process = new Process('while ! docker ps > /dev/null 2>&1 ; do sleep 2; done');
            $process->start();
            $process->wait();

            $this->logStep('Docker restarted...');
        }

        $process = new Process('sudo nfsd status');
        $process->start();
        $process->wait();
        $status = $process->getOutput();
        if (false !== strpos($status, 'nfsd is not running')) {
            $this->runCommand('sudo nfsd start');
            $this->logStep('Nfsd started...');
        }
    }

    private function getDockerRunningTextMessage()
    {
        $website = "http://".$this->shuttle->getApacheVhost();
        if ($this->shuttle->sslEnabled()) {
            $website .= " and https://".$this->shuttle->getApacheVhost();
        }

        return  "Docker is up and running.\n\nWebsite ==> " . $website . "\n\nMaildev ==> http://localhost:1080";
    }
}
