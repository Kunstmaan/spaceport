<?php

namespace Spaceport;

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
        return parent::doRun($input, $output);
    }

}