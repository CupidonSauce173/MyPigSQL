<?php

namespace CupidonSauce173\MyPigSQL\Task;

use CupidonSauce173\MyPigSQL\Utils\SQLRequestException;
use pocketmine\scheduler\AsyncTask;
use mysqli;

class ValidationTask extends AsyncTask
{
    public array $connInfo;

    public function __construct(array $connInfo)
    {
        $this->connInfo = $connInfo;
    }

    public function onRun(): void
    {
        $conn = new mysqli(
            $this->connInfo['address'],
            $this->connInfo['username'],
            $this->connInfo['password'],
            $this->connInfo['database'],
            $this->connInfo['port']);
        if ($conn->connect_error != false) {
            $this->setResult($conn->connect_error);
        } else {
            $this->setResult(true);
        }
    }

    /**
     * @throws SQLRequestException
     */
    public function onCompletion(): void
    {
        if (!$this->getResult()) throw new SQLRequestException($this->getResult());
    }
}