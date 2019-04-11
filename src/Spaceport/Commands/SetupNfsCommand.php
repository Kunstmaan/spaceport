<?php

namespace Spaceport\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class SetupNfsCommand extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setName('setup-nfs')
            ->setDescription('Setup local nfs setings')
        ;
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->isMacOs()) {
            $output->writeln('This command should only be executed on OS X');
            exit(1);
        }

        $nfsConfigCheck = new Process('grep -- "nfs.server.mount.require_resv_port = 0" "/etc/nfs.conf"');
        $nfsConfigCheck->run();

        if ($nfsConfigCheck->getExitCode() === 0) {
            $output->writeln('<info>Nfs config has the required settings</info>');

            return;
        }

        $output->write('Setup required nfs settings...');

        $nfsConfigCommand = 'grep -q -- "nfs.server.mount.require_resv_port = 0" "/etc/nfs.conf" ';
        $nfsConfigCommand .= '|| echo "nfs.server.mount.require_resv_port = 0" ';
        $nfsConfigCommand .= '| sudo tee -a /etc/nfs.conf > /dev/null';

        $nsfConfigCheck = new Process($nfsConfigCommand);
        $nsfConfigCheck->run();

        if ($nsfConfigCheck->getExitCode() === 0) {
            $output->writeLn('<info>OK</info>');
        } else {
            $output->write('<error>FAILED</error>');
        }

        $output->write('Restarting nfs daemon...');

        $nfsdRestart = new Process('sudo nfsd restart');
        $nfsdRestart->run();

        if ($nfsdRestart->getExitCode() === 0) {
            $output->writeLn('<info>OK</info>');
        } else {
            $output->write('<error>FAILED</error>');
        }
    }
}