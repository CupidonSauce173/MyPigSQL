<?php

use CupidonSauce173\MyPigSQL\Utils\BatchBuffer;
use CupidonSauce173\MyPigSQL\Utils\SQLRequest;
use CupidonSauce173\MyPigSQL\Utils\SQLConnString;
use CupidonSauce173\MyPigSQL\MyPigSQL;
use CupidonSauce173\MyPigSQL\Utils\SQLRequestException;

class testClass
{
    /**
     * @throws SQLRequestException
     */
    public function __construct()
    {
        # SQLConnString example.
        $connString = new SQLConnString();
        $connString
            ->setName("mainDb")
            ->setPort(3306)
            ->setUsername("SuperUser")
            ->setPassword("FG)@*#Mdeo@IQ#NJDF)*GJ4")
            ->setAddress("127.0.0.1")
            ->setDatabase("mainPlayerDb")
            ->validate(); // perform verifications to make sure everything works, optional.

        # SQLRequest example (from static create method).
        $testSqlR = SQLRequest::create(
            "SELECT * FROM PlayerData WHERE username = ?",
            "s",
            ['CupidonSauce173'],
            $connString,
            function($data){
                var_dump("SQLRequest has been processed with success! Data retrieved: " . $data);
            }
        );
        $testSqlR->register(); # Registers the SQLRequest in an available BatchBuffer instance.

        # SQLRequest example (from non-static methods).
        $connTestTwo = MyPigSQL::getSQLConnStringByName("mainFDb");
        $testSqlRTwo = new SQLRequest();
        $player = 'CupidonSauce173';
        $testSqlRTwo
            ->setQuery("SELECT balance FROM PlayerBalances WHERE username = ?")
            ->setDataTypes("s")
            ->setDataKeys([$player])
            ->setConnString($connTestTwo)
            ->setCallable(function($data) use ($player){
                if((int)$data > 100){
                    var_dump($player . " has more than 100$!");
                }
            });
        MyPigSQL::addQueryToBatch($testSqlRTwo); # Registers the SQLRequest in an available BatchBuffer from the Core's static method.

        # BatchBuffer registration example.
        $batchBuffer = new BatchBuffer();
        $batchBuffer->setBufferSize(50);
        $batchBuffer->setTimeOut(10);
        MyPigSQL::registerBatchBuffer($batchBuffer); # registers the buffer in the container from the core's static method.

        $batchBufferTwo = new BatchBuffer(20, 15); # arg1 = timeOut (seconds), arg2 = SQLRequest max hold.
        $batchBufferTwo->register(); # Registers the buffer in the container.

        MyPigSQL::unregisterStringConn(MyPigSQL::getSQLConnStringByName("MainPlayerDb"));
    }
}