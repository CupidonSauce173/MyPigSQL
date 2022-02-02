<?php

namespace CupidonSauce173\MyPigSQL\Task;

use CupidonSauce173\MyPigSQL\MyPigSQL;
use CupidonSauce173\MyPigSQL\SQLRequest\SQLRequest;
use CupidonSauce173\MyPigSQL\SQLRequest\SQLRequestException;
use pocketmine\scheduler\AsyncTask;
use mysqli;

class DispatchBatch extends AsyncTask
{
    private array $queryBatch;
    private array $connStrings; # Categorize queries.
    /** @var mysqli[] $connections */
    private array $connections; # Holds all MySQL connections.

    public function setQueryBatch(array $queryBatch): void
    {
        $this->queryBatch = $queryBatch;
    }

    /**
     * @throws SQLRequestException
     */
    public function onRun(): void
    {
        # Categorize queries into connStrings & creating MySQL connections.
        foreach($this->queryBatch as $serializedQuery){
            /** @var SQLRequest $query */
            $query = unserialize($serializedQuery);
            $connString = $query->getConnString();
            if(!isset($this->connStrings[$connString->getName()])){
                $this->connStrings[$connString->getName()] = $connString;
                $conn = new mysqli(
                    $connString->getAddress(),
                    $connString->getUsername(),
                    $connString->getPassword(),
                    $connString->getDatabase(),
                    $connString->getPort()
                );
                $this->connections[$connString->getName()] = $conn;
            }
            $this->connStrings[$query->getConnString()->getName()][] = $query;
        }

        # Process all queries
        $hasCallable = [];
        foreach($this->connStrings as $connString){
            /** @var SQLRequest $query */
            foreach($connString as $query){
                $stmt = $this->connections[$query->getConnString()->getName()]->prepare($query->getQuery());
                $stmt->bind_param($query->getDataTypes(), ...$query->getDataKeys());
                $stmt->execute();
                if($stmt->error_list){
                    throw new SQLRequestException($stmt->error);
                }
                if($query->getCallable() !== null){
                    $hasCallable[] = [$query, $stmt->get_result()->fetch_assoc()];
                }
            }
        }
        $this->setResult($hasCallable);
    }

    public function onCompletion(): void
    {
        if($this->getResult() !== null){
            foreach($this->getResult() as $results){
                /** @var SQLRequest $query */
                $query = $results[0];
                MyPigSQL::removeQueryFromBatch($query->getId());
                call_user_func($query->getCallable(), $results);
            }
        }
    }
}