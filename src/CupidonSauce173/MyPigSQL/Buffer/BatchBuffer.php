<?php

namespace CupidonSauce173\MyPigSQL\Utils;

use CupidonSauce173\MyPigSQL\MyPigSQL;
use Volatile;

# [NEW]
class BatchBuffer extends Volatile
{
    private Volatile $buffer; // buffer set in the array [0]
    private int $size; // maximum size of the array
    private string $id;
    private int $timeOut;

    public function __construct(int $timeOut = null, int $batchSize = null)
    {
        $this->buffer[0] = [];
        $this->id = uniqid();

        if($timeOut == null){
            $this->timeOut = MyPigSQL::getInstance()->config['buffer-timeout'];
        }
        if($batchSize == null){
            $this->size = MyPigSQL::getInstance()->config['default-buffer-size'];
        }
    }

    /**
     * Need more verifications but will tell if the buffer is available for future use.
     * @return bool
     */
    public function isAvailable(): bool{
        if(count($this->buffer[0]) < $this->size){
            return true;
        }
        return false;
    }

    /**
     * Returns the id of this buffer
     * @return string
     */
    public function getId(): string{
        return $this->id;
    }

    /**
     * Returns the timeout value of the batch before it gets destroyed (when inactive).
     * @return int
     */
    public function getTimeOut(): int{
        return $this->timeOut;
    }

    /**
     * Sets the timeout of the batch before it gets destroyed (when inactive).
     * @param int $timeOut
     */
    public function setTimeOut(int $timeOut): void{
        $this->timeOut = $timeOut;
    }

    /**
     * Add an SQLRequest to the buffer
     * @param SQLRequest $request
     */
    public function addRequest(SQLRequest $request){
        $this->buffer[0][$request->getId()] = $request;
    }

    /**
     * Remove an SQLRequest from the buffer
     * @param string $id
     */
    public function removeRequest(string $id){
        if(isset($this->buffer[0][$id])){
            unset($this->buffer[0][$id]);
        }
    }

    /**
     * Get the current buffer size, which is the amount of SQLRequests the buffer holds.
     * @return int
     */
    public function getBufferSize(): int{
        return $this->size;
    }

    /**
     * Set the buffer size, which is the maximum amount of SQLRequests the buffer can hold.
     * @param int $value
     */
    public function setBufferSize(int $value){
        $this->size = $value;
    }
}