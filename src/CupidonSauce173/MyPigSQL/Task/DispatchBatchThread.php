<?php

namespace CupidonSauce173\MyPigSQL\Task;

use CupidonSauce173\MyPigSQL\Utils\SQLRequest;
use CupidonSauce173\MyPigSQL\Utils\SQLRequestException;
use mysqli;
use Thread;
use Volatile;
use function microtime;

class DispatchBatchThread extends Thread
{
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
        include($this->container['folder'] . "/Utils/SQLRequest.php");
        include($this->container['folder'] . "/Utils/SQLConnString.php");
        include($this->container['folder'] . "/Utils/SQLRequestException.php");

        $nextTime = microtime(true) + 1;
        while ($this->container['runThread']) {
            if (microtime(true) >= $nextTime) {
                # Categorize queries into connStrings & creating MySQL connections.
                /** @var mysqli[] $connections */
                $connections = [];
                /** @var SQLRequest $query */
                foreach ($this->container['batch'] as $query) {
                    if(!$query instanceof SQLRequest) throw new SQLRequestException('Error while processing a SQLRequest');
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
                        $results = $stmt->get_result();
                        if($results !== false) {
                            $data = $results->fetch_assoc();
                            $this->container['callbackResults'][$query->getId()] = $data;
                        }
                        $stmt->close();
                    }
                }
                $nextTime = microtime(true) + $this->executionInterval;
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