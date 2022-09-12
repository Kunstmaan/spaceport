<?php

namespace Spaceport\Traits;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait IOTrait
{
    protected string $logo = '<fg=green;options=bold>
 ███████╗██████╗  █████╗  ██████╗███████╗██████╗  ██████╗ ██████╗ ████████╗
 ██╔════╝██╔══██╗██╔══██╗██╔════╝██╔════╝██╔══██╗██╔═══██╗██╔══██╗╚══██╔══╝
 ███████╗██████╔╝███████║██║     █████╗  ██████╔╝██║   ██║██████╔╝   ██║   
 ╚════██║██╔═══╝ ██╔══██║██║     ██╔══╝  ██╔═══╝ ██║   ██║██╔══██╗   ██║   
 ███████║██║     ██║  ██║╚██████╗███████╗██║     ╚██████╔╝██║  ██║   ██║   
 ╚══════╝╚═╝     ╚═╝  ╚═╝ ╚═════╝╚══════╝╚═╝      ╚═════╝ ╚═╝  ╚═╝   ╚═╝
</fg=green;options=bold>
';

    protected ?SymfonyStyle $io = null;

    protected ?OutputInterface $output = null;

    public function setUpIO(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
    }

    public function logCommand($command): void
    {
        if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
            $this->io->text('<fg=yellow>      $ ' . $command . '</>');
        }
    }

    public function logStep($command): void
    {
        if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_QUIET) {
            $this->io->text('<fg=blue> - ' . $command . '</>');
        }
    }

    public function logWarning($command): void
    {
        if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_QUIET) {
            $this->io->warning($command);
        }
    }

    public function logSuccess($command): void
    {
        if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_QUIET) {
            $this->io->success($command);
        }
    }

    public function logError($command): void
    {
        if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_QUIET) {
            $this->io->error($command);
        }
    }

    public function showLogo(): void
    {
        $this->io->text($this->logo);
    }
}
