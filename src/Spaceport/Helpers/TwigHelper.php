<?php

namespace Spaceport\Helpers;

use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

class TwigHelper
{
    private $twig;

    public function __construct(OutputInterface $output)
    {
        $twig_loader = new ChainLoader(array(
            new FilesystemLoader(BASE_DIR),
            new ArrayLoader(array())
        ));
        $twig_options = array(
            'charset' => "UTF-8",
            'debug' => $output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL,
            'strict_variables' => $output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL,
        );
        $this->twig = new Environment($twig_loader, $twig_options);
    }

    public function renderAndWriteTemplate($source, $target, array $variables = array())
    {
        if(!file_exists(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        $template = $this->twig->load('templates/' . $source);
        $content = $template->render($variables);
        file_put_contents($target, $content);
    }
}
