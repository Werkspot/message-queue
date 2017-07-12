<?php

namespace Werkspot\MessageQueue\Test\Unit;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use Werkspot\MessageQueue\DeliveryQueue\Amqp\AmqpMessageHandlerInterface;
use Werkspot\MessageQueue\DeliveryQueueToHandlerWorker;
use Werkspot\MessageQueue\Message\Message;
use Werkspot\MessageQueue\MessageQueueService;
use Werkspot\MessageQueue\ScheduledQueue\ScheduledQueueService;
use Werkspot\MessageQueue\ScheduledQueue\ScheduledQueueServiceInterface;
use Werkspot\MessageQueue\ScheduledQueueToDeliveryQueueWorker;
use Werkspot\MessageQueue\Test\WithMessage;

class MessageQueueServiceTest extends TestCase
{
    const PAYLOAD = 'some payload';
    const HANDLER_METHOD = 'handle';
    const QUEUE_NAME = 'some_queue';

    /**
     * @var RabbitMqStub
     */
    private $rabbitMqStub;

    /**
     * @var ScheduledMessageRepositoryStub
     */
    private $queuedMessageRepositoryStub;

    /**
     * @var MessageQueueService
     */
    private $messageQueueService;

    /**
     * @var ScheduledQueueService
     */
    private $scheduledQueueService;

    /**
     * @var MockInterface|AmqpMessageHandlerInterface
     */
    private $amqpMessageHandlerMock;

    /**
     * @var ScheduledQueueToDeliveryQueueWorker
     */
    private $scheduledQueueToDeliveryQueueWorker;

    /**
     * @var DeliveryQueueToHandlerWorker
     */
    private $deliveryQueueToHandlerWorker;

    public function setUp(): void
    {
        $this->queuedMessageRepositoryStub = new ScheduledMessageRepositoryStub();
        $this->scheduledQueueService = new ScheduledQueueService($this->queuedMessageRepositoryStub);
        $this->messageQueueService = new MessageQueueService($this->scheduledQueueService);

        $this->amqpMessageHandlerMock = Mockery::mock(AmqpMessageHandlerInterface::class);
        $this->rabbitMqStub = new RabbitMqStub($this->amqpMessageHandlerMock);

        $this->scheduledQueueToDeliveryQueueWorker = new ScheduledQueueToDeliveryQueueWorker(
            $this->scheduledQueueService,
            Mockery::mock(EntityManagerInterface::class),
            $this->rabbitMqStub,
            self::QUEUE_NAME
        );

        $this->deliveryQueueToHandlerWorker = new DeliveryQueueToHandlerWorker($this->rabbitMqStub, self::QUEUE_NAME);
    }

    /**
     * @test
     */
    public function enqueueMessage_SchedulesTheMessage(): void
    {
        $payload = 'payload';
        $destination = 'destination';
        $deliveryTime = new DateTimeImmutable('now +1 day');
        $priority = 1;

        $scheduledMessageQueueService = Mockery::mock(ScheduledQueueServiceInterface::class);
        $scheduledMessageQueueService->shouldReceive('scheduleMessage')
            ->once()
            ->with(WithMessage::equalTo(new Message($payload, $destination, $deliveryTime, $priority)));

        $messageQueueService = new MessageQueueService($scheduledMessageQueueService);
        $messageQueueService->enqueueMessage($payload, $destination, $deliveryTime, $priority);

        self::assertTrue(true);
    }

    /**
     * @test
     */
    public function enqueueMessage_DeliversMessageToMessageHandler(): void
    {
        $this->amqpMessageHandlerMock->shouldReceive(self::HANDLER_METHOD)
            ->once()
            ->with(Mockery::on(function (AMQPMessage $AMQPMessage) {
                /** @var Message $message */
                $message = unserialize($AMQPMessage->getBody());
                return $message->getPayload() === self::PAYLOAD;
            }));

        $this->dispatchQueuedMessage(self::PAYLOAD);

        self::assertCount(0, $this->queuedMessageRepositoryStub->findAll());
    }

    private function dispatchQueuedMessage($payload): void
    {
        $this->messageQueueService->enqueueMessage($payload, 'dummy destination', new DateTimeImmutable(), 1);

        self::assertCount(1, $this->queuedMessageRepositoryStub->findAll(), 'Message did not reach the ScheduleMessageQueue');

        $this->scheduledQueueToDeliveryQueueWorker->moveMessageBatch(1);

        self::assertCount(1, $this->rabbitMqStub->getAllMessages(), 'Message did not reach the DeliveryMessageQueue');
        self::assertCount(0, $this->queuedMessageRepositoryStub->findAll(), 'Message was not removed from ScheduleMessageQueue');

        $this->deliveryQueueToHandlerWorker->startConsuming(1);

        self::assertCount(0, $this->rabbitMqStub->getAllMessages(), 'Message was not removed from DeliveryMessageQueue');
    }
}
