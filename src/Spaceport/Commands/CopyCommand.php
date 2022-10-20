<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CopyCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setName('copy')
            ->addArgument('directory', null, 'Which directory to copy', 'vendor')
            ->setDescription('Copy a directory from container to host. Useful for vendor and node_modules as they are not synced');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $directory = $input->getArgument('directory');

        return $this->copyDirectory($directory);
    }

    private function copyDirectory(string $directory): int
    {
        $this->logStep(sprintf("Copying directory/file %s", $directory));
        $projectName = $this->shuttle->getName();
        $this->runCommand(sprintf('docker cp %s-web-1:/app/%s .', $projectName, $directory));

        return 0;
    }
}
