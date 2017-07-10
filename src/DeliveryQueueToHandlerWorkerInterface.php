<?php

namespace Werkspot\MessageQueue;

interface DeliveryQueueToHandlerWorkerInterface
{
    public function startConsuming(int $maxExecutionTimeInSeconds): void;
}
