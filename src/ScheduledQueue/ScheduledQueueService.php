<?php

namespace Werkspot\MessageQueue\ScheduledQueue;

use Werkspot\MessageQueue\Message\MessageInterface;
use Werkspot\MessageQueue\ScheduledQueue\Repository\MessageRepositoryInterface;

class ScheduledQueueService implements ScheduledQueueServiceInterface
{
    const EVENT_PREFIX = 'queued.';

    /**
     * @var MessageRepositoryInterface
     */
    private $scheduledMessageRepository;

    public function __construct(MessageRepositoryInterface $scheduledMessageRepository)
    {
        $this->scheduledMessageRepository = $scheduledMessageRepository;
    }

    public function scheduleMessage(MessageInterface $message): void
    {
        $this->scheduledMessageRepository->save($message);
    }

    public function findScheduledMessageList(int $quantity = 1): array
    {
        return $this->scheduledMessageRepository->findMessagesToDeliver($quantity);
    }

    public function unscheduleMessage(MessageInterface ...$messageList): void
    {
        foreach ($messageList as $message) {
            $this->scheduledMessageRepository->delete($message);
        }
    }
}
