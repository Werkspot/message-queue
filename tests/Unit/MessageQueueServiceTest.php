<?php

namespace Werkspot\MessageQueue\Test\Unit;

use DateTime;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Werkspot\MessageQueue\Message\Message;
use Werkspot\MessageQueue\MessageQueueService;
use Werkspot\MessageQueue\ScheduledQueue\ScheduledQueueServiceInterface;
use Werkspot\MessageQueue\Test\WithMessage;

final class MessageQueueServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var MockInterface|ScheduledQueueServiceInterface
     */
    private $scheduledMessageQueueService;

    /**
     * @var MessageQueueService
     */
    private $messageQueueService;

    public function setUp(): void
    {
        $this->scheduledMessageQueueService = Mockery::mock(ScheduledQueueServiceInterface::class);
        $this->messageQueueService = new MessageQueueService($this->scheduledMessageQueueService);
    }

    /**
     * @test
     */
    public function enqueueMessage_SchedulesTheMessage(): void
    {
        $payload = 'payload';
        $destination = 'destination';
        $deliveryTime = new DateTime('now +1 day');
        $priority = 1;

        $this->scheduledMessageQueueService->shouldReceive('scheduleMessage')
            ->once()
            ->with(WithMessage::equalTo(new Message($payload, $destination, $deliveryTime, $priority)));

        $this->messageQueueService->enqueueMessage($payload, $destination, $deliveryTime, $priority);
    }
}
