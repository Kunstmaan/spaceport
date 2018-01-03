<?php

namespace Spaceport\Commands;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class DatabaseCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('db')
            ->setDescription('Perform database actions (e.g. sync, remove, reinit)')
            ->addOption('sync', null, null, 'Sync the database from a server')
            ->addOption('remove', null, null, 'Remove the database and it\'s container.')
            ->addOption('reinit', null, null, 'Reinitialize the database')
            ->addOption('fetch', null, null, 'Fetch the database file from the remote server');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        if ($this->isDockerized()) {
            //Always create the .spaceport/mysql dir
            $this->runCommand(sprintf('mkdir -p ~/.spaceport/mysql/%s', $this->shuttle->getName()));

            if ($input->getOption('fetch')) {
                $this->fetchDatabaseBackup();
            }

            if ($input->getOption('sync')) {
                $this->fetchDatabaseBackup();
                $this->removeDatabase();
                $this->startDatabase();
            }

            if ($input->getOption('remove')) {
                $this->removeDatabase();
            }

            if ($input->getOption('reinit')) {
                $this->removeDatabase();
                $this->startDatabase();
            }
        }
    }

    private function fetchDatabaseBackup()
    {
        $question = new Question('What is the hostname of the server ?');
        $this->shuttle->setServer($this->io->askQuestion($question));
        $question = new Question('What is the name of the project?', $this->shuttle->getName());
        $projectName = $this->io->askQuestion($question);
        $question = new Question('What is the location and filename of the mysql backup ?', sprintf('/home/projects/%s/backup/mysql.dmp.gz', $projectName));
        $databaseBackupLocationFileName = $this->io->askQuestion($question);

        $returnValue = $this->runCommand(sprintf('ssh %s stat %s', $this->shuttle->getServer(), $databaseBackupLocationFileName), null, [], true);

        if (is_bool($returnValue) && !$returnValue) {
            $this->logError(sprintf("Could not find the mysql backup file on server %s on location %s", $this->shuttle->getServer(), $databaseBackupLocationFileName));

            exit;
        }

        $this->logStep('Syncing database file');

        $this->runCommand(sprintf(
            'rsync --no-acls -rLDhz --delete --size-only %s:%s ~/.spaceport/mysql/%s/%s',
            $this->shuttle->getServer(),
            $databaseBackupLocationFileName,
            $this->shuttle->getName(),
            'mysql.sql.gz'));
    }

    private function removeDatabase()
    {
        $containerId = $this->runCommand('docker-compose ps -q mysql_' . $this->shuttle->getName());
        if (!empty($containerId)) {
            $this->logStep('Stopping mysql container');
            $this->runCommand('docker stop ' .$containerId);
            $this->logStep('Removing mysql container and volume');
            $this->runCommand('docker rm ' . $containerId);
            $this->runCommand('docker volume prune -f');
        }
    }

    private function startDatabase()
    {
        $this->logStep('Starting mysql container');
        $this->runCommand('docker-compose -f ' . parent::DOCKER_COMPOSE_MAC_FILE_NAME . ' up -d --no-recreate');
    }
}
