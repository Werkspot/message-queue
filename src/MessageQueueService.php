<?php

namespace Werkspot\MessageQueue;

use DateTimeImmutable;
use Werkspot\MessageQueue\Message\Message;
use Werkspot\MessageQueue\ScheduledQueue\ScheduledQueueServiceInterface;

final class MessageQueueService implements MessageQueueServiceInterface
{
    /**
     * @var ScheduledQueueServiceInterface
     */
    private $scheduledMessageQueueService;

    public function __construct(ScheduledQueueServiceInterface $scheduledMessageQueueService)
    {
        $this->scheduledMessageQueueService = $scheduledMessageQueueService;
    }

    public function enqueueMessage(
        $payload,
        string $destination,
        DateTimeImmutable $deliverAt,
        int $priority
    ): void {
        $this->scheduledMessageQueueService->scheduleMessage(
            new Message(
                $payload,
                $destination,
                $deliverAt,
                $priority
            )
        );
    }
}
