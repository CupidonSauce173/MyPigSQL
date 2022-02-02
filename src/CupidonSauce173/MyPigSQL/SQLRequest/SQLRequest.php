<?php

namespace CupidonSauce173\MyPigSQL\SQLRequest;

use function uniqid;

class SQLRequest
{
    private string $query = '';
    private null|string $dataTypes = null;
    private null|array $dataKeys = null;
    private null|SQLConnString $connString = null;
    private bool $validated = false;
    private string $id;

    /** @var null|callable $callable */
    private $callable = null;

    public function __construct()
    {
        $this->id = uniqid();
    }

    /**
     * Will create a new SQLRequest object.
     * @param string $query
     * @param string $dataTypes
     * @param array $dataKeys
     * @param SQLConnString $connString
     * @param null|callable $callback
     * @return static
     * @throws SQLRequestException
     */
    public static function create(string $query, string $dataTypes, array $dataKeys, SQLConnString $connString, null|callable $callback = null): self
    {
        $result = new self();
        if (empty($query)) {
            throw new SQLRequestException("You cannot set an empty query in a SQLRequest.");
        }
        $result->setQuery($query);
        $result->setDataTypes($dataTypes);
        $result->setDataKeys($dataKeys);
        $result->setConnString($connString);
        $result->setCallable($callback);
        return $result;
    }

    /**
     * Returns the anonymous function attached to the request.
     * @return callable|null
     */
    public function getCallable(): ?callable
    {
        return $this->callable;
    }

    /**
     * Set callable function.
     * @param callable|null $callback
     */
    public function setCallable(?callable $callback): void
    {
        $this->callable = $callback;
    }

    /**
     * Returns if the request has been executed or to set if the request has been executed.
     * @param bool $value
     * @return bool
     */
    public function hasBeenExecuted(bool $value = false): bool
    {
        if ($value) {
            $this->validated = $value;
            return $this->validated;
        }
        return $this->validated;
    }

    /**
     * Returns the id of the request.
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns unconverted query string.
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * To set the query of the request. (NEEDED)
     * @param string $query
     * @return bool
     * @throws SQLRequestException
     */
    public function setQuery(string $query): bool
    {
        if (empty($query)) {
            throw new SQLRequestException('You cannot set an empty query in SQLRequest.');
        }
        $this->query = $query;
        return true;
    }

    /**
     * Returns data types.
     * @return string|null
     */
    public function getDataTypes(): ?string
    {
        return $this->dataTypes;
    }

    /**
     * To set the data types of the query. (OPTIONAL)
     * @param string $dataTypes
     */
    public function setDataTypes(string $dataTypes): void
    {
        $this->dataTypes = $dataTypes;
    }

    /**
     * Returns data keys.
     * @return array|null
     */
    public function getDataKeys(): ?array
    {
        return $this->dataKeys;
    }

    /**
     * To set the dataKeys of the query. (OPTIONAL)
     * @param array $dataKeys
     */
    public function setDataKeys(array $dataKeys): void
    {
        $this->dataKeys = $dataKeys;
    }

    /**
     * Returns the SQLConnString attached to this request.
     * @return SQLConnString|null
     */
    public function getConnString(): ?SQLConnString
    {
        return $this->connString;
    }

    /**
     * To set the database of the query. (NEEDED)
     * @param SQLConnString $connString
     */
    public function setConnString(SQLConnString $connString): void
    {
        $this->connString = $connString;
    }
}