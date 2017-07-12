<?php

namespace Werkspot\MessageQueue\Test\Integration\Queue\Amqp;

use DateTimeImmutable;
use ErrorException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Exception\AMQPProtocolConnectionException;
use PhpAmqpLib\Message\AMQPMessage;
use Werkspot\ApiLibrary\TestHelper\MockHelper;
use Werkspot\Instapro\Test\TestFramework\Integration\AbstractIntegrationTest;
use Werkspot\MessageQueue\DeliveryQueue\Amqp\AmqpConsumer;
use Werkspot\MessageQueue\DeliveryQueue\Amqp\AmqpMessageHandlerInterface;
use Werkspot\MessageQueue\DeliveryQueue\Amqp\AmqpProducer;
use Werkspot\MessageQueue\DeliveryQueue\Amqp\UuidMessageIdGenerator;
use Werkspot\MessageQueue\DeliveryQueue\ProducerInterface;
use Werkspot\MessageQueue\Message\Message;

/**
 * @large
 *
 * TODO when we extract the package to its own repo, make this extend PHPUnit_Framework_TestCase
 */
final class AmqpTest extends AbstractIntegrationTest
{
    use MockeryPHPUnitIntegration;

    /**
     * @var string
     */
    private static $queueName;

    /**
     * @beforeClass
     */
    public static function createTestQueue(): void
    {
        $client = self::createAmqpConnection();
        $channel = $client->channel();

        $channel->queue_delete(self::getQueueName());
        $channel->queue_declare(self::getQueueName(), false, true, false, false);
    }

    /**
     * @afterClass
     */
    public static function deleteTestQueue(): void
    {
        $client = self::createAmqpConnection();
        $channel = $client->channel();

        $channel->queue_delete(self::getQueueName());
    }

    /**
     * @test
     */
    public function happyFlow(): void
    {
        $messages = [
            'message1' => 1,
            'message2' => 2,
        ];

        $handler = new class($messages) implements AmqpMessageHandlerInterface {
            /**
             * @var array
             */
            private $messages;

            /**
             * @var bool
             */
            private $isHandlingMessage = false;

            /**
             * @var AmqpConsumer
             */
            private $consumer;

            public function __construct(array &$messages)
            {
                $this->messages = &$messages;
            }

            public function handle(AMQPMessage $amqpMessage): void
            {
                $this->isHandlingMessage = true;

                /** @var Message $message */
                $message = unserialize($amqpMessage->body);
                $channel = $amqpMessage->delivery_info['channel'];

                $this->messages[$message->getPayload()]--;

                if ($this->messages[$message->getPayload()] <= 0) {
                    $channel->basic_ack($amqpMessage->delivery_info['delivery_tag']);
                } else {
                    $channel->basic_nack($amqpMessage->delivery_info['delivery_tag'], false, true);
                }

                $this->isHandlingMessage = false;

                $this->stopIfWeCan();
            }

            public function setConsumer(AmqpConsumer $consumer): void
            {
                $this->consumer = $consumer;
            }

            public function isHandlingMessage(): bool
            {
                return $this->isHandlingMessage;
            }

            private function stopIfWeCan(): void
            {
                // Stop the client (and speed up the test, if everything is processed
                foreach ($this->messages as $value) {
                    if ($value != 0) {
                        return;
                    }
                }

                MockHelper::setProtectedProperty($this->consumer, 'exitSignalReceived', true);
            }
        };

        $consumer = new AmqpConsumer(self::createAmqpConnection(), $handler);
        $producer = self::createProducer();

        $handler->setConsumer($consumer);


        // Publish two test messages to the queue
        foreach ($messages as $key => $value) {
            $producer->send(new Message($key, 'destination', new DateTimeImmutable(), 1), self::getQueueName());
        }

        // Process the queue and make sure the two commands were executed
        $consumer->startConsuming(self::getQueueName(), 7);

        foreach ($messages as $key => $value) {
            self::assertSame(0, $value);
        }
    }

    /**
     * @test
     */
    public function usingWrongCredentialsThrowsException(): void
    {
        $connection = new AMQPLazyConnection(
            self::getRabbitHost(),
            self::getRabbitPort(),
            self::getRabbitUser(),
            'wrong-password'
        );

        $handler = Mockery::mock(AmqpMessageHandlerInterface::class);
        $consumer = new AmqpConsumer($connection, $handler);

        $this->expectException(AMQPProtocolConnectionException::class);
        $this->expectExceptionMessageRegExp('/ACCESS_REFUSED - Login was refused .*/');
        $consumer->startConsuming('commands', 1);
    }

    /**
     * @test
     */
    public function anExceptionIsThrownWhenConnectionCannotBeMade(): void
    {
        $connection = new AMQPLazyConnection(
            '127.0.0.999',
            self::getRabbitPort(),
            self::getRabbitUser(),
            self::getRabbitPassword()
        );

        $handler = Mockery::mock(AmqpMessageHandlerInterface::class);
        $consumer = new AmqpConsumer($connection, $handler);

        $this->expectException(ErrorException::class);
        $this->expectExceptionMessageRegExp('/.*getaddrinfo failed.*/');
        @$consumer->startConsuming('commands', 2);
    }

    private static function getQueueName(): string
    {
        if (!self::$queueName) {
            self::$queueName = 'testqueue_' . gethostname();
        }

        return self::$queueName;
    }

    private static function createProducer(): ProducerInterface
    {
        return new AmqpProducer(
            self::createAmqpConnection(),
            new UuidMessageIdGenerator()
        );
    }

    private static function createAmqpConnection(): AMQPLazyConnection
    {
        return new AMQPLazyConnection(
            self::getRabbitHost(),
            self::getRabbitPort(),
            self::getRabbitUser(),
            self::getRabbitPassword()
        );
    }

    // TODO when we extract the package to its own repo, make this use `return getenv('RABBITMQ_HOST');`
    private static function getRabbitHost(): string
    {
        return self::getParameter('rabbitmq.host');
    }

    // TODO when we extract the package to its own repo, make this use `return getenv('RABBITMQ_PORT');`
    private static function getRabbitPort(): int
    {
        $port = self::getParameter('rabbitmq.port');
        return $port > 0 ? $port : 5672;
    }

    // TODO when we extract the package to its own repo, make this use `return getenv('RABBITMQ_USER');`
    private static function getRabbitUser(): string
    {
        return self::getParameter('rabbitmq.user');
    }

    // TODO when we extract the package to its own repo, make this use `return getenv('RABBITMQ_PASSWORD');`
    private static function getRabbitPassword(): string
    {
        return self::getParameter('rabbitmq.password');
    }
}
