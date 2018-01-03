<?php

namespace Spaceport\Model;

class Shuttle
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $server;

    /**
     * @var string
     */
    private $apacheVhost;

    /**
     * @var DatabaseConnection[]
     */
    private $databases;

    /**
     * @var string
     */
    private $apacheDocumentRoot;

    /**
     * @var string
     */
    private $apacheFallbackDomain;

    /**
     * @var string
     */
    private $phpVersion;

    /**
     * @var string
     */
    private $elasticsearchVersion;

    /**
     * @var string
     */
    private $nodeVersion;

    /**
     * @var bool
     */
    private $runSync;
    
    const DOCKER_EXT = '.dev.kunstmaan.be';

    public function __construct()
    {
        $this->name = basename(getcwd());
        $this->apacheVhost = $this->name . self::DOCKER_EXT;
        $this->apacheDocumentRoot = "/app/web/";
        $this->runSync = false;
        $this->databases = [];
    }
    
    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getServer()
    {
        return null !== $this->server ? $this->server : '';
    }

    /**
     * @param bool|string $server
     */
    public function setServer($server)
    {
        $this->server = $server;
    }

    /**
     * @return bool
     */
    public function hasServer()
    {
        return null !== $this->server;
    }

    /**
     * @return string
     */
    public function getApacheVhost()
    {
        return $this->apacheVhost;
    }

    /**
     * @param string $apacheVhost
     */
    public function setApacheVhost($apacheVhost)
    {
        $this->apacheVhost = $apacheVhost;
    }

    /**
     * @return string
     */
    public function getApacheDocumentRoot()
    {
        return $this->apacheDocumentRoot;
    }

    /**
     * @param string $apacheDocumentRoot
     */
    public function setApacheDocumentRoot($apacheDocumentRoot)
    {
        $this->apacheDocumentRoot = $apacheDocumentRoot;
    }

    /**
     * @return bool|string
     */
    public function getApacheFallbackDomain()
    {
        return $this->apacheFallbackDomain;
    }

    /**
     * @param bool|string $apacheFallbackDomain
     */
    public function setApacheFallbackDomain($apacheFallbackDomain)
    {
        $this->apacheFallbackDomain = $apacheFallbackDomain;
    }

    /**
     * @return string
     */
    public function getPhpVersion()
    {
        return $this->phpVersion;
    }

    /**
     * @param string $phpVersion
     */
    public function setPhpVersion($phpVersion)
    {
        $this->phpVersion = $phpVersion;
    }

    /**
     * @return string
     */
    public function getElasticsearchVersion()
    {
        return $this->elasticsearchVersion;
    }

    /**
     * @param string $elasticsearchVersion
     * @return Shuttle
     */
    public function setElasticsearchVersion($elasticsearchVersion)
    {
        $this->elasticsearchVersion = $elasticsearchVersion;

        return $this;
    }

    /**
     * @return boolean
     */
    public function shouldRunSync()
    {
        return $this->runSync;
    }

    /**
     * @param boolean $runSync
     */
    public function setRunSync($runSync)
    {
        $this->runSync = $runSync;
    }

    /**
     * @return string
     */
    public function getNodeVersion()
    {
        return $this->nodeVersion;
    }

    /**
     * @param string $nodeVersion
     * @return $this
     */
    public function setNodeVersion($nodeVersion)
    {
        $this->nodeVersion = $nodeVersion;

        return $this;
    }

    /**
     * @return DatabaseConnection[]
     */
    public function getDatabases()
    {
        return $this->databases;
    }

    /**
     * @param DatabaseConnection[] $databases
     * @return $this
     */
    public function setDatabases($databases)
    {
        $this->databases = $databases;

        return $this;
    }


    /**
     * @param DatabaseConnection $connection
     */
    public function addDatabaseConnection(DatabaseConnection $connection)
    {
       if(null !== $connection) {
           $this->databases[] = $connection;
       }
    }

    /**
     * @param DatabaseConnection $connection
     */
    public function removeDatabaseConnection(DatabaseConnection $connection)
    {
        if($index = array_search($connection, $this->databases, true)) {
            array_splice($this->databases, $index, 1);
        }
    }
}
