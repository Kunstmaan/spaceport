<?php

namespace Spaceport;

use Humbug\SelfUpdate\Strategy\GithubStrategy;
use Humbug\SelfUpdate\Updater;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{

    const VERSION_URL = 'https://s3-eu-west-1.amazonaws.com/kunstmaan-spaceport/spaceport.version';
    const PHAR_URL = 'https://s3-eu-west-1.amazonaws.com/kunstmaan-spaceport/spaceport.phar';
    const PACKAGE_NAME = 'kunstmaan/spaceport';
    const FILE_NAME = 'spaceport.phar';

    public function doRun(InputInterface $input, OutputInterface $output)
    {

        //$this->checkForUpdate();

//        if ($this->isInPharMode() && in_array($commandName, array('new', 'demo'), true)) {
//            if (!$this->checkIfInstallerIsUpdated()) {
//                $output->writeln(sprintf(
//                    " <comment>[WARNING]</comment> Your Symfony Installer version is outdated.\n".
//                    ' Execute the command "%s selfupdate" to get the latest version.',
//                    $_SERVER['PHP_SELF']
//                ));
//            }
//        }

        return parent::doRun($input, $output);
    }

    public function isInPharMode()
    {
        return 'phar://' === substr(__DIR__, 0, 7);
    }

    private function checkForUpdate()
    {
        $updater = new Updater();
        $updater->setStrategy(Updater::STRATEGY_GITHUB);
        /** @var GithubStrategy $strategyInterface */
        $strategyInterface = $updater->getStrategy();
        $strategyInterface->setPackageName('kunstmaan/spaceport');
        $strategyInterface->setPharName('spaceport.phar');
        $strategyInterface->setCurrentLocalVersion($this->getVersion());

        try {
            $result = $updater->hasUpdate();
            if ($result) {
                echo(sprintf(
                    'The current stable build available remotely is: %s',
                    $updater->getNewVersion()
                ));
            } elseif (false === $updater->getNewVersion()) {
                echo('There are no stable builds available.');
            } else {
                echo('You have the current stable build installed.');
            }
        } catch (\Exception $e) {
            exit('Well, something happened! Either an oopsie or something involving hackers.');
        }
    }
}