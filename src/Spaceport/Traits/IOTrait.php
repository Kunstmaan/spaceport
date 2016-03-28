<?php

namespace Spaceport\Traits;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait IOTrait
{

    /**
     * @var SymfonyStyle
     */
    protected $io;

    public function setUpIO(InputInterface $input, OutputInterface $output){
        $this->io = new SymfonyStyle($input, $output);
    }

}