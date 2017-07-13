<?php

namespace Werkspot\MessageQueue\DeliveryQueue;

use Werkspot\MessageQueue\Message\MessageInterface;

interface MessageHandlerInterface
{
    public function handle(MessageInterface $message);

    public function isHandlingMessage(): bool;

    public function shutdown(): void;
}
