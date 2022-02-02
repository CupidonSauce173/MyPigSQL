<?php

namespace CupidonSauce173\MyPigSQL;

use CupidonSauce173\MyPigSQL\SQLRequest\SQLConnString;
use CupidonSauce173\MyPigSQL\SQLRequest\SQLRequest;
use CupidonSauce173\MyPigSQL\SQLRequest\SQLRequestException;
use CupidonSauce173\MyPigSQL\Task\DispatchBatchThread;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use mysqli;
use Volatile;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;

class MyPigSQL extends PluginBase
{
    /** @var SQLRequest[] */
    private array $queryBatch = [];
    /** @var SQLConnString[] $sqlConnStringContainer */
    private array $sqlConnStringContainer = [];

    static MyPigSQL $instance;
    private DispatchBatchThread $dispatchBatchTask;

    private Volatile $container;

    /**
     * @throws SQLRequestException
     */
    protected function onEnable(): void
    {
        $this->container = new Volatile();
        $this->container['runThread'] = true;
        $this->container['executedRequests'] = [];
        $this->container['batch'] = [];
        $this->container['callbackResults'] = [];

        # File integrity check
        if (!file_exists($this->getDataFolder() . 'config.yml')) {
            $this->saveResource('config.yml');
        }
        $config = new Config($this->getDataFolder() . 'config.yml', Config::YAML);
        $config = $config->getAll();

        self::iniConnStrings();

        # Ini DispatchBatchThread and it's options.
        $this->dispatchBatchTask = new DispatchBatchThread($this->container);
        $this->dispatchBatchTask->setExecutionInterval($config['batch-execution-interval']);
        $this->dispatchBatchTask->start();

        # Repeated Task to update the SQLRequests array.
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () {
            foreach($this->queryBatch as $request){
                if($request->hasBeenExecuted()) return;
                $callback = $request->getCallable();
                $request->setCallable(null);
                $this->container['batch'][] = $request;
                $request->setCallable($callback);
                $request->hasBeenExecuted(true);
            }
        }), 20 * $config['batch-update-interval']);

        # Repeated Task to execute the callables from the SQLRequests.
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () {
            foreach ($this->container['executedRequests'] as $index=>$id) {
                unset($this->container['executedRequests'][$index]);
                if (!isset($this->container['callbackResults'][$id])) return;
                $request = self::getQueryFrombatch($id);
                self::removeQueryFromBatch($id);;
                if ($request instanceof SQLRequest) {
                    if ($request->getCallable() == null) return;
                    call_user_func($request->getCallable(), (array)self::getInstance()->container['callbackResults'][$id]);
                    unset(self::getInstance()->container['callbackResults'][$id]);
                }
            }
        }), 20);
    }

    function onLoad(): void
    {
        self::$instance = $this;
    }

    /**
     * @return static
     */
    static function getInstance(): self
    {
        return self::$instance;
    }

    /**
     * @throws SQLRequestException
     */
    public static function iniConnStrings(): void
    {
        # Examples of how to use the registerStringConn function.
        MyPigSQL::registerStringConn(
            SQLConnString::create(
                'MainDatabase',
                '127.0.0.1',
                'sha2user',
                'G($&*N@)#Ivmn0I@#T',
                'notifications',
                3306,
                true)
        );

        $request = new SQLRequest();
        $request->setQuery("SELECT * FROM FriendRequests WHERE id = ?");
        $request->setDataTypes('i');
        $request->setDataKeys(['220']);
        $request->setConnString(MyPigSQL::getSQLConnStringByName('MainDatabase'));
        $request->setCallable(function(array $data){
            MyPigSQL::getInstance()->getServer()->broadcastMessage(
                'Wow, this is very fine! Here is when the relation has been created: ' . $data['reg_date']
            );
        });

        # Examples of how to create a new SQLRequest object.
        $requestTwo = SQLRequest::create(
            'SELECT * FROM FriendRequests WHERE id = ?',
            'i',
            ['220'],
            self::getSQLConnStringByName('MainDatabase'),
            function (){
                MyPigSQL::getInstance()->getServer()->broadcastMessage("The task is done! output from the query: ");
            }
        );

        MyPigSQL::addQueryToBatch($request); # Adds requestTwo to the batch.
    }

    /**
     * @param string $requestId
     * @return array
     * @throws SQLRequestException
     */
    public static function getRequestOutput(string $requestId): array
    {
        if(!isset(self::getInstance()->container['callbackResults'][$requestId])){
            throw new SQLRequestException("There are no results for your $requestId request!");
        }
        return self::getInstance()->container['callbackResults'][$requestId];
    }

    /**
     * Will validate a connection string by trying to connect to MySQL in an anonymous AsyncTask.
     * @param array $connString
     */
    public static function validateConnString(array $connString)
    {
        # Will validate connection by trying to connect async.
        $asyncTest = new class() extends AsyncTask {
            public array $connInfo;

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

            public function onCompletion(): void
            {
                if (!$this->getResult()) new SQLRequestException($this->getResult());
            }
        };
        $asyncTest->connInfo = $connString;
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
     * Get a SQLRequest from the batch by id.
     * @param string $id
     * @return SQLRequest
     * @throws SQLRequestException
     */
    public static function getQueryFromBatch(string $id): SQLRequest
    {
        if (!isset(self::getInstance()->queryBatch[$id])){
            throw new SQLRequestException("There is no SQLRequest with the id $id");
        }
        return self::getInstance()->queryBatch[$id];
    }

    /**
     * To add a new SQLRequest in the SQLRequest batch, it will encode the request and pack it to be dispatched later.
     * @param SQLRequest $request
     * @throws SQLRequestException
     */
    public static function addQueryToBatch(SQLRequest $request): void
    {
        if(isset(self::getInstance()->queryBatch[$request->getId()])){
            throw new SQLRequestException("There is already a request with the id {$request->getId()}.");
        }
        self::getInstance()->queryBatch[$request->getId()] = $request;
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
}