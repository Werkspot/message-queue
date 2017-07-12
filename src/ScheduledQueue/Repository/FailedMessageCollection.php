<?php

namespace Werkspot\MessageQueue\ScheduledQueue\Repository;

use Countable;
use Iterator;
use Werkspot\MessageQueue\Message\MessageInterface;

final class FailedMessageCollection implements Countable, Iterator
{
    /**
     * @var array
     */
    private $failedMessages = [];

    /**
     * @var int
     */
    private $pointer = 0;

    public function __construct(array $failedMessagesArray)
    {
        /**
         * @var MessageInterface $message
         * @var int $count
         */
        foreach ($failedMessagesArray as [$message, $count]) {
            $this->failedMessages[] = new FailedMessage($message, $count);
        }
    }

    public function current(): FailedMessage
    {
        return $this->failedMessages[$this->pointer];
    }

    public function next(): void
    {
        $this->pointer++;
    }

    public function key(): int
    {
        return $this->pointer;
    }

    public function valid(): bool
    {
        return isset($this->failedMessages[$this->pointer]);
    }

    public function rewind(): void
    {
        $this->pointer = 0;
    }

    public function count(): int
    {
        return count($this->failedMessages);
    }
}
