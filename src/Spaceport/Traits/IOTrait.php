<?php

namespace Spaceport\Traits;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait IOTrait
{

    protected $logo = '<fg=green;options=bold>
 ███████╗██████╗  █████╗  ██████╗███████╗██████╗  ██████╗ ██████╗ ████████╗
 ██╔════╝██╔══██╗██╔══██╗██╔════╝██╔════╝██╔══██╗██╔═══██╗██╔══██╗╚══██╔══╝
 ███████╗██████╔╝███████║██║     █████╗  ██████╔╝██║   ██║██████╔╝   ██║   
 ╚════██║██╔═══╝ ██╔══██║██║     ██╔══╝  ██╔═══╝ ██║   ██║██╔══██╗   ██║   
 ███████║██║     ██║  ██║╚██████╗███████╗██║     ╚██████╔╝██║  ██║   ██║   
 ╚══════╝╚═╝     ╚═╝  ╚═╝ ╚═════╝╚══════╝╚═╝      ╚═════╝ ╚═╝  ╚═╝   ╚═╝
</fg=green;options=bold>
';

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /** @var OutputInterface */
    protected $output;

    public function setUpIO(InputInterface $input, OutputInterface $output){
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
    }

    public function logCommand($command){
        if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $this->io->text('<fg=yellow>      $ ' . $command . '</>');
        }
    }

    public function logStep($command){
        if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_QUIET) {
            $this->io->text('<fg=blue> - ' . $command . '</>');
        }
    }

    public function logWarning($command){
        if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_QUIET) {
            $this->io->warning($command);
        }
    }

    public function logSuccess($command){
        if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_QUIET) {
            $this->io->success($command);
        }
    }

    public function logError($command){
        if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_QUIET) {
            $this->io->error($command);
        }
    }

    public function showLogo(){
        $this->io->text($this->logo);
    }
}