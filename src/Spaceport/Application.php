<?php

namespace Spaceport;

use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        if (posix_getuid() == 0) {
            throw new LogicException("This should not be run as root. You must run it as your normal user. \n
If we need sudo rights we will ask for your password and you will provide it or start crying. Your choice.");
        }

        return parent::doRun($input, $output);
    }
}