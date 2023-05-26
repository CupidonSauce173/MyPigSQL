<?php

namespace CupidonSauce173\MyPigSQL\Utils;

use function uniqid;

class SQLRequest
{
    private string $query = '';
    private null|string $dataTypes = null;
    private null|array $dataKeys = null;
    private null|SQLConnString $connString = null;
    private bool $dispatched;
    private bool $completed;
    private string $id;
    private int $batch = 0;

    /** @var null|callable $callable */
    private $callable = null;

    public function __construct()
    {
        $this->id = uniqid();
    }

    /**
     * Will create a new Utils object.
     * @param string $query
     * @param string $dataTypes
     * @param array $dataKeys
     * @param SQLConnString $connString
     * @param null|callable $callback
     * @param int $batch
     * @return SQLRequest
     * @throws SQLRequestException
     */
    public static function create(string $query, string $dataTypes, array $dataKeys, SQLConnString $connString, null|callable $callback = null, int $batch = 0): self
    {
        $result = new self();
        if (empty($query)) {
            throw new SQLRequestException("You cannot set an empty query in a Utils.");
        }
        $result->setQuery($query);
        $result->setDataTypes($dataTypes);
        $result->setDataKeys($dataKeys);
        $result->setConnString($connString);
        $result->setCallable($callback);
        $result->setBatch();
        $result->setDispatched(false);
        $result->setCompleted(false);
        return $result;
    }

    /**
     * To set if the request has been executed.
     * @param bool $value
     */
    public function setDispatched(bool $value = true): void
    {
        $this->dispatched = $value;
    }

    /**
     * To set if it has been completed.
     * @param bool $value
     */
    public function setCompleted(bool $value = true): void
    {
        $this->completed = $value;
    }

    /**
     * Returns in which batch this request will be executed.
     * @return int
     */
    public function getBatch(): int
    {
        return $this->batch;
    }

    /**
     * To set in which batch this request will be executed.
     * @param int $batchNumber
     */
    public function setBatch(int $batchNumber = 0): void
    {
        $this->batch = $batchNumber;
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
     * @return SQLRequest
     */
    public function setCallable(?callable $callback): self
    {
        $this->callable = $callback;
        return $this;
    }

    /**
     * Returns if the request has been dispatched to the DispatchBatchThread
     * @return bool
     */
    public function hasBeenDispatched(): bool
    {
        return $this->dispatched;
    }

    /**
     * Returns if the request has been completed
     * @return bool
     */
    public function hasBeenCompleted(): bool
    {
        return $this->completed;
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
     * @return SQLRequest
     * @throws SQLRequestException
     */
    public function setQuery(string $query): self
    {
        if (empty($query)) {
            throw new SQLRequestException('You cannot set an empty query in Utils.');
        }
        $this->query = $query;
        return $this;
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
     * @return SQLRequest
     */
    public function setDataTypes(string $dataTypes): self
    {
        $this->dataTypes = $dataTypes;
        return $this;
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
     * @return SQLRequest
     */
    public function setDataKeys(array $dataKeys): self
    {
        $this->dataKeys = $dataKeys;
        return $this;
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
     * @return SQLRequest
     */
    public function setConnString(SQLConnString $connString): self
    {
        $this->connString = $connString;
        return $this;
    }

    public function register(): void
    {

    }
}