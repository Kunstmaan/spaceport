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

    private function checkDockerDaemonIsRunning()
    {
        $serverInfo = php_uname('s');
        if (strpos($serverInfo, 'Darwin') !== false) {
            $output = $this->runCommand('ps aux | grep docker | grep -v \'grep\' | grep -v \'com.docker.vmnetd\'', null, [], true);
            if (empty($output)) {
                $this->logError("Docker daemon is not running. Start Docker first before using spaceport");

                exit(1);
            }
        } else {
            //TODO
            //Check linux docker is running
        }
    }

    abstract protected function doExecute(InputInterface $input, OutputInterface $output);
}
