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
    /** @var array[] $queryBatch */
    private array $queryBatch = [];
    /** @var SQLConnString[] $sqlConnStringContainer */
    private array $sqlConnStringContainer = [];
    private DispatchBatchThread $dispatchBatchTask;
    private Volatile $container;


    # Since 2.0.0
    /** @var int[] $dispatchBatchThreadPool */
    private array $dispatchBatchThreadPool = [];
    private array $config = [];

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
        $result = self::getQueryFromBatch($request->getId());
        if($result != null){
            throw new SQLRequestException("There is already a request with the id {$request->getId()}.");
        }
        if(!isset(self::getInstance()->queryBatch[$request->getBatch()])){
            var_dump("Adding request {$request->getId()} to batch {$request->getBatch()}...[new batch]");
            self::getInstance()->queryBatch[$request->getBatch()][$request->getId()] = $request;
        }else{
            $max = self::getInstance()->config['request-per-batch'];
            $i = $request->getBatch();
            while(count(self::getInstance()->queryBatch[$i]) > $max){
                $i++;
                if(!isset(self::getInstance()->queryBatch[$i])){
                    self::getInstance()->queryBatch[$i] = [];
                    break;
                }
            }
            # Set the request in the corresponding batch.
            $request->setBatch($i);
            self::getInstance()->queryBatch[$i][$request->getId()] = $request;
            var_dump("Adding request {$request->getId()} to batch {$request->getBatch()}...");
        }
    }

    function onLoad(): void
    {
        self::$instance = $this;
    }

    protected function onEnable(): void
    {
        # File integrity check
        if (!file_exists($this->getDataFolder() . 'config.yml')) {
            $this->saveResource('config.yml');
        }
        $config = new Config($this->getDataFolder() . 'config.yml', Config::YAML);
        $this->config = $config->getAll();

        # Init volatile.
        $this->container = new Volatile();
        $this->container['runThread'] = [];
        $this->container['runThread'][DispatchBatchThread::MAIN_THREAD] = true;
        $this->container['runThread'][DispatchBatchThread::HELP_THREAD] = [];
        $this->container['executedRequests'] = [];
        $this->container['batch'] = [];
        $this->container['batch'][0] = [];
        $this->container['callbackResults'] = [];
        $this->container['folder'] = __DIR__;

        $this->queryBatch[0] = [];

        # Ini DispatchBatchThread and it's options.
        $this->dispatchBatchTask = new DispatchBatchThread($this->container, DispatchBatchThread::MAIN_THREAD);
        $this->dispatchBatchTask->setExecutionInterval($this->config['batch-execution-interval']);
        $this->dispatchBatchTask->start();
        $this->dispatchBatchThreadPool[0] = $this->dispatchBatchTask->getThreadId();

        # Repeated Task to update the SQLRequests array.
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () {
            foreach ($this->queryBatch as $index=>$batch) {
                /** @var SQLRequest $request */
                foreach($batch as $request){
                    if ($request->hasBeenDispatched()) return;
                    $request->hasBeenDispatched(true);
                    $callback = $request->getCallable();
                    $request->setCallable(null);
                    if(!isset($this->container['batch'][$request->getBatch()])){
                        $this->container['batch'][$request->getBatch()] = [];
                    }
                    $this->container['batch'][$request->getBatch()][$request->getId()] = serialize($request);
                    $request->setCallable($callback);

                    # Create & Start a new dispatchBatchThread for other batches.
                    if(!isset($this->dispatchBatchThreadPool[$request->getBatch()])){
                        var_dump('creating new thread on index ' . $request->getBatch());
                        $this->dispatchBatchThreadPool[$request->getBatch()] = new DispatchBatchThread($this->container, DispatchBatchThread::HELP_THREAD);
                        $this->dispatchBatchThreadPool[$request->getBatch()]->setBatchToExecute($index);
                        $this->dispatchBatchThreadPool[$request->getBatch()]->start();
                        $this->container['runThread'][DispatchBatchThread::HELP_THREAD][$request->getBatch()] = true;
                        unset($this->dispatchBatchThreadPool[$request->getBatch()]);
                    }
                }
            }
        }), 20 * $this->config['batch-update-interval']);

        # Repeated Task to execute the callables from the SQLRequests.
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () {
            foreach ($this->container['executedRequests'] as $index => $id) {
                unset($this->container['executedRequests'][$index]);
                if (!isset($this->container['callbackResults'][$id])) return;
                $request = self::getQueryFrombatch($id);
                self::removeQueryFromBatch($id);
                if ($request instanceof SQLRequest) {
                    if ($request->getCallable() == null) return;
                    if (!isset(self::getInstance()->container['callbackResults'][$id])) {
                        $data = null;
                    } else {
                        $data = (array)self::getInstance()->container['callbackResults'][$id];
                    }
                    call_user_func($request->getCallable(), $data);
                    $request->hasBeenCompleted(true);
                    unset(self::getInstance()->container['callbackResults'][$id]);
                }
            }
        }), 20);
    }

    /**
     * Get a Utils from the batch by id.
     * @param string $id
     * @return SQLRequest|null
     */
    public static function getQueryFromBatch(string $id): ?SQLRequest
    {
        foreach(self::getInstance()->queryBatch as $rqList){
            /**
             * @var string $id
             * @var SQLRequest $request
             */
            foreach($rqList as $request){
                if($request->getId() == $id)
                    return $request;
            }
        }
        return null;
    }

    /**
     * To remove a query from the batch.
     * @param string $id
     * @return bool
     * @throws SQLRequestException
     */
    public static function removeQueryFromBatch(string $id): bool
    {
        foreach(self::getInstance()->queryBatch as $batches){
            /**
             * @var string $id
             * @var SQLRequest $request
             */
            foreach($batches as $request){
                if($request->getId() == $id){
                    unset(self::getInstance()->queryBatch[$request->getBatch()][$id]);
                    return true;
                }
            }
        }
        throw new SQLRequestException("Query: $id hasn't been registered in any batch.");
    }

    protected function onDisable(): void
    {
        $this->container['runThread'][DispatchBatchThread::MAIN_THREAD] = false;
    }
}