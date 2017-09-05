<?php

namespace Werkspot\MessageQueue\Test\Integration\DeliveryQueue\Amqp;

use DateTimeImmutable;
use ErrorException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Exception\AMQPProtocolConnectionException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\TestCase;
use Werkspot\ApiLibrary\TestHelper\MockHelper;
use Werkspot\MessageQueue\DeliveryQueue\Amqp\AmqpConsumer;
use Werkspot\MessageQueue\DeliveryQueue\Amqp\AmqpMessageHandlerInterface;
use Werkspot\MessageQueue\DeliveryQueue\Amqp\AmqpProducer;
use Werkspot\MessageQueue\DeliveryQueue\Amqp\UuidMessageIdGenerator;
use Werkspot\MessageQueue\DeliveryQueue\ProducerInterface;
use Werkspot\MessageQueue\Message\Message;

/**
 * @large
 */
final class AmqpTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const DEFAULT_RABBITMQ_PORT = 5672;

    private const PRIORITY_LOWEST = 1;
    private const PRIORITY_LOW = 3;
    private const PRIORITY_NORMAL = 5;
    private const PRIORITY_HIGH = 7;
    private const PRIORITY_URGENT = 9;

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
        $channel->queue_declare(
            self::getQueueName(),
            false,
            true,
            false,
            false,
            false,
            new AMQPTable([
                'x-max-priority' => 10
            ])
        );
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
            'message1' => ['tries' => 1, 'priority' => self::PRIORITY_LOWEST],
            'message2' => ['tries' => 2, 'priority' => self::PRIORITY_LOW], // try this once again with a NACK
            'message3' => ['tries' => 1, 'priority' => self::PRIORITY_URGENT],
            'message4' => ['tries' => 1, 'priority' => self::PRIORITY_NORMAL],
            'message5' => ['tries' => 1, 'priority' => self::PRIORITY_LOWEST],
            'message6' => ['tries' => 1, 'priority' => self::PRIORITY_HIGH],
        ];

        $expectedDeliveryOrder = [
            self::PRIORITY_URGENT,
            self::PRIORITY_HIGH,
            self::PRIORITY_NORMAL,
            self::PRIORITY_LOW,
            self::PRIORITY_LOW, // this is the retrying
            self::PRIORITY_LOWEST,
            self::PRIORITY_LOWEST,
        ];

        $handler = new class($messages) implements AmqpMessageHandlerInterface {

            /**
             * @var array
             */
            private $messages;

            private $isHandlingMessage = false;

            /**
             * @var AmqpConsumer
             */
            private $consumer;

            /**
             * @var int[]
             */
            public $handledPriorities = [];

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

                $this->messages[$message->getPayload()]['tries']--;
                $this->handledPriorities[] = $message->getPriority();

                if ($this->messages[$message->getPayload()]['tries'] <= 0) {
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
                    if ($value['tries'] != 0) {
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
            $producer->send(new Message($key, 'destination', new DateTimeImmutable(), $value['priority']), self::getQueueName());
        }

        // Process the queue and make sure the two commands were executed
        $consumer->startConsuming(self::getQueueName(), 7);

        foreach ($messages as $key => $value) {
            self::assertSame(0, $value['tries']);
        }

        self::assertSame($expectedDeliveryOrder, $handler->handledPriorities);
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

    private static function getRabbitHost(): string
    {
        return getenv('RABBITMQ_HOST');
    }

    private static function getRabbitPort(): int
    {
        $port = getenv('RABBITMQ_PORT');
        return $port ?: self::DEFAULT_RABBITMQ_PORT;
    }

    private static function getRabbitUser(): string
    {
        return getenv('RABBITMQ_USER');
    }

    private static function getRabbitPassword(): string
    {
        return getenv('RABBITMQ_PASSWORD');
    }
}
