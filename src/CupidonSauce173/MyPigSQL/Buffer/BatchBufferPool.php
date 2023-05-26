<?php

namespace CupidonSauce173\MyPigSQL\Utils;

class BatchBufferPool
{
    private int $size;
    private array $requestQueues;

    private array $executables;

    /** @var BatchBuffer[] */
    private array $batches;

    public function submitRequest(SQLRequest $request): void
    {
        $batch = $this->selectBatch();

        $executable = $request->getCallable();
        if ($executable !== null) {
            $this->executables[$request->getId()] = $request->getCallable();
            $request->setCallable(null);
        }
        $batch->addRequest($request);
    }

    public function selectBatch(): BatchBuffer
    {
        $batch = null;
        foreach ($this->batches as $batchBuffer) {
            if ($batchBuffer->isAvailable()) {
                $batch = $batchBuffer;
            }
        }
        if ($batch == null) {
            $nb = new BatchBuffer();
            $this->batches[$nb->getId()] = $nb;
            $batch = $nb;
        }
        return $batch;
    }

    private function executeCallables(): void
    {

    }

}