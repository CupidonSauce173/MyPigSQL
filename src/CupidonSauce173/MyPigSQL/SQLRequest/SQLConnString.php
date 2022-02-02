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
        if ($port == null) return $conn;
        $conn->setPort($port);
        if ($validate) {
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

    /**
     * Set the name of the SQLConnString.
     * @param string $connName
     */
    public function setName(string $connName): void
    {
        $this->connName = $connName;
    }

    /**
     * Will validate the connection info by trying to connect to the database.
     */
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

    /**
     * Returns the database used for the request.
     * @return string|null
     */
    public function getDatabase(): ?string
    {
        return $this->database;
    }

    /**
     * Set the database used.
     * @param string $database
     */
    public function setDatabase(string $database): void
    {
        $this->database = $database;
    }

    /**
     * Returns the name of the SQLConnString.
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->connName;
    }

    /**
     * Returns the host/address server.
     * @return string|null
     */
    public function getAddress(): ?string
    {
        return $this->address;
    }

    /**
     * Set the host/address server.
     * @param string $address
     */
    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    /**
     * Returns the username for the connection.
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * Set the username for the connection.
     * @param string $username
     */
    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    /**
     * Returns the password for the user.
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Set the password for the user.
     * @param string $password
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * Returns the port used for the connection.
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Set the port of the server, this is optional, the default value is 3306
     * @param int $port
     */
    public function setPort(int $port): void
    {
        $this->port = $port;
    }
}