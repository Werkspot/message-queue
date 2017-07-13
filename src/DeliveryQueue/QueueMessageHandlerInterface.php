<?php

namespace Werkspot\MessageQueue\DeliveryQueue;

use PhpAmqpLib\Message\AMQPMessage;
use Werkspot\MessageQueue\DeliveryQueue\Exception\AlreadyClaimedException;
use Werkspot\MessageQueue\DeliveryQueue\Exception\CanNotClaimException;

interface QueueMessageHandlerInterface
{
    /**
     * @throws AlreadyClaimedException
     * @throws CanNotClaimException
     */
    public function claim(AMQPMessage $amqpMessage): void;
}
