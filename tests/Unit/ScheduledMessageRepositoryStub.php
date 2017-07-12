<?php

namespace Werkspot\MessageQueue\Test\Unit;

use Werkspot\MessageQueue\Message\MessageInterface;
use Werkspot\MessageQueue\ScheduledQueue\Repository\MessageRepositoryInterface;

final class ScheduledMessageRepositoryStub implements MessageRepositoryInterface
{
    /**
     * @var array
     */
    private $db = [];

    public function findAll(): array
    {
        return $this->db;
    }

    public function findMessagesToDeliver(int $limit): array
    {
        return array_slice($this->db, 0, $limit);
    }

    public function save(MessageInterface $message): void
    {
        $this->db[$message->getId()] = $message;
    }

    public function delete(MessageInterface $message): void
    {
        unset($this->db[$message->getId()]);
    }
}
