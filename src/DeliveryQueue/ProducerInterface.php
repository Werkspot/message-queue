<?php

namespace Werkspot\MessageQueue\DeliveryQueue;

use Werkspot\MessageQueue\Message\MessageInterface;

interface ProducerInterface
{
    public function send(MessageInterface $message, string $queueName): void;
}
