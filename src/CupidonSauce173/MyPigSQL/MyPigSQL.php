<?php

namespace CupidonSauce173\MyPigSQL;

use CupidonSauce173\MyPigSQL\Task\DispatchBatchThread;
use CupidonSauce173\MyPigSQL\Task\ValidationTask;
use CupidonSauce173\MyPigSQL\Utils\DispatchBatchPool;
use CupidonSauce173\MyPigSQL\Utils\SQLConnString;
use CupidonSauce173\MyPigSQL\Utils\SQLRequest;
use CupidonSauce173\MyPigSQL\Utils\SQLRequestException;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use Volatile;
use function call_user_func;
use function count;

class MyPigSQL extends PluginBase
{
    private static MyPigSQL $instance;
    public array $config = [];
    private array $queryBatch = [];
    private array $sqlConnStringContainer = [];
    private array $requestCallableContainer = [];
    private DispatchBatchThread $dispatchBatchTask;
    private Volatile $container;

    public static function validateConnString(array $connString): void
    {
        self::getInstance()->getServer()->getAsyncPool()->submitTask(new ValidationTask($connString));
    }

    public static function getInstance(): self
    {
        return self::$instance;
    }

    /**
     * @throws SQLRequestException
     */
    public static function registerStringConn(SQLConnString $connString): bool
    {
        $connName = $connString->getName();
        if (isset(self::getInstance()->sqlConnStringContainer[$connName])) {
            throw new SQLRequestException("An SQLConnString with the name '$connName' is already registered.");
        }

        self::getInstance()->sqlConnStringContainer[$connName] = $connString;
        return true;
    }

    /**
     * @throws SQLRequestException
     */
    public static function unregisterStringConn(SQLConnString $connString): bool
    {
        return self::unregisterStringConnByName($connString->getName());
    }

    /**
     * @throws SQLRequestException
     */
    public static function unregisterStringConnByName(string $connName): bool|SQLRequestException
    {
        $instance = self::getInstance();
        if (!isset($instance->sqlConnStringContainer[$connName])) {
            throw new SQLRequestException("No SQLConnString object with the name '$connName' has been registered.");
        }

        unset($instance->sqlConnStringContainer[$connName]);
        return true;
    }

    /**
     * @throws SQLRequestException
     */
    public static function getSQLConnStringByName(string $connName): SQLConnString
    {
        $instance = self::getInstance();
        if (!isset($instance->sqlConnStringContainer[$connName])) {
            throw new SQLRequestException("No SQLConnString object with the name '$connName' has been registered.");
        }

        return $instance->sqlConnStringContainer[$connName];
    }

    public static function addQueryToBatch(SQLRequest $request): void
    {
        $config = self::getInstance()->config;
        $maxBatchSize = $config['request-per-batch'];
        $queryBatch = self::getInstance()->queryBatch;

        if (!isset($queryBatch[$request->getBatch()])) {
            self::getInstance()->requestCallableContainer[$request->getId()] = $request->getCallable();
            $request->setCallable(null);
            $queryBatch[$request->getBatch()][$request->getId()] = $request;
        } else {
            $i = $request->getBatch();
            while (count($queryBatch[$i]) > $maxBatchSize) {
                $i++;
                if (!isset($queryBatch[$i])) {
                    $queryBatch[$i] = [];
                }
            }
            $request->setBatch($i);
            self::getInstance()->requestCallableContainer[$request->getId()] = $request->getCallable();
            $request->setCallable(null);
            $queryBatch[$i][$request->getId()] = $request;
        }

        self::getInstance()->queryBatch = $queryBatch;
    }

    protected function onLoad(): void
    {
        self::$instance = $this;
    }

    protected function onEnable(): void
    {
        if (!$this->getDataFolder() . 'config.yml') {
            $this->saveResource('config.yml');
        }
        $config = new Config($this->getDataFolder() . 'config.yml', Config::YAML);
        $this->config = $config->getAll();

        $this->container = new Volatile();
        $this->container['runThread'] = [
            DispatchBatchThread::MAIN_THREAD => true,
            DispatchBatchThread::HELP_THREAD => []
        ];
        $this->container['executedRequests'] = [];
        $this->container['batch'] = [0 => []];
        $this->container['callbackResults'] = [];
        $this->container['folder'] = __DIR__;

        $this->queryBatch = [];

        $this->dispatchBatchTask = new DispatchBatchThread($this->container, DispatchBatchThread::MAIN_THREAD);
        $this->dispatchBatchTask->setExecutionInterval($this->config['batch-execution-interval']);
        $this->dispatchBatchTask->start();

        $batchUpdateInterval = 20 * $this->config['batch-update-interval'];
        $batchExecutionInterval = 20;

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () {
            foreach ($this->queryBatch as $batch) {
                foreach ($batch as $request) {
                    if ($request->hasBeenDispatched()) {
                        continue;
                    }
                    $request->setDispatched(true);
                    if (!isset($this->container['batch'][$request->getBatch()])) {
                        $this->container['batch'][$request->getBatch()] = [];
                    }
                    $this->container['batch'][$request->getBatch()][$request->getId()] = $request;
                }
            }

            end($this->queryBatch);
            $key = count($this->queryBatch);
            reset($this->queryBatch);

            $dispatchBatchPool = new DispatchBatchPool($key, 'Worker');
            for ($i = 1; $i < $key; $i++) {
                $c = count($this->queryBatch[$i]);
                if ($c == 0) {
                    continue;
                }
                $dispatchBatchPool->submit(new DispatchBatchThread($this->container, DispatchBatchThread::HELP_THREAD, $i));
            }

        }), $batchUpdateInterval);

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () {
            $executedRequests = $this->container['executedRequests'];

            foreach ($executedRequests as $index => $id) {
                unset($executedRequests[$index]);

                $request = null;
                if (isset($this->container['callbackResults'][$id])) {
                    $request = self::getQueryFromBatch($id);
                }

                if ($request instanceof SQLRequest) {
                    unset($this->queryBatch[$request->getBatch()][$request->getId()]);
                    self::removeQueryFromBatch($request->getId(), $request->getBatch());

                    $function = $this->requestCallableContainer[$request->getId()];
                    $data = $this->container['callbackResults'][$id] ?? null;

                    if ($function !== null) {
                        call_user_func($function, (array)$data);
                    }

                    $request->setCompleted(true);

                    unset($this->container['callbackResults'][$id]);
                    unset($this->requestCallableContainer[$request->getId()]);
                }
            }

            $this->container['executedRequests'] = $executedRequests;
        }), $batchExecutionInterval);

        // For testing purpose
        //new testClass();
    }

    public static function getQueryFromBatch(string $id): ?SQLRequest
    {
        $queryBatch = self::getInstance()->queryBatch;

        foreach ($queryBatch as $batch) {
            foreach ($batch as $request) {
                if ($request->getId() === $id) {
                    return $request;
                }
            }
        }

        return null;
    }

    public static function removeQueryFromBatch(string $id, int $batch): bool
    {
        $queryBatch = self::getInstance()->queryBatch;

        if (!isset($queryBatch[$batch][$id])) {
            return false;
        }

        unset($queryBatch[$batch][$id]);
        self::getInstance()->queryBatch = $queryBatch;
        return true;
    }

    protected function onDisable(): void
    {
        $this->container['runThread'][DispatchBatchThread::MAIN_THREAD] = false;
    }
}
