<?php

namespace Werkspot\MessageQueue\Test\Unit;

use Mockery;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Werkspot\MessageQueue\DeliveryQueue\ConsumerInterface;
use Werkspot\MessageQueue\DeliveryQueue\MessageHandlerInterface;
use Werkspot\MessageQueue\DeliveryQueue\ProducerInterface;
use Werkspot\MessageQueue\Message\MessageInterface;

final class RabbitMqStub implements ProducerInterface, ConsumerInterface
{
    /**
     * @var AMQPMessage[]
     */
    private $queue = [];

    /**
     * @var MessageHandlerInterface
     */
    private $handler;

    public function __construct(MessageHandlerInterface $handler)
    {
        $this->handler = $handler;
    }

    /**
     * @param int $maxSeconds The maximum amount of seconds we consume, before returning
     */
    public function startConsuming(string $queueName, int $maxSeconds): void
    {
        /** @var AMQPMessage $amqpMessage */
        while ($amqpMessage = array_shift($this->queue)) {
            $this->handler->handle(unserialize($amqpMessage->getBody()));
        }
    }

    public function send(MessageInterface $message, string $queueName): void
    {
        $this->queue[] = $amqpMessage = new AMQPMessage(serialize($message));
        $amqpMessage->delivery_info['delivery_tag'] = 'this array key needs to be set';
        $amqpMessage->delivery_info['channel'] = $channelMock = Mockery::mock(AMQPChannel::class);
        $channelMock->shouldReceive('basic_ack');
    }

    /**
     * @return AMQPMessage[]
     */
    public function getAllMessages(): array
    {
        return $this->queue;
    }
}
