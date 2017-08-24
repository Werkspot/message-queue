<?php

declare(strict_types=1);

namespace Werkspot\MessageQueue\Test\Unit\DeliveryQueue\Amqp;

use Hamcrest\Core\IsEqual;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use Werkspot\MessageQueue\DeliveryQueue\Amqp\AmqpProducer;
use Werkspot\MessageQueue\DeliveryQueue\Amqp\MessageIdGeneratorInterface;
use Werkspot\MessageQueue\Test\Unit\DeliveryQueue\MessageStub;

final class AmqpProducerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @test
     */
    public function theConnectionIsLazyAndIsNotCalledInTheConstructor(): void
    {
        $connection = Mockery::mock(AMQPLazyConnection::class);
        $idGenerator = Mockery::mock(MessageIdGeneratorInterface::class);
        new AmqpProducer($connection, $idGenerator);
        self::assertTrue(true);
    }

    /**
     * @test
     */
    public function theConnectionIsEstablishedWhenSendingAMessageAndMessageIsCorrectlySentToCorrectQueue(): void
    {
        $connection = Mockery::mock(AMQPLazyConnection::class);
        $channel = Mockery::mock(AMQPChannel::class);
        $queueName = 'foo';
        $message = new MessageStub('hi');

        $idGenerator = Mockery::mock(MessageIdGeneratorInterface::class);
        $generatedId = 'the-message-id';
        $idGenerator->shouldReceive('generateId')->twice()->andReturn($generatedId);

        $connection->shouldReceive('channel')->once()->andReturn($channel);

        $amqpMessage = new AMQPMessage(serialize($message));
        $amqpMessage->set('message_id', $generatedId);

        $channel->shouldReceive('queue_declare')->twice()->with($queueName, false, true, false, false);
        $channel->shouldReceive('basic_publish')->twice()->with(new IsEqual($amqpMessage), '', $queueName);

        $producer = new AmqpProducer($connection, $idGenerator);
        $producer->send($message, $queueName);
        $producer->send($message, $queueName);
    }
}
