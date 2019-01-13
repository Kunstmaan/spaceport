<?php
namespace Spaceport\Commands;

use Spaceport\Model\Shuttle;
use Spaceport\Traits\IOTrait;
use Spaceport\Traits\TwigTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;

abstract class AbstractCommand extends Command
{

    CONST DOCKER_COMPOSE_LINUX_FILE_NAME="docker-compose.yml";
    CONST DOCKER_COMPOSE_MAC_FILE_NAME="docker-compose-mac.yml";

    use TwigTrait;
    use IOTrait;

    /**
     * @var Shuttle
     */
    protected $shuttle;

    protected function execute(InputInterface $input, OutputInterface $output){
        $this->setUpIO($input, $output);
        $this->setUpTwig($output);

        $this->showLogo();
        $this->io->title("Executing " . get_class($this));

        $this->shuttle = new Shuttle();

        $this->checkDockerDaemonIsRunning();
        $this->isApacheRunning();

        $this->doExecute($input, $output);
    }

    protected function runCommand($command, $timeout = null, $env = [], $quiet = false)
    {
        $this->logCommand($command);
        $env = array_replace($_ENV, $_SERVER, $env);
        $process = new Process($command, null, $env);
        $process->setTimeout($timeout);
        $process->run(function ($type, $buffer) {
            if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_VERBOSE) {
                strlen($type); // just to get rid of the scrutinizer error... sigh
                echo $buffer;
            }
        });
        if (!$process->isSuccessful()) {
            if (!$quiet) {
                $this->logError($process->getErrorOutput());
            }

            return false;
        }

        return trim($process->getOutput());
    }

    /**
     * Check if the project is ready for docker.
     * Logs an error if the project is not ready for docker.
     *
     * @param bool $quiet
     * @return true
     */
    protected function isDockerized($quiet = false)
    {
        $dockerFile = $this->getDockerFile();
        if (!file_exists($dockerFile)) {
            if (!$quiet) {
                $this->logError("There is no docker-compose file present. Run `spaceport init` first");
            }

            return false;
        }

        return true;
    }

    /**
     * Check is OS is MacOs
     *
     * @return bool
     */
    protected function isMacOs()
    {
        return \PHP_OS === 'Darwin';
    }

    /**
     * Check if nfsd is running
     *
     * @return bool
     */
    protected function isNfsdRunning()
    {
        // Command throws exit code so we need to do the process manually
        $process = new Process('sudo nfsd status | grep "not running"');
        $process->start();
        $process->wait();
        return empty($process->getOutput());
    }

    protected function getProxyContainerId()
    {
        return $this->runCommand('docker ps -a --filter="name=http-proxy" -q');
    }

    protected function isProxyRunning($containerId = null)
    {
        if (null === $containerId) {
            $containerId = $this->getProxyContainerId();
        }

        if (empty($containerId)) {
            return false;
        }

        $containerRunning = $this->runCommand('docker inspect -f \'{{.State.Running}}\' ' . $containerId);
        return $containerRunning !== 'false';
    }

    /**
     * Check if dinghy ssl certs already exist and ask to enable ssl if they don't
     */
    protected function setDinghySSLCerts()
    {
        $home = getenv("HOME");
        if (file_exists($home . "/.dinghy/certs/" . $this->shuttle->getApacheVhost() . ".crt") && file_exists($home . "/.dinghy/certs/" . $this->shuttle->getApacheVhost() . ".key")) {
            return;
        }

        if ($this->io->confirm('Do you want to enable SSL for your Apache vhost ?', true)) {
            $this->createSSLCerts();
        }
    }

    protected function createSSLCerts()
    {
        //Check if dinghy dir exists
        if (!file_exists("~/.dinghy/certs/")) {
            $this->runCommand("mkdir -p ~/.dinghy/certs/");
        }
        $sslFilesLocation = $this->getSSLFileLocation();
        if ($sslFilesLocation) {
            $this->runCommand("sudo -s -p \"Please enter your sudo password:\" cp " . $sslFilesLocation . "*.crt ~/.dinghy/certs/" . $this->shuttle->getApacheVhost() . ".crt");
            $this->runCommand("sudo -s -p \"Please enter your sudo password:\" cp " . $sslFilesLocation . "*.key ~/.dinghy/certs/" . $this->shuttle->getApacheVhost() . ".key");
        }

    }

    protected function getDockerFile()
    {
        return $this->isMacOs() ? self::DOCKER_COMPOSE_MAC_FILE_NAME : self::DOCKER_COMPOSE_LINUX_FILE_NAME;
    }

    protected function getDockerFullFileName()
    {
        $currentWorkDir = getcwd();
        $dockerFilePath = $currentWorkDir . DIRECTORY_SEPARATOR;
        $dockerFile = $this->getDockerFile();

        return $dockerFilePath . $dockerFile;
    }

    private function getSSLFileLocation()
    {
        $question = new Question('What is the location of the SSL cert file and key file ? (Note: the dir should only contain 1 crt and 1 key file)', '/etc/ssl/docker_certs/');
        $sslFilesLocation = $this->io->askQuestion($question);
        if (!substr($sslFilesLocation, -1) == '/') {
            $sslFilesLocation = $sslFilesLocation . '/';
        }
        if (!file_exists($sslFilesLocation)) {
            $this->logError("The location " . $sslFilesLocation . " does not exist");

            return false;
        }
        if (count(glob($sslFilesLocation . "*.crt")) == 0) {
            $this->logError("There is no crt file available in the location " . $sslFilesLocation . ".");

            return false;
        }
        if (count(glob($sslFilesLocation . "*.crt")) > 1) {
            $this->logError("There are multiple crt files available in the location " . $sslFilesLocation . ". There should only be one crt file.");

            return false;
        }
        if (count(glob($sslFilesLocation . "*.key")) == 0) {
            $this->logError("There is no key file available in the location " . $sslFilesLocation . ".");

            return false;
        }
        if (count(glob($sslFilesLocation . "*.key")) > 1) {
            $this->logError("There are multiple key files available in the location " . $sslFilesLocation . ". There should only be one crt file.");

            return false;
        }

        return $sslFilesLocation;
    }

    private function checkDockerDaemonIsRunning()
    {
        if ($this->isMacOs()) {
            $output = $this->runCommand('ps aux | grep docker | grep -v \'grep\' | grep -v \'com.docker.vmnetd\'', null, [], true);
            if (empty($output)) {
                $this->logError("Docker daemon is not running. Start Docker first before using spaceport");

                exit(1);
            }
        } else {
            //TODO
            $output = $this->runCommand('ps aux | grep docker | grep -v \'grep\' | grep -v \'com.docker.vmnetd\'', null, [], true);
            if (empty($output)) {
                $this->logError("Docker daemon is not running. Start Docker first before using spaceport");

                exit(1);
            }
        }
    }

    /**
     * Check if apache is running, if so, exit when running spaceport start, otherwise just notify
     */
    private function isApacheRunning()
    {
        $process = new Process('pgrep httpd');
        $process->start();
        $process->wait();
        $processes = $process->getOutput();
        if (!empty($processes)) {
            $this->logError('Apache seems to be running. Please shutdown apache and rerun your command.');
            // Only exit on spaceport start
            if ($this instanceof StartCommand) {
                exit(1);
            }
        }
    }

    abstract protected function doExecute(InputInterface $input, OutputInterface $output);
}
