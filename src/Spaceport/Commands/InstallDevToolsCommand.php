<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallDevToolsCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('install-dev')
            ->addArgument('install-dir', InputArgument::OPTIONAL, 'Configure the install dir for the required files')
            ->setDescription('Install all the development tools to interface with container');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $ourDir = (realpath(dirname(__FILE__)) . '/../Resources/DevTools');
        $installDir = '/usr/local/bin/';
        if ($input->getArgument('install-dir')) {
            $installDir = $input->getArgument('install-dir');
        }

        $output->writeln(sprintf('Writing our tools to directory: %s', realpath($installDir)));
        $command = sprintf("cp %s/* %s/", realpath($ourDir), realpath($installDir));
        $chmod = sprintf("chmod +x %s/*", realpath($installDir));

        if (!is_writable($installDir)) {
            $command = 'sudo -s -p "Please enter your sudo password:" ' . $command;
            $chmod = 'sudo -s -p "Please enter your sudo password:" ' . $chmod;
        }

        exec($command);
        exec($chmod);
    }
}
