<?php

namespace CupidonSauce173\MyPigSQL\Task;

use CupidonSauce173\MyPigSQL\Utils\SQLConnString;
use CupidonSauce173\MyPigSQL\Utils\SQLRequest;
use CupidonSauce173\MyPigSQL\Utils\SQLRequestException;
use mysqli;
use Thread;
use Volatile;
use function microtime;

class DispatchBatchThread extends Thread
{
    private int $executionInterval = 2;
    private Volatile $container;

    # Since 2.0.0
    private int $type;
    private int $batch = 0;
    const MAIN_THREAD = 0; # Is the main thread that never stops until pmmp stops.
    const HELP_THREAD = 1; # Only starts when more than 1 request batch.

    /**
     * @param Volatile $container
     * @param int $type
     * @param int|null $batch
     */
    public function __construct(Volatile $container, int $type, int $batch = null)
    {
        if($batch != null){
            $this->batch = $batch;
        }
        $this->type = $type;
        $this->container = $container;
        require_once($this->container['folder'] . "/Utils/SQLRequestException.php");
        require_once($this->container['folder'] . "/Utils/SQLRequest.php");
        require_once($this->container['folder'] . "/Utils/SQLConnString.php");
    }

    /**
     * @throws SQLRequestException
     */
    public function run(): void
    {
        $nextTime = microtime(true) + 1;

        if($this->type == self::MAIN_THREAD){
            while ($this->container['runThread'][self::MAIN_THREAD]) {
                if (microtime(true) >= $nextTime) {
                    $nextTime = microtime(true) + $this->executionInterval;
                    $this->processThread();
                }
            }
        }else{
            $this->processThread();
        }
        if(isset($this->container['runThread'][self::HELP_THREAD][$this->getThreadId()])){
            unset($this->container['runThread'][self::HELP_THREAD][$this->getThreadId()]);
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
        /** @var string[] $queryContainers */
        $queryContainers = []; // Categorized queries by SQLConnStrings.

        /** @var string $query */
        foreach ($this->container['batch'][$this->batch] as $id=>$serialized) {
            /** @var SQLRequest $query */
            $query = $serialized;
            # Verifying objects integrity.
            if (!$query instanceof SQLRequest) throw new SQLRequestException('Error while processing a SQLRequest');
            $connString = $query->getConnString();
            if (!$connString instanceof SQLConnString) throw new SQLRequestException('Error while processing a SQLConnString');
            if (!isset($queryContainers[$connString->getName()])) {
                $queryContainers[$connString->getName()] = [];
            }
            $connections[$connString->getName()] = $this->createConnection($connString);
            if (isset($queryContainers[$connString->getName()][$query->getId()])) return;
            $queryContainers[$connString->getName()][$query->getId()] = $query;
            # Removing SQLRequest from the batch.
            unset($this->container['batch'][$this->batch][$id]);
        }
        # Process all queries
        foreach ($queryContainers as $databaseQueries) {
            /** @var SQLRequest $query */
            foreach ($databaseQueries as $query) {
                if (in_array($query->getId(), (array)$this->container['executedRequests'])) return;
                $this->container['executedRequests'][] = $query->getId();
                $stmt = $connections[$query->getConnString()->getName()]->prepare($query->getQuery());
                $stmt->bind_param($query->getDataTypes(), ...$query->getDataKeys());
                $stmt->execute();
                if ($stmt->error_list) {
                    throw new SQLRequestException($stmt->error);
                }
                $results = $stmt->get_result();
                $data = null;
                if ($results !== false) {
                    $data = $results->fetch_assoc();
                }
                if ($data == null) $data = false;
                $this->container['callbackResults'][$query->getId()] = $data;
                $stmt->close();
                unset($queryContainers[$query->getConnString()->getName()][$query->getId()]);
            }
        }
    }

    /**
     * Creates & returns mysqli
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

    /**
     * Function to set the execution interval.
     * @param int $seconds
     */
    public function setExecutionInterval(int $seconds): void
    {
        $this->executionInterval = $seconds;
    }

    /**
     * Function to set the batch that the thread will take care of.
     * @param int $batch
     */
    public function setBatchToExecute(int $batch): void
    {
        $this->batch = $batch;
    }
}