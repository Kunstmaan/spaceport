<?php

namespace Spaceport;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{

    /** @var \Twig_Environment */
    public $twig;

    public function doRun(InputInterface $input, OutputInterface $output)
    {

        $loader = new \Twig_Loader_Filesystem(__DIR__ . '/templates');
        $this->twig = new \Twig_Environment($loader);

        return parent::doRun($input, $output);
    }

}