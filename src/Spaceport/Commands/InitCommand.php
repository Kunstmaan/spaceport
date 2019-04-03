<?php

namespace Spaceport\Commands;

use Spaceport\Helpers\Sf3InitInitHelper;
use Spaceport\Helpers\Sf4InitInitHelper;
use Spaceport\Helpers\SfInitHelper;
use Spaceport\Model\Shuttle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class InitCommand extends AbstractCommand
{

    /** @var SfInitHelper $initHelper*/
    private $initHelper;

    protected function configure()
    {
        $this
            ->setName('init')
            ->setDescription('Initialize the project to run with docker')
            ->addOption('force', null, InputOption::VALUE_OPTIONAL, 'Force the regeneration of the docker files');
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function doExecute(InputInterface $input, OutputInterface $output)
    {
        $symfonyVersion = $this->io->choice('What version of Symfony do you need?', ['3', '4'], '3');
        if ($symfonyVersion == "3") {
            $this->initHelper = new Sf3InitInitHelper($input, $output);
        } else if ($symfonyVersion == "4") {
            $this->initHelper = new Sf4InitInitHelper($input, $output);
        }

        if (!$this->isDockerized(true) || $input->getOption('force')) {
            $this->writeDockerComposeFile();
            $this->writeConfigDockerFile();
        }
        $this->logStep("dockerizing App");
        $this->initHelper->dockerizeApp($this->io);
        $this->setDinghySSLCerts();

        $this->logSuccess("You can now run `spaceport start` to run the development environment");
        $this->io->newLine();
    }

    private function writeDockerComposeFile()
    {
        $this->initHelper->findMySQLSettings($this->shuttle);
        $this->findApacheSettings();
        $this->findPHPSettings();
        $this->askElasticVersion();
        $this->logStep('Generating the docker-compose file');

        $this->twig->renderAndWriteTemplate('symfony/' . $this->getDockerComposeFileName() . '.twig', $this->getDockerComposeFileName(), ['shuttle' => $this->shuttle]);
    }

    private function findApacheSettings()
    {
        $this->shuttle->setApacheDocumentRoot("/app/" . $this->initHelper->getApacheDocumentRoot());
        $question = new Question('What server should be used as the fallback domain ? (Can be left empty)', '/');
        $fallbackDomain = $this->io->askQuestion($question);
        $fallbackDomain = preg_replace('#^https?://#', '', rtrim($fallbackDomain, '/'));
        $this->shuttle->setApacheFallbackDomain($fallbackDomain);
        $this->shuttle->setApacheVhost($this->shuttle->getName() . Shuttle::DOCKER_EXT);
    }

    private function findPHPSettings($ask = true)
    {
        $php = [];
        if ($ask) {
            $this->shuttle->setPhpVersion($this->io->choice('What version of PHP do you need?', ['7.2', '7.1'], '7.2'));
        }

        return $php;
    }

    private function askElasticVersion($ask = true)
    {
        if ($ask) {
            $this->shuttle->setElasticsearchVersion($this->io->choice('What version of Elasticsearch do you need?', ['6'], '6'));
        }
    }

    private function writeConfigDockerFile()
    {
        $configDockerFilePath = $this->initHelper->getConfigDockerFilePath();
        $this->logStep('Generating the ' . $configDockerFilePath . ' file');
        $this->twig->renderAndWriteTemplate($this->initHelper->getTwigTemplateNameConfigDockerFile(), $configDockerFilePath, ['shuttle' => $this->shuttle]);
    }

}
