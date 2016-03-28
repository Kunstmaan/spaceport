<?php
namespace Spaceport\Commands;

use Spaceport\Traits\IOTrait;
use Spaceport\Traits\TwigTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{

    use TwigTrait;
    use IOTrait;

    protected function execute(InputInterface $input, OutputInterface $output){
        $this->setUpIO($input, $output);
        $this->setUpTwig($output);
        $this->doExecute($input, $output);
    }

    abstract protected function doExecute(InputInterface $input, OutputInterface $output);
}