<?php

namespace Spaceport\Commands;


use Humbug\SelfUpdate\Updater;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SelfUpdateCommand extends AbstractCommand
{

    protected function configure()
    {
        $this
            ->setName('self-update')
            ->setDescription('Update spaceport.phar to most recent version.');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $updater = new Updater(null, false, Updater::STRATEGY_GITHUB);
        $updater->getStrategy()->setPackageName(self::PHAR_PACKAGE_NAME);
        $updater->getStrategy()->setPharName(self::PHAR_FILE_NAME);
        $updater->getStrategy()->setCurrentLocalVersion($this->getApplication()->getVersion());

        try {
            if ($updater->hasUpdate()) {
                $this->logStep("Going to update spaceport to the latest version");
                $updater->update();
                $this->logSuccess("spaceport is updated to the latest version: " . $updater->getNewVersion());
            } else {
                $this->logSuccess("spaceport is already updated to the latest version");
            }
        } catch (\Exception $e) {
            $this->logError("Something went wrong trying to update spaceport to the latest version.");
            exit(1);
        }
    }
}