<?php

namespace Spaceport;

use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        if (posix_getuid() == 0) {
            throw new LogicException("This should not be run as root. You must run it as your normal user.");
        }

        return parent::doRun($input, $output);
    }
}
