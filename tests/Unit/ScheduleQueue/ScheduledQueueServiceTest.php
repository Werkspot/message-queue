<?php

namespace Werkspot\MessageQueue\Test\Unit\ScheduledQueue;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Werkspot\MessageQueue\ScheduledQueue\Repository\MessageRepositoryInterface;
use Werkspot\MessageQueue\ScheduledQueue\ScheduledQueueService;
use Werkspot\MessageQueue\Test\Unit\DeliveryQueue\MessageStub;

final class ScheduledQueueServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var MockInterface|MessageRepositoryInterface
     */
    private $queuedMessageRepository;

    /**
     * @var ScheduledQueueService
     */
    private $messageQueueService;

    public function setUp(): void
    {
        $this->queuedMessageRepository = Mockery::mock(MessageRepositoryInterface::class);
        $this->messageQueueService = new ScheduledQueueService($this->queuedMessageRepository);
    }

    /**
     * @test
     */
    public function enqueueMessage_ShouldSaveTheMessageAndTriggerEvent(): void
    {
        $message = new MessageStub('payload');

        $this->queuedMessageRepository->shouldReceive('save')->once()->with($message);

        $this->messageQueueService->scheduleMessage($message);
    }

    /**
     * @test
     */
    public function dequeueMessage_ReturnsOneMessageWhenThereAreMessagesInTheQueue(): void
    {
        $message = new MessageStub('payload');
        $persistedMessage = clone $message;

        $this->queuedMessageRepository->shouldReceive('findMessagesToDeliver')->once()->with(1)->andReturn([$persistedMessage]);

        self::assertEquals([$message], $this->messageQueueService->findScheduledMessageList());
    }

    /**
     * @test
     */
    public function dequeueMessage_ReturnsEmptyArrayWhenThereAreNoMessagesInTheQueue(): void
    {
        $this->queuedMessageRepository->shouldReceive('findMessagesToDeliver')->once()->with(1)->andReturn([]);

        self::assertEmpty($this->messageQueueService->findScheduledMessageList());
    }
}
