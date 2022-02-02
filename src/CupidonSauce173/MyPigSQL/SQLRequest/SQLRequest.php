<?php

namespace CupidonSauce173\MyPigSQL\SQLRequest;

class SQLRequest
{
    private null|string $query = null;
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
        $result->setQuery($query);
        $result->setDataTypes($dataTypes);
        $result->setDataKeys($dataKeys);
        $result->setConnString($connString);
        $result->setCallable($callback);
        return $result;
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
     * @return callable|null
     */
    public function getCallable(): ?callable
    {
        return $this->callable;
    }

    /**
     * Returns if the request has been executed or to set if the request has been executed.
     * @param bool $value
     * @return bool
     */
    public function hasBeenExecuted(bool $value = false): bool
    {
        if($value){
            $this->validated = $value;
            return $this->validated;
        }
        return $this->validated;
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
     * To set the data types of the query. (OPTIONAL)
     * @param string $dataTypes
     */
    public function setDataTypes(string $dataTypes): void
    {
        $this->dataTypes = $dataTypes;
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
     * To set the database of the query. (NEEDED)
     * @param SQLConnString $connString
     */
    public function setConnString(SQLConnString $connString): void
    {
        $this->connString = $connString;
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
     * Returns data types.
     * @return string
     */
    public function getDataTypes(): string
    {
        return $this->dataTypes;
    }

    /**
     * Returns data keys.
     * @return array
     */
    public function getDataKeys(): array
    {
        return $this->dataKeys;
    }

    /**
     * Returns database target.
     * @return SQLConnString
     */
    public function getConnString(): SQLConnString
    {
        return $this->connString;
    }
}