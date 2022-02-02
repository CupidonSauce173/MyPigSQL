<?php

namespace CupidonSauce173\MyPigSQL\Task;

use CupidonSauce173\MyPigSQL\SQLRequest\SQLRequest;
use CupidonSauce173\MyPigSQL\SQLRequest\SQLRequestException;
use mysqli;
use Thread;
use Volatile;
use function microtime;

class DispatchBatchThread extends Thread
{
    /** @var SQLRequest[] $queryContainers */
    private array $queryContainers = []; # Categorized queries.
    private int $executionInterval = 2;
    private Volatile $container;

    /**
     * @param Volatile $container
     */
    public function __construct(Volatile $container)
    {
        $this->container = $container;
    }

    /**
     * @throws SQLRequestException
     */
    public function run(): void
    {
        $nextTime = microtime(true) + 1;
        while ($this->container['runThread']) {
            if (microtime(true) >= $nextTime) {
                $this->ProcessThread();
                $nextTime = microtime(true) + $this->executionInterval;
            }
        }
    }

    /**
     * @throws SQLRequestException
     */
    private function processThread(): void
    {
        # Categorize queries into connStrings & creating MySQL connections.
        /** @var mysqli[] $connections */
        $connections = [];
        /** @var SQLRequest $query */
        foreach ($this->container['batch'] as $query) {
            $connString = $query->getConnString();
            if (!isset($this->queryContainers[$connString->getName()])) {
                $this->queryContainers[$connString->getName()] = [];
            }
            $conn = new mysqli(
                $connString->getAddress(),
                $connString->getUsername(),
                $connString->getPassword(),
                $connString->getDatabase(),
                $connString->getPort()
            );
            $connections[$connString->getName()] = $conn;

            if (isset($this->queryContainers[$connString->getName()][$query->getId()])) return;
            $this->queryContainers[$connString->getName()][$query->getId()] = $query;
        }
        # Process all queries
        foreach ((array)$this->queryContainers as $databaseQueries) {
            /** @var SQLRequest $query */
            foreach ($databaseQueries as $query) {
                if (isset($this->container['executedRequests'][$query->getId()])) return;
                $this->container['executedRequests'][] = $query->getId();
                $stmt = $connections[$query->getConnString()->getName()]->prepare($query->getQuery());
                $stmt->bind_param($query->getDataTypes(), ...$query->getDataKeys());
                $stmt->execute();
                if ($stmt->error_list) {
                    throw new SQLRequestException($stmt->error);
                }
                $data = $stmt->get_result()->fetch_assoc();
                $this->container['callbackResults'][$query->getId()] = $data;
                $stmt->close();
            }
        }
    }

    /**
     * @param int $seconds
     */
    public function setExecutionInterval(int $seconds): void
    {
        $this->executionInterval = $seconds;
    }
}