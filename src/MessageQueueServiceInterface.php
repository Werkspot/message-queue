<?php

namespace Werkspot\MessageQueue;

use DateTimeImmutable;

interface MessageQueueServiceInterface
{
    public function enqueueMessage($payload, string $destination, DateTimeImmutable $deliverAt, int $priority, array $metadata): void;
}
