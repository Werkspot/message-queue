<?php

namespace Werkspot\MessageQueue;

use DateTimeInterface;

interface MessageQueueServiceInterface
{
    public function enqueueMessage(
        $payload,
        string $destination,
        DateTimeInterface $deliverAt,
        int $priority
    ): void;
}
