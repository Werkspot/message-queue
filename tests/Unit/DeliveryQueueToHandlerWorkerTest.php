<?php

namespace Werkspot\MessageQueue\Test\Unit;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Werkspot\MessageQueue\DeliveryQueue\ConsumerInterface;
use Werkspot\MessageQueue\DeliveryQueueToHandlerWorker;

final class DeliveryQueueToHandlerWorkerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    const QUEUE_NAME = 'queue';
    const MAX_EXECUTION_TIME = 10;

    /**
     * @var MockInterface|ConsumerInterface
     */
    private $consumerMock;

    /**
     * @var DeliveryQueueToHandlerWorker
     */
    private $deliveryQueueToHandlerWorker;

    public function setUp(): void
    {
        $this->consumerMock = Mockery::mock(ConsumerInterface::class);
        $this->deliveryQueueToHandlerWorker = new DeliveryQueueToHandlerWorker($this->consumerMock, self::QUEUE_NAME);
    }

    /**
     * @test
     */
    public function startConsuming_StartsTheConsumer(): void
    {
        $this->consumerMock->shouldReceive('startConsuming')->once()->with(self::QUEUE_NAME, self::MAX_EXECUTION_TIME);
        $this->deliveryQueueToHandlerWorker->startConsuming(self::MAX_EXECUTION_TIME);
    }
}
