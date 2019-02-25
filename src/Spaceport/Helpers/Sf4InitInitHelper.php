<?php

namespace Spaceport\Helpers;


class Sf4InitInitHelper extends SfInitHelper
{

    public function getConfigDockerFilePath()
    {
        return "config/packages/docker/config.yaml";
    }

    public function getTwigTemplateNameConfigDockerFile()
    {
        return "symfony/config_docker_sf4.yaml.twig";
    }

    public function getDatabaseSettings()
    {
        return [];
    }

    public function getApacheDocumentRoot()
    {
        return "public";
    }

    /**
     * Method where you make changes to the app to make it docker ready.
     * E.g. Change app.php (sf3) or config/bundles.php (sf4)
     */
    public function dockerizeApp()
    {
        $this->checkBundlesFile();
        $this->checkKernelFile();
    }

    private function checkBundlesFile()
    {
        $this->logStep('Checking if the config/bundles.php file is setup for Docker');

        require getcwd().'/vendor/autoload.php';

        if (file_exists("config/bundles.php")) {
            $file = \file("config/bundles.php");

            foreach ($file as $key => $line) {
                if (preg_match("/\[[\'|\"]dev.*?\]/", $line, $matches) && strpos($line, "docker") === false) {
                    $match = $matches[0];
                    $matchReplace = substr_replace($match, ", 'docker' => true" . substr($match, -1), -1);
                    $file[$key] = str_replace($match, $matchReplace, $file[$key]);
                }
            }

            file_put_contents("config/bundles.php", implode("", $file));
        }
    }

    private function checkKernelFile()
    {
        $this->logStep('Checking if the src/Kernel.php file is setup for Docker');

        require getcwd().'/vendor/autoload.php';

        if (file_exists("src/Kernel.php")) {
            $file = $this->writeLogDir(\file("src/Kernel.php"));
            $file = $this->writeCacheDir($file);

            file_put_contents("src/Kernel.php", implode("", $file));
        }
    }

    private function writeLogDir(array $file)
    {
        return $this->writeDir("App\Kernel", "getLogDir", $file,"/tmp/symfony/var/logs/");
    }

    private function writeCacheDir(array $file)
    {
        return $this->writeDir("App\Kernel", "getCacheDir", $file,"/tmp/symfony/var/cache/");
    }
}