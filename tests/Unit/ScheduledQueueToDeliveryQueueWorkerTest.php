<?php

namespace Werkspot\MessageQueue\Test\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Werkspot\MessageQueue\DeliveryQueue\ProducerInterface;
use Werkspot\MessageQueue\ScheduledQueue\ScheduledQueueServiceInterface;
use Werkspot\MessageQueue\ScheduledQueueToDeliveryQueueWorker;
use Werkspot\MessageQueue\Test\Unit\DeliveryQueue\MessageStub;

final class ScheduledQueueToDeliveryQueueWorkerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    const QUEUE_NAME = 'queue';

    /**
     * @var MockInterface|ScheduledQueueServiceInterface
     */
    private $scheduledMessageQueueServiceMock;

    /**
     * @var MockInterface|EntityManagerInterface
     */
    private $entityManagerMock;

    /**
     * @var MockInterface|ProducerInterface
     */
    private $deliveryQueueMessageProducerMock;

    /**
     * @var MockInterface|LoggerInterface
     */
    private $loggerMock;

    /**
     * @var ScheduledQueueToDeliveryQueueWorker
     */
    private $scheduledQueueToDeliveryQueueWorker;

    public function setUp(): void
    {
        $this->scheduledMessageQueueServiceMock = Mockery::mock(ScheduledQueueServiceInterface::class);
        $this->entityManagerMock = Mockery::mock(EntityManagerInterface::class);
        $this->deliveryQueueMessageProducerMock = Mockery::mock(ProducerInterface::class);
        $this->loggerMock = Mockery::mock(LoggerInterface::class);
        $this->scheduledQueueToDeliveryQueueWorker = new ScheduledQueueToDeliveryQueueWorker(
            $this->scheduledMessageQueueServiceMock,
            $this->entityManagerMock,
            $this->deliveryQueueMessageProducerMock,
            self::QUEUE_NAME,
            $this->loggerMock
        );
    }

    /**
     * @test
     */
    public function moveMessageBatch_DoesntTryToMoveMessagesIfThereAreNoMessages(): void
    {
        $batchSize = 5;

        $this->scheduledMessageQueueServiceMock->shouldReceive('findScheduledMessageList')
            ->once()
            ->with($batchSize)
            ->andReturn([]);

        $this->deliveryQueueMessageProducerMock->shouldNotReceive('send');

        $this->scheduledQueueToDeliveryQueueWorker->moveMessageBatch($batchSize);
    }

    /**
     * @test
     */
    public function moveMessageBatch_TriesToMoveAllMessagesRetrievedFromScheduleQueue(): void
    {
        $batchSize = 5;

        $this->scheduledMessageQueueServiceMock->shouldReceive('findScheduledMessageList')
            ->once()
            ->with($batchSize)
            ->andReturn(
                [
                    new MessageStub('payload'),
                    new MessageStub('payload'),
                    new MessageStub('payload'),
                    new MessageStub('payload'),
                    new MessageStub('payload'),
                ]
            );

        $this->loggerMock->shouldReceive('info')->times($batchSize * 2);
        $this->deliveryQueueMessageProducerMock->shouldReceive('send')->times($batchSize);
        $this->scheduledMessageQueueServiceMock->shouldReceive('unscheduleMessage')->times($batchSize);
        $this->entityManagerMock->shouldReceive('flush')->times($batchSize);

        $this->scheduledQueueToDeliveryQueueWorker->moveMessageBatch($batchSize);
    }

    /**
     * @test
     */
    public function moveMessageBatch_IfThereIsAnErrorLogsItAndContinues(): void
    {
        $batchSize = 5;

        $this->scheduledMessageQueueServiceMock->shouldReceive('findScheduledMessageList')
            ->once()
            ->with($batchSize)
            ->andReturn(
                [
                    new MessageStub('payload'),
                    new MessageStub('payload'),
                    new MessageStub('payload'),
                    new MessageStub('payload'),
                    new MessageStub('payload'),
                ]
            );

        $this->deliveryQueueMessageProducerMock->shouldReceive('send')->times(2);
        $this->deliveryQueueMessageProducerMock->shouldReceive('send')
            ->once()
            ->andThrow(new Exception('An error sending message to delivery queue.'));
        $this->deliveryQueueMessageProducerMock->shouldReceive('send')->times(2);
        $this->scheduledMessageQueueServiceMock->shouldReceive('unscheduleMessage')->times($batchSize - 1);
        $this->loggerMock->shouldReceive('info')->times($batchSize);
        $this->loggerMock->shouldReceive('error')->times($batchSize);

        $this->scheduledQueueToDeliveryQueueWorker->moveMessageBatch($batchSize);
    }
}
