<?php
namespace Spaceport\Commands;

use Spaceport\Traits\IOTrait;
use Spaceport\Traits\TwigTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

abstract class AbstractCommand extends Command
{

    CONST DOCKER_COMPOSE_FILE_NAME="docker-compose.yml";
    CONST DOCKER_COMPOSE_DEV_FILE_NAME="docker-compose-dev.yml";
    CONST DOCKER_COMPOSE_SYNC_FILE_NAME="docker-sync.yml";

    use TwigTrait;
    use IOTrait;

    protected function execute(InputInterface $input, OutputInterface $output){
        $this->setUpIO($input, $output);
        $this->setUpTwig($output);

        $this->showLogo();
        $this->io->title("Executing " . get_class($this));

        $this->doExecute($input, $output);
    }

    protected function runCommand($command, $timeout = null, $env = [])
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
            $this->logError($process->getErrorOutput());

            return false;
        }

        return trim($process->getOutput());
    }

    abstract protected function doExecute(InputInterface $input, OutputInterface $output);
}