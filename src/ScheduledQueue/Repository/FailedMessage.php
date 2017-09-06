<?php

namespace Werkspot\MessageQueue\ScheduledQueue\Repository;

use Werkspot\MessageQueue\Message\MessageInterface;

class FailedMessage
{
    /**
     * @var MessageInterface
     */
    private $message;

    /**
     * @var int
     */
    private $count;

    public function __construct(MessageInterface $message, int $count)
    {
        $this->message = $message;
        $this->count = $count;
    }

    public function getMessage(): MessageInterface
    {
        return $this->message;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function addCount(int $count): void
    {
        $this->count += $count;
    }
}
