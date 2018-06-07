<?php
namespace Spaceport\Commands;

use Spaceport\Model\Shuttle;
use Spaceport\Traits\IOTrait;
use Spaceport\Traits\TwigTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
     * @return true
     */
    protected function isDockerized($quiet = false)
    {
        if (!(file_exists(self::DOCKER_COMPOSE_LINUX_FILE_NAME) && file_exists(self::DOCKER_COMPOSE_MAC_FILE_NAME))) {
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
