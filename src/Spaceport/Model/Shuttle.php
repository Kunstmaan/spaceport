<?php

/**
 * Created by PhpStorm.
 * User: Ruud Denivel <ruud.denivel@kunstmaan.be>
 * Date: 24/04/16
 * Time: 10:40
 */

namespace Spaceport\Model;

class Shuttle
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string|bool
     */
    private $server = false;

    /**
     * @var string
     */
    private $mysqlDatabase;

    /**
     * @var string
     */
    private $mysqlUser;

    /**
     * @var string
     */
    private $mysqlPassword;

    /**
     * @var string
     */
    private $apacheVhost;

    /**
     * @var string
     */
    private $apacheWebroot;

    /**
     * @var string|bool
     */
    private $apacheFallbackDomain;

    /**
     * @var string
     */
    private $phpVersion;

    /**
     * @var bool
     */
    private $runSync;
    
    const DOCKER_EXT = '.docker';

    public function __construct()
    {
        $this->name = basename(getcwd());
        $this->server = false;
        $this->apacheVhost = basename(getcwd()) . self::DOCKER_EXT;
        $this->apacheWebroot = "web/";
        $this->apacheFallbackDomain = false;
        $this->runSync = false;
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
     * @return bool|string
     */
    public function getServer()
    {
        return $this->server;
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
        return $this->server !== false;
    }

    /**
     * @return string
     */
    public function getMysqlDatabase()
    {
        return $this->mysqlDatabase;
    }

    /**
     * @param string $mysqlDatabase
     */
    public function setMysqlDatabase($mysqlDatabase)
    {
        $this->mysqlDatabase = $mysqlDatabase;
    }

    /**
     * @return string
     */
    public function getMysqlUser()
    {
        return $this->mysqlUser;
    }

    /**
     * @param string $mysqlUser
     */
    public function setMysqlUser($mysqlUser)
    {
        $this->mysqlUser = $mysqlUser;
    }

    /**
     * @return string
     */
    public function getMysqlPassword()
    {
        return $this->mysqlPassword;
    }

    /**
     * @param string $mysqlPassword
     */
    public function setMysqlPassword($mysqlPassword)
    {
        $this->mysqlPassword = $mysqlPassword;
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
    public function getApacheWebroot()
    {
        return $this->apacheWebroot;
    }

    /**
     * @param string $apacheWebroot
     */
    public function setApacheWebroot($apacheWebroot)
    {
        $this->apacheWebroot = $apacheWebroot;
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
}
