<?php

namespace Werkspot\MessageQueue\DeliveryQueue\Amqp;

use Ramsey\Uuid\Uuid;

final class UuidMessageIdGenerator implements MessageIdGeneratorInterface
{
    public function generateId(): string
    {
        return Uuid::uuid4();
    }
}
