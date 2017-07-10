<?php

namespace Werkspot\MessageQueue\DeliveryQueue;

interface ConsumerInterface
{
    /**
     * @param int $maxSeconds The maximum amount of seconds we consume, before returning
     */
    public function startConsuming(string $queueName, int $maxSeconds): void;
}
