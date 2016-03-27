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

        $this->wireTwig($output);

        return parent::doRun($input, $output);
    }

    /**
     * @param OutputInterface $output
     */
    public function wireTwig(OutputInterface $output)
    {
        $twig_loader = new \Twig_Loader_Chain(array(
            new \Twig_Loader_Filesystem(array(BASE_DIR . '/templates', './templates')),
            new \Twig_Loader_Array(array())
        ));
        $twig_options = array(
            'charset' => "UTF-8",
            'debug' => $output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL,
            'strict_variables' => $output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL,
        );
        $this->twig = new \Twig_Environment($twig_loader, $twig_options);
    }

}