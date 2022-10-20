<?php

namespace Spaceport\Traits;

use Spaceport\Helpers\TwigHelper;
use Symfony\Component\Console\Output\OutputInterface;

trait TwigTrait
{
    protected ?TwigHelper $twig = null;

    public function setUpTwig(OutputInterface $output): void
    {
        $this->twig = new TwigHelper($output);
    }
}
