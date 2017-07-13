<?php

namespace Werkspot\MessageQueue\DeliveryQueue;

use Werkspot\MessageQueue\DeliveryQueue\Exception\UnrecoverablePersistenceException;
use Werkspot\MessageQueue\Message\MessageInterface;
use Werkspot\MessageQueue\Message\UnRequeueableMessageInterface;

interface PersistenceClientInterface
{
    /**
     * @param MessageInterface|UnRequeueableMessageInterface $message
     *
     * @throws UnrecoverablePersistenceException
     */
    public function persist($message): void;

    public function persistUndeliverableMessage($message, string $reason = ''): void;

    public function rollbackTransaction(): void;

    public function reset(): void;
}
