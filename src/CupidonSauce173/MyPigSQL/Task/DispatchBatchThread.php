<?php

namespace CupidonSauce173\MyPigSQL\Task;

use CupidonSauce173\MyPigSQL\Utils\SQLConnString;
use CupidonSauce173\MyPigSQL\Utils\SQLRequest;
use CupidonSauce173\MyPigSQL\Utils\SQLRequestException;
use mysqli;
use Thread;
use Volatile;
use function in_array;

class DispatchBatchThread extends Thread
{
    const MAIN_THREAD = 0;
    const HELP_THREAD = 1;

    private int $executionInterval = 2;
    private Volatile $container;
    private int $type;
    private int $batch = 0;

    /**
     * @param Volatile $container
     * @param int $type
     * @param int|null $batch
     */
    public function __construct(Volatile $container, int $type, ?int $batch = null)
    {
        if ($batch !== null) {
            $this->batch = $batch;
        }
        $this->type = $type;
        $this->container = $container;
    }

    /**
     * @throws SQLRequestException
     */
    public function run(): void
    {
        $nextTime = microtime(true) + 1;

        if ($this->type === self::MAIN_THREAD) {
            while ($this->container['runThread'][self::MAIN_THREAD]) {
                if (microtime(true) >= $nextTime) {
                    $nextTime = microtime(true) + $this->executionInterval;
                    $this->processThread();
                }
            }
        } else {
            $this->processThread();
        }

        $this->cleanupThread();
    }

    /**
     * @throws SQLRequestException
     */
    private function processThread(): void
    {
        $connections = [];
        $queryContainers = [];

        foreach ($this->container['batch'][$this->batch] as $id => $serialized) {
            $query = $serialized;
            if (!$query instanceof SQLRequest) {
                throw new SQLRequestException('Error while processing a SQLRequest');
            }
            $connString = $query->getConnString();
            if (!$connString instanceof SQLConnString) {
                throw new SQLRequestException('Error while processing a SQLConnString');
            }
            if (!isset($queryContainers[$connString->getName()])) {
                $queryContainers[$connString->getName()] = [];
                $connections[$connString->getName()] = $this->createConnection($connString);
            }
            if (isset($queryContainers[$connString->getName()][$query->getId()])) continue;
            $queryContainers[$connString->getName()][$query->getId()] = $query;
            unset($this->container['batch'][$this->batch][$id]);
        }

        foreach ($queryContainers as $databaseQueries) {
            foreach ($databaseQueries as $query) {
                if (in_array($query->getId(), (array)$this->container['executedRequests'])) {
                    continue;
                }
                $this->container['executedRequests'][] = $query->getId();
                $stmt = $connections[$query->getConnString()->getName()]->prepare($query->getQuery());
                $stmt->bind_param($query->getDataTypes(), ...$query->getDataKeys());
                $stmt->execute();
                if ($stmt->error_list) {
                    throw new SQLRequestException($stmt->error);
                }
                $results = $stmt->get_result();
                $data = $results !== false ? $results->fetch_assoc() : false;
                $this->container['callbackResults'][$query->getId()] = $data;
                $stmt->close();
                unset($queryContainers[$query->getConnString()->getName()][$query->getId()]);
            }
        }
    }

    /**
     * Creates a SQL connection
     * @param SQLConnString $connString
     * @return mysqli
     */
    private function createConnection(SQLConnString $connString): mysqli
    {
        return new mysqli(
            $connString->getAddress(),
            $connString->getUsername(),
            $connString->getPassword(),
            $connString->getDatabase(),
            $connString->getPort()
        );
    }

    private function cleanupThread(): void
    {
        if (isset($this->container['runThread'][self::HELP_THREAD][$this->getThreadId()])) {
            unset($this->container['runThread'][self::HELP_THREAD][$this->getThreadId()]);
        }
    }

    /**
     * @param int $seconds
     */
    public function setExecutionInterval(int $seconds): void
    {
        $this->executionInterval = $seconds;
    }

    public function setBatchToExecute(int $batch): void
    {
        $this->batch = $batch;
    }
}
