<?php

namespace Spaceport\Helpers;

use Symfony\Component\Console\Output\OutputInterface;

class TwigHelper
{
    private $twig;

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $twig_loader = new \Twig_Loader_Chain(array(
            new \Twig_Loader_Filesystem(BASE_DIR),
            new \Twig_Loader_Array(array())
        ));
        $twig_options = array(
            'charset' => "UTF-8",
            'debug' => $output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL,
            'strict_variables' => $output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL,
        );
        $this->twig = new \Twig_Environment($twig_loader, $twig_options);
    }

    /**
     * @param $source
     * @param $target
     * @param array $variables
     */
    public function renderAndWriteTemplate($source, $target, array $variables = array())
    {
        $template = $this->twig->loadTemplate('templates/' . $source);
        $content = $template->render($variables);
        file_put_contents($target, $content);
    }
}
