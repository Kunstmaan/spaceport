<?php
namespace Spaceport\Traits;

use Spaceport\Helpers\TwigHelper;
use Symfony\Component\Console\Output\OutputInterface;

trait TwigTrait
{

    /** @var TwigHelper */
    protected $twig;

    public function setUpTwig(OutputInterface $output){
        $this->twig = new TwigHelper($output);
    }
}