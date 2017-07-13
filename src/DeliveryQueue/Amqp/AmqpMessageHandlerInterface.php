<?php

namespace Werkspot\MessageQueue\DeliveryQueue\Amqp;

use PhpAmqpLib\Message\AMQPMessage;

interface AmqpMessageHandlerInterface
{
    public function handle(AMQPMessage $amqpMessage): void;
}
