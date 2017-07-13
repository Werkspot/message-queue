<?php

namespace Werkspot\MessageQueue\DeliveryQueue;

use Werkspot\MessageQueue\Message\MessageInterface;

interface MessageDeliveryServiceInterface
{
    public function deliver(MessageInterface $message): void;
}
