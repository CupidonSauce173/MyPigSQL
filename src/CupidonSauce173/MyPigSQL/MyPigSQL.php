<?php

namespace CupidonSauce173\MyPigSQL;

use CupidonSauce173\MyPigSQL\Task\DispatchBatchThread;
use CupidonSauce173\MyPigSQL\Task\ValidationTask;
use CupidonSauce173\MyPigSQL\Utils\SQLConnString;
use CupidonSauce173\MyPigSQL\Utils\SQLRequest;
use CupidonSauce173\MyPigSQL\Utils\SQLRequestException;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use Volatile;
use function call_user_func;
use function file_exists;

class MyPigSQL extends PluginBase
{
    static MyPigSQL $instance;
    /** @var SQLRequest[] */
    private array $queryBatch = [];
    /** @var SQLConnString[] $sqlConnStringContainer */
    private array $sqlConnStringContainer = [];
    private DispatchBatchThread $dispatchBatchTask;

    private Volatile $container;

    /**
     * Will validate a connection string by trying to connect to MySQL in an anonymous AsyncTask.
     * @param array $connString
     */
    public static function validateConnString(array $connString)
    {
        # Will validate connection by trying to connect async.
        MyPigSQL::getInstance()->getServer()->getAsyncPool()->submitTask(new ValidationTask($connString));
    }

    /**
     * @return MyPigSQL
     */
    static function getInstance(): self
    {
        return self::$instance;
    }

    /**
     * To register a new ConnString in the SQLConnString container.
     * @param SQLConnString $connString
     * @return bool
     * @throws SQLRequestException
     */
    public static function registerStringConn(SQLConnString $connString): bool
    {
        if (isset(self::getInstance()->sqlConnStringContainer[$connString->getName()])) {
            throw new SQLRequestException('You already registered an SQLConnString with the name: ' . $connString->getName());
        }
        self::getInstance()->sqlConnStringContainer[$connString->getName()] = $connString;
        return true;
    }

    /**
     * To unregister an SQLConnString from the SQLConnString container by connection name.
     * @param string $connName
     * @return bool|SQLRequestException
     */
    public static function unregisterStringConnByName(string $connName): bool|SQLRequestException
    {
        if (!isset(self::getInstance()->sqlConnStringContainer[$connName])) {
            return new SQLRequestException(
                "No SQLConnString object with name: $connName has been registered in the sqlConnStringContainer"
            );
        }
        unset(self::getInstance()->sqlConnStringContainer[$connName]);
        return true;
    }

    /**
     * To unregister an SQLConnString from the SQLConnString container.
     * @param SQLConnString $connString
     * @return bool
     * @throws SQLRequestException
     */
    public static function unregisterStringConn(SQLConnString $connString): bool
    {
        if (!isset(self::getInstance()->sqlConnStringContainer[$connString->getName()])) {
            throw new SQLRequestException(
                'No SQLConnString object with name: ' .
                $connString->getName() .
                ' has been registered in the sqlConnStringContainer'
            );
        }
        unset(self::getInstance()->sqlConnStringContainer[$connString->getName()]);
        return true;
    }

    /**
     * To get the SQLConnString by connName (Connection Name).
     * @param string $connName
     * @return SQLConnString
     * @throws SQLRequestException
     */
    public static function getSQLConnStringByName(string $connName): SQLConnString
    {
        if (!isset(self::getInstance()->sqlConnStringContainer[$connName])) {
            throw new SQLRequestException('No SQLConnString object has the name: ' . $connName);
        }
        return self::getInstance()->sqlConnStringContainer[$connName];
    }

    /**
     * To add a new Utils in the Utils batch, it will encode the request and pack it to be dispatched later.
     * @param SQLRequest $request
     * @throws SQLRequestException
     */
    public static function addQueryToBatch(SQLRequest $request): void
    {
        if (isset(self::getInstance()->queryBatch[$request->getId()])) {
            throw new SQLRequestException("There is already a request with the id {$request->getId()}.");
        }
        self::getInstance()->queryBatch[$request->getId()] = $request;
    }

    function onLoad(): void
    {
        self::$instance = $this;
    }

    protected function onEnable(): void
    {
        $this->container = new Volatile();
        $this->container['runThread'] = true;
        $this->container['executedRequests'] = [];
        $this->container['batch'] = [];
        $this->container['callbackResults'] = [];
        $this->container['folder'] = __DIR__;

        # File integrity check
        if (!file_exists($this->getDataFolder() . 'config.yml')) {
            $this->saveResource('config.yml');
        }
        $config = new Config($this->getDataFolder() . 'config.yml', Config::YAML);
        $config = $config->getAll();

        # Ini DispatchBatchThread and it's options.
        $this->dispatchBatchTask = new DispatchBatchThread($this->container);
        $this->dispatchBatchTask->setExecutionInterval($config['batch-execution-interval']);
        $this->dispatchBatchTask->start();

        # Repeated Task to update the SQLRequests array.
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () {
            foreach ($this->queryBatch as $request) {
                if ($request->hasBeenExecuted()) return;
                $callback = $request->getCallable();
                $request->setCallable(null);
                $this->container['batch'][] = $request;
                $request->setCallable($callback);
                $request->hasBeenExecuted(true);
            }
        }), 20 * $config['batch-update-interval']);

        # Repeated Task to execute the callables from the SQLRequests.
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () {
            foreach ($this->container['executedRequests'] as $index => $id) {
                unset($this->container['executedRequests'][$index]);
                if (!isset($this->container['callbackResults'][$id])) return;
                $request = self::getQueryFrombatch($id);
                self::removeQueryFromBatch($id);
                if ($request instanceof SQLRequest) {
                    if ($request->getCallable() == null) return;
                    call_user_func($request->getCallable(), (array)self::getInstance()->container['callbackResults'][$id]);
                    unset(self::getInstance()->container['callbackResults'][$id]);
                }
            }
        }), 20);
    }

    /**
     * Get a Utils from the batch by id.
     * @param string $id
     * @return SQLRequest
     * @throws SQLRequestException
     */
    public static function getQueryFromBatch(string $id): SQLRequest
    {
        if (!isset(self::getInstance()->queryBatch[$id])) {
            throw new SQLRequestException("There is no Utils with the id $id");
        }
        return self::getInstance()->queryBatch[$id];
    }

    /**
     * To remove a query from the batch.
     * @param string $id
     * @return bool
     * @throws SQLRequestException
     */
    public static function removeQueryFromBatch(string $id): bool
    {
        if (!isset(self::getInstance()->queryBatch[$id])) {
            throw new SQLRequestException("Query: $id hasn't been registered in the batch.");
        }
        unset(self::getInstance()->queryBatch[$id]);
        return true;
    }

    protected function onDisable(): void
    {
        $this->container['runThread'] = false;
    }
}