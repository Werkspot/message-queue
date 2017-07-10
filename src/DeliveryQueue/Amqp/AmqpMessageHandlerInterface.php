<?php

namespace Werkspot\MessageQueue\DeliveryQueue\Amqp;

use PhpAmqpLib\Message\AMQPMessage;

interface AmqpMessageHandlerInterface
{
    // TODO refactor this interface so that we don't depend Amqp here anymore
    // a way we can do it is to have an interface like handle(\Werkspot\MessageQueue\Queue\SynchronousMessage $message): bool
    // when it's true it's acknowledged, if false it's being nacked.
    public function handle(AMQPMessage $message);

    public function isHandlingMessage(): bool;
}
