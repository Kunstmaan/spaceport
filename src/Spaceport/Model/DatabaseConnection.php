<?php

namespace Spaceport\Model;

class DatabaseConnection
{
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
    private $mysqlPort;

    /**
     * @return string
     */
    public function getMysqlDatabase()
    {
        return $this->mysqlDatabase;
    }

    /**
     * @param string $mysqlDatabase
     * @return $this
     */
    public function setMysqlDatabase($mysqlDatabase)
    {
        $this->mysqlDatabase = $mysqlDatabase;

        return $this;
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
     * @return $this
     */
    public function setMysqlUser($mysqlUser)
    {
        $this->mysqlUser = $mysqlUser;

        return $this;
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
     * @return $this
     */
    public function setMysqlPassword($mysqlPassword)
    {
        $this->mysqlPassword = $mysqlPassword;

        return $this;
    }

    /**
     * @return string
     */
    public function getMysqlPort()
    {
        return $this->mysqlPort;
    }

    /**
     * @param string $mysqlPort
     * @return $this
     */
    public function setMysqlPort($mysqlPort)
    {
        $this->mysqlPort = $mysqlPort;

        return $this;
    }
}