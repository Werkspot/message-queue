<?php

namespace Werkspot\MessageQueue\DeliveryQueue\Amqp;

interface MessageIdGeneratorInterface
{
    public function generateId(): string;
}
