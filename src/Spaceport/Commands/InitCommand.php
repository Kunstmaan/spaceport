<?php

namespace Spaceport\Commands;

use Spaceport\Helpers\Sf4InitInitHelper;
use Spaceport\Helpers\SfInitHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends AbstractCommand
{
    public CONST SUPPORTED_PHP_VERSIONS = ['7.1', '7.4', '8.0', self::DEFAULT_PHP_VERSION];
    public CONST DEFAULT_PHP_VERSION = '8.1';
    public CONST SUPPORTED_ELASTICSEARCH_VERSIONS = [self::DEFAULT_ELASTICSEARCH_VERSION];
    public CONST DEFAULT_ELASTICSEARCH_VERSION = '7.16.2';
    public CONST SUPPORTED_MYSQL_VERSIONS = [self::DEFAULT_MYSQL_VERSION];
    public CONST DEFAULT_MYSQL_VERSION = '5.7';

    private ?SfInitHelper $initHelper = null;

    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Initialize the project to run with docker')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force the regeneration of the docker files');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $this->initHelper = new Sf4InitInitHelper($input, $output);

        if (!$this->isDockerized(true) || $input->getOption('force')) {
            $this->writeDockerComposeFile();
            $this->writeConfigDockerFile();
        }
        $this->logStep("dockerizing App");
        $this->initHelper->dockerizeApp($this->shuttle);

        $this->logSuccess("You can now run `spaceport start` to run the development environment");
        $this->io->newLine();

        return 0;
    }

    private function writeDockerComposeFile(): void
    {
        $this->askMysqlVersion();
        $this->initHelper->findMySQLSettings($this->shuttle);
        $this->findPHPSettings();
        $this->askElasticVersion();
        $this->logStep('Generating the docker-compose file');

        $this->twig->renderAndWriteTemplate('symfony/' . $this->getDockerComposeFileName() . '.twig', $this->getDockerComposeFileName(), ['shuttle' => $this->shuttle]);
    }

    private function findPHPSettings($ask = true): array
    {
        $php = [];
        if ($ask) {
            $this->shuttle->setPhpVersion($this->io->choice('What version of PHP do you need?', self::SUPPORTED_PHP_VERSIONS, self::DEFAULT_PHP_VERSION));
        }

        return $php;
    }

    private function askMysqlVersion($ask = true): void
    {
        if ($ask) {
            $this->shuttle->setMysqlVersion($this->choice('What version of Mysql do you need?', self::SUPPORTED_MYSQL_VERSIONS, self::DEFAULT_MYSQL_VERSION));
        }
    }

    private function askElasticVersion($ask = true): void
    {
        if ($ask) {
            $answer = $this->io->choice("Do we require elasticsearch?", ['yes', 'no'], 'yes');
            if ($answer === "yes") {
                $this->shuttle->setElasticsearchVersion($this->choice('What version of Elasticsearch do you need?', self::SUPPORTED_ELASTICSEARCH_VERSIONS, self::DEFAULT_ELASTICSEARCH_VERSION));
            }
        }
    }

    private function writeConfigDockerFile(): void
    {
        $configDockerFilePath = $this->initHelper->getConfigDockerFilePath();
        $this->logStep('Generating the ' . $configDockerFilePath . ' file');
        $this->twig->renderAndWriteTemplate($this->initHelper->getTwigTemplateNameConfigDockerFile(), $configDockerFilePath, ['shuttle' => $this->shuttle]);
    }

    private function choice($question, array $choices, $default = null) {
        if (count($choices) === 1) {
            $this->logStep("Only one choice avaiable. No need to ask the choiceQuestion: " . $question);

            return $default ?: $choices[0];
        }

        return $this->io->choice($question, $choices, $default);
    }
}
