<?php

namespace CupidonSauce173\MyPigSQL\SQLRequest;

use CupidonSauce173\MyPigSQL\MyPigSQL;

class SQLConnString
{
    private null|string $connName = null;
    private null|string $address = null;
    private null|string $username = null;
    private null|string $password = null;
    private null|string $database = null;
    private int $port = 3306;

    /**
     * @param string $connName
     * @param string $address
     * @param string $username
     * @param string $password
     * @param string $database
     * @param null|int $port
     * @param bool $validate
     * @return static
     * @generate create-func
     */
    public static function create(string $connName, string $address, string $username, string $password, string $database, null|int $port = null, bool $validate = true): self
    {
        $conn = new self();
        $conn->setName($connName);
        $conn->setAddress($address);
        $conn->setUsername($username);
        $conn->setPassword($password);
        $conn->setDatabase($database);
        if($port == null) return $conn;
        $conn->setPort($port);
        if($validate){
            MyPigSQL::validateConnString([
                'address' => $address,
                'username' => $username,
                'password' => $password,
                'database' => $database,
                'port' => $port
            ]);
        }
        return $conn;
    }

    public function validate(): void
    {
        MyPigSQL::validateConnString([
            'address' => $this->address,
            'username' => $this->username,
            'password' => $this->password,
            'database' => $this->database,
            'port' => $this->port
        ]);
    }

    public function setDatabase(string $database): void
    {
        $this->database = $database;
    }

    public function setName(string $connName): void
    {
        $this->connName = $connName;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    public function getDatabase(): ?string
    {
        return $this->database;
    }

    public function getName(): ?string
    {
        return $this->connName;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPort(): int
    {
        return $this->port;
    }
}