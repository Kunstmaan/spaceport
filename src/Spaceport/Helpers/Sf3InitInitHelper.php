<?php

namespace Spaceport\Helpers;


use Symfony\Component\Yaml\Parser;

class Sf3InitInitHelper extends SfInitHelper
{

    public function getConfigDockerFilePath()
    {
        return "app/config/config_docker.yml";
    }

    public function getTwigTemplateNameConfigDockerFile()
    {
        return "symfony/config_docker_sf3.yml.twig";
    }

    public function getDatabaseSettings()
    {
        $yaml = new Parser();
        $databaseSettings = [];
        $parameters = $yaml->parse(file_get_contents("app/config/parameters.yml"));
        if (array_key_exists('database_name', $parameters['parameters']) &&
            array_key_exists('database_user', $parameters['parameters']) &&
            array_key_exists('database_password', $parameters['parameters'])) {
            $databaseSettings["database_name"] = $parameters["parameters"]["database_name"];
            $databaseSettings["database_user"] = $parameters["parameters"]["database_user"];
            $databaseSettings["database_password"] = $parameters["parameters"]["database_password"];
        }

        return $databaseSettings;
    }

    public function getApacheDocumentRoot()
    {
        return "web";
    }

    public function dockerizeApp()
    {
        $this->checkAppFile();
        $this->checkAppKernelFile();
    }

    private function checkAppFile()
    {
        $this->logStep('Checking if the web/app.php file is setup for Docker');

        if (file_exists("web/app.php")) {
            $file = \file("web/app.php");

            foreach ($file as $key => $line) {
                if (preg_match("/getenv\('APP_ENV'\) === ['|\"]dev['|\"](.*)/", $line, $matches) && strpos($line, "docker") === false) {
                    $match = $matches[0];
                    $matchReplace = substr_replace($match, "|| getenv('APP_ENV') === 'docker'" . substr($match, -3), -3);
                    $file[$key] = str_replace($match, $matchReplace, $file[$key]);
                }
                if (preg_match("/getenv\('APP_ENV'\) !== ['|\"]dev['|\"](.*)/", $line, $matches) && strpos($line, "docker") === false) {
                    $match = $matches[0];
                    $matchReplace = substr_replace($match, "|| getenv('APP_ENV') !== 'docker'" . substr($match, -3), -3);
                    $file[$key] = str_replace($match, $matchReplace, $file[$key]);
                }
            }

            file_put_contents("web/app.php", implode("", $file));
        }
    }

    private function checkAppKernelFile()
    {
        $this->logStep('Checking if the app/AppKernel.php file is setup for Docker');

        require getcwd().'/app/autoload.php';
        include_once getcwd().'/var/bootstrap.php.cache';

        if (file_exists("app/AppKernel.php")) {
            $file = $this->writeRegisterBundles(\file("app/AppKernel.php"));
            $file = $this->writeLogDir($file);
            $file = $this->writeCacheDir($file);

            file_put_contents("app/AppKernel.php", implode("", $file));
        }
    }

    private function writeRegisterBundles(array $file) {
        $method = new \ReflectionMethod('AppKernel', 'registerBundles');
        $slice = array_slice($file, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);
        foreach ($slice as $key => $line) {
            if (strpos($line, "in_array(\$this->getEnvironment()") && strpos($line, "docker") === false) {
                //Match the part array('dev') or ['dev'] either with single or double quotes until the first ) or ]
                if (preg_match("/array\([\'|\"].*?\)|\[[\'|\"].*?\]/", $line, $matches)) {
                    $match = $matches[0];
                    $matchReplace = substr_replace($match, ", 'docker'" . substr($match, -1), -1);
                    $slice[$key] = str_replace($match, $matchReplace, $slice[$key]);
                }
            }
        }
        array_splice($file, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1, $slice);

        return $file;
    }

    private function writeLogDir(array $file)
    {
        return $this->writeDir("AppKernel", "getLogDir", $file, "/tmp/symfony/var/logs/");
    }

    private function writeCacheDir(array $file)
    {
        return $this->writeDir("AppKernel", "getCacheDir", $file, "/tmp/symfony/var/cache/");
    }

}