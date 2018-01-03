<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class StartCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('start')
            ->setDescription('Start the development environment')
            ->addOption('clean', null, null, 'Start with clean containers')
            ->addOption('fresh-images', null, null, 'Pull new images from dockerhub.')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        if(!file_exists(parent::DOCKER_COMPOSE_LINUX_FILE_NAME)) {
            $this->logError("There is no docker-compose.yml file present. Run `spaceport init` first");

            return;
        }

        $clean = $input->getOption('clean');
        if ($clean) {
            $this->logStep("Cleaning containers");
            $this->runCommand('docker-compose down');
        }

        $fresh = $input->getOption('fresh-images');
        if ($fresh) {
            $this->logStep("Pulling required images");
            $this->runCommand('docker-compose pull');
        }

        $this->runDocker();
    }

    private function runDocker()
    {
        $this->logStep("Building required containers");
        $this->startContainers();
        $this->startProxy();
        $this->copyApacheConfig();
        $text = "Docker is up and running.\n\nWebsite ==> " . $this->shuttle->getApacheVhost() . "\n\nMaildev ==> localhost:1080";
        $this->logSuccess($text);
    }

    private function startContainers()
    {
        $serverInfo = php_uname('s');
        if (strpos($serverInfo, 'Darwin') !== false && file_exists(parent::DOCKER_COMPOSE_MAC_FILE_NAME)) {
            $config = Yaml::parse(file_get_contents(parent::DOCKER_COMPOSE_MAC_FILE_NAME));
            if (isset($config['volumes'])) {
                foreach ($config['volumes'] as $volume => $data) {
                    if (!empty($data) && isset($data['external']) && $data['external'] == true) {
                        $this->runCommand("docker volume create --name=$volume");
                    }
                }
            }

            $this->runCommand('docker-compose -f ' . parent::DOCKER_COMPOSE_MAC_FILE_NAME . ' up -d');
            $this->runCommand('docker-sync start');

        } else {
            $this->runCommand('docker-compose -f ' . parent::DOCKER_COMPOSE_LINUX_FILE_NAME . ' up -d');
        }
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
