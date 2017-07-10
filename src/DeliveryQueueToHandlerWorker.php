<?php

namespace Werkspot\MessageQueue;

use Werkspot\MessageQueue\DeliveryQueue\ConsumerInterface;

final class DeliveryQueueToHandlerWorker implements DeliveryQueueToHandlerWorkerInterface
{
    /**
     * @var ConsumerInterface
     */
    private $deliveryQueueMessageConsumer;

    /**
     * @var string
     */
    private $deliveryQueueName;

    public function __construct(
        ConsumerInterface $deliveryQueueMessageConsumer,
        string $deliveryQueueName
    ) {
        $this->deliveryQueueMessageConsumer = $deliveryQueueMessageConsumer;
        $this->deliveryQueueName = $deliveryQueueName;
    }

    public function startConsuming(int $maxExecutionTimeInSeconds): void
    {
        $this->deliveryQueueMessageConsumer->startConsuming($this->deliveryQueueName, $maxExecutionTimeInSeconds);
    }
}
