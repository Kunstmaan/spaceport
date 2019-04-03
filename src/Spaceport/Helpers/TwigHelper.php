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
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function renderAndWriteTemplate($source, $target, array $variables = array())
    {
        if(!file_exists(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        $template = $this->twig->loadTemplate('templates/' . $source);
        $content = $template->render($variables);
        file_put_contents($target, $content);
    }
}
