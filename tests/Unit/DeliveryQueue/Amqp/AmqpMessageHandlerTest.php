<?php

namespace Werkspot\MessageQueue\Test\Unit\DeliveryQueue\Amqp;

use DateTimeImmutable;
use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use stdClass;
use Werkspot\MessageQueue\DeliveryQueue\Amqp\AmqpMessageHandler;
use Werkspot\MessageQueue\DeliveryQueue\Exception\UnrecoverablePersistenceException;
use Werkspot\MessageQueue\DeliveryQueue\MessageHandlerInterface;
use Werkspot\MessageQueue\DeliveryQueue\PersistenceClientInterface;
use Werkspot\MessageQueue\Message\Message;
use Werkspot\MessageQueue\Test\ReflectionHelper;
use Werkspot\MessageQueue\Test\WithMessage;

final class AmqpMessageHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var AmqpMessageHandler
     */
    private $amqpMessageHandler;

    /**
     * @var MockInterface|MessageHandlerInterface
     */
    private $messageHandlerMock;

    /**
     * @var MockInterface|CacheInterface
     */
    private $cacheMock;

    /**
     * @var MockInterface|PersistenceClientInterface
     */
    private $persistenceClientMock;

    public function setUp(): void
    {
        $this->messageHandlerMock = Mockery::mock(MessageHandlerInterface::class);
        $this->cacheMock = Mockery::mock(CacheInterface::class);
        $this->persistenceClientMock = Mockery::mock(PersistenceClientInterface::class);

        $this->amqpMessageHandler = new AmqpMessageHandler(
            $this->messageHandlerMock,
            $this->cacheMock,
            $this->persistenceClientMock
        );
        $this->preventRegisteringTheShutDownHandler($this->amqpMessageHandler);
    }

    /**
     * @test
     */
    public function handle_IfMessageCanNotBeClaimedDoesNotHandleIt(): void
    {
        $amqpMessageMock = Mockery::mock(AMQPMessage::class);
        $amqpMessageMock->shouldReceive('has')->once()->with('message_id')->andReturn(false);
        $amqpMessageMock->body = 'message body';

        $this->expectAmqpMessageIsAcknowledged($amqpMessageMock);
        $this->persistenceClientMock->shouldReceive('persistUndeliverableMessage')
            ->once()
            ->with($amqpMessageMock->body, AmqpMessageHandler::UNPACKING_EXCEPTION_MSG_UNSERIALIZABLE_CONTENT);

        $this->amqpMessageHandler->handle($amqpMessageMock);
    }

    /**
     * @test
     */
    public function handle_IfMessageIsAlreadyClaimedDoesNotHandleIt(): void
    {
        $amqpMessageMock = Mockery::mock(AMQPMessage::class);
        $amqpMessageMock->shouldReceive('has')->once()->with('message_id')->andReturn(true);
        $amqpMessageMock->shouldReceive('get')->once()->with('message_id')->andReturn(1);

        $amqpMessageMock->body = serialize(new Message('payload', 'destination', new DateTimeImmutable(), 1));

        $this->cacheMock->shouldReceive('has')->once()->andReturn(true);

        $this->expectAmqpMessageIsAcknowledged($amqpMessageMock);

        $this->amqpMessageHandler->handle($amqpMessageMock);
    }

    /**
     * @test
     *
     * @dataProvider invalidBodyProvider
     */
    public function handle_IfMessageIsInvalidPersistsItToUndeliverableMessages(string $body, string $errorMsg): void
    {
        $amqpMessageMock = Mockery::mock(AMQPMessage::class);
        $amqpMessageMock->shouldReceive('has')->once()->with('message_id')->andReturn(true);
        $amqpMessageMock->shouldReceive('get')->once()->with('message_id')->andReturn(1);

        $this->cacheMock->shouldReceive('has')->once()->andReturn(false);
        $this->cacheMock->shouldReceive('set')
            ->once()
            ->with(Mockery::type('string'), true, AmqpMessageHandler::CLAIM_TTL);

        $amqpMessageMock->body = $body;

        $this->persistenceClientMock->shouldReceive('persistUndeliverableMessage')
            ->once()
            ->with($body, $errorMsg);

        $this->expectAmqpMessageIsAcknowledged($amqpMessageMock);

        $this->amqpMessageHandler->handle($amqpMessageMock);
    }

    public function invalidBodyProvider(): array
    {
        return [
            'rubbish' => ['jahsbdijhasdb', AmqpMessageHandler::UNPACKING_EXCEPTION_MSG_UNSERIALIZABLE_CONTENT],
            'invalid class' => [serialize(new stdClass()), AmqpMessageHandler::UNPACKING_EXCEPTION_MSG_UNEXPECTED_TYPE],
        ];
    }

    /**
     * @test
     */
    public function handle_MessageIsHandledSuccessfullyAndAcknowledged(): void
    {
        $amqpMessageMock = Mockery::mock(AMQPMessage::class);
        $amqpMessageMock->shouldReceive('has')->once()->with('message_id')->andReturn(true);
        $amqpMessageMock->shouldReceive('get')->once()->with('message_id')->andReturn(1);

        $this->cacheMock->shouldReceive('has')->once()->andReturn(false);
        $this->cacheMock->shouldReceive('set')
            ->once()
            ->with(Mockery::type('string'), true, AmqpMessageHandler::CLAIM_TTL);

        $message = new Message('payload', 'destination', new DateTimeImmutable(), 1);
        $amqpMessageMock->body = serialize($message);

        $this->messageHandlerMock->shouldReceive('handle')->once()->with(WithMessage::equalTo($message));

        $this->expectAmqpMessageIsAcknowledged($amqpMessageMock);

        $this->amqpMessageHandler->handle($amqpMessageMock);
    }

    /**
     * @test
     *
     * @expectedException \Werkspot\MessageQueue\DeliveryQueue\Exception\UnrecoverablePersistenceException
     */
    public function handle_WhenMessageHandlingThrowsExpectedExceptionMessageIsAcknowledgedAndExceptionIsNotStopped(): void
    {
        $amqpMessageMock = Mockery::mock(AMQPMessage::class);
        $amqpMessageMock->shouldReceive('has')->once()->with('message_id')->andReturn(true);
        $amqpMessageMock->shouldReceive('get')->once()->with('message_id')->andReturn(1);

        $this->cacheMock->shouldReceive('has')->once()->andReturn(false);
        $this->cacheMock->shouldReceive('set')
            ->once()
            ->with(Mockery::type('string'), true, AmqpMessageHandler::CLAIM_TTL);

        $message = new Message('payload', 'destination', new DateTimeImmutable(), 1);
        $amqpMessageMock->body = serialize($message);

        $this->messageHandlerMock->shouldReceive('handle')
            ->once()
            ->with(WithMessage::equalTo($message))
            ->andThrow(new UnrecoverablePersistenceException());

        $this->expectAmqpMessageIsAcknowledged($amqpMessageMock);

        $this->amqpMessageHandler->handle($amqpMessageMock);
    }

    /**
     * @test
     *
     * @expectedException \Exception
     * @expectedExceptionMessage some_error
     */
    public function handle_WhenMessageHandlingThrowsUnexpectedExceptionMessageIsNotAcknowledgedAndExceptionIsBubbled(): void
    {
        $amqpMessageMock = Mockery::mock(AMQPMessage::class);
        $amqpMessageMock->shouldReceive('has')->once()->with('message_id')->andReturn(true);
        $amqpMessageMock->shouldReceive('get')->once()->with('message_id')->andReturn(1);

        $this->cacheMock->shouldReceive('has')->once()->andReturn(false);
        $this->cacheMock->shouldReceive('set')
            ->once()
            ->with(Mockery::type('string'), true, AmqpMessageHandler::CLAIM_TTL);

        $message = new Message('payload', 'destination', new DateTimeImmutable(), 1);
        $amqpMessageMock->body = serialize($message);

        $this->messageHandlerMock->shouldReceive('handle')
            ->once()
            ->with(WithMessage::equalTo($message))
            ->andThrow(new Exception('some_error'));

        $this->amqpMessageHandler->handle($amqpMessageMock);
    }

    private function expectAmqpMessageIsAcknowledged(MockInterface $amqpMessageMock): void
    {
        $delivery_tag = 'delivery_tag';

        $amqpChannelMock = Mockery::mock(AMQPChannel::class);
        $amqpChannelMock->shouldReceive('basic_ack')->once()->with($delivery_tag);

        $amqpMessageMock->delivery_info['delivery_tag'] = $delivery_tag;
        $amqpMessageMock->delivery_info['channel'] = $amqpChannelMock;
    }

    private function preventRegisteringTheShutDownHandler(AmqpMessageHandler $handler): void
    {
        // If we do the register_shutdown_handler phpunit will not like it... so prevent this
        ReflectionHelper::setProtectedProperty($handler, 'hasRegisteredShutdownHandler', true);
    }
}
