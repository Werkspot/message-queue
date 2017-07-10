<?php

namespace Werkspot\MessageQueue;

interface ScheduledQueueToDeliveryQueueWorkerInterface
{
    /**
     * @param float $timeToSleep time in seconds
     */
    public function moveMessageBatch(int $batchSize, callable $afterMessageTransfer = null): int;
}
