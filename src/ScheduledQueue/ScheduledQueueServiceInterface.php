<?php

namespace Werkspot\MessageQueue\ScheduledQueue;

use Werkspot\MessageQueue\Message\MessageInterface;

interface ScheduledQueueServiceInterface
{
    public function scheduleMessage(MessageInterface $message): void;

    public function unscheduleMessage(MessageInterface ...$messageList): void;

    /**
     * @return MessageInterface[]
     */
    public function findScheduledMessageList(int $quantity = 1): array;
}
