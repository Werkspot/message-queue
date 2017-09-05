<?php

namespace Werkspot\MessageQueue\ScheduledQueue\Repository;

use Werkspot\MessageQueue\Message\MessageInterface;

interface MessageRepositoryInterface
{
    /**
     * The maximum number of message delivery tries
     */
    const MAXIMUM_DELIVERY_TRIES = 4;

    /**
     * @return MessageInterface[]
     */
    public function findAll(): array;

    /**
     * @return MessageInterface[]
     */
    public function findMessagesToDeliver(int $limit): array;

    public function save(MessageInterface $message): void;

    /**
     * This method must persist/flush the changes immediately to prevent failed deletes because of later errors
     */
    public function delete(MessageInterface $message): void;
}
