<?php
namespace Spaceport\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunCommand extends Command
{

    /**
     * @var SymfonyStyle
     */
    protected $io;

    protected function configure()
    {
        $this
            ->setName('run')
            ->setDescription('Update spaceport.phar to most recent stable, pre-release or development build.')
        ;
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $twig = $this->getApplication()->twig;
        $template = $twig->loadTemplate('symfony/docker-compose.yml');
        echo $template->render(array('the' => 'variables', 'go' => 'here'));
    }

}