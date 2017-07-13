<?php

namespace Werkspot\MessageQueue\Test\Unit\DeliveryQueue;

use DateTimeImmutable;
use Exception;
use Hamcrest\Core\IsEqual;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Werkspot\MessageQueue\DeliveryQueue\Exception\UnrecoverablePersistenceException;
use Werkspot\MessageQueue\DeliveryQueue\MessageDeliveryServiceInterface;
use Werkspot\MessageQueue\DeliveryQueue\MessageHandler;
use Werkspot\MessageQueue\DeliveryQueue\PersistenceClientInterface;
use Werkspot\MessageQueue\Message\Message;
use Werkspot\MessageQueue\Message\MessageInterface;
use Werkspot\MessageQueue\Test\WithMessage;

final class MessageHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function assertMessageHandlerIsNotHandlingMessageAfterCreated(): void
    {
        $messageHandler = new MessageHandler(
            Mockery::mock(MessageDeliveryServiceInterface::class),
            Mockery::mock(PersistenceClientInterface::class)
        );

        self::assertFalse($messageHandler->isHandlingMessage());
    }

    /**
     * @test
     */
    public function thatTheMessageIsAcknowledgedIfHandledSuccessfully(): void
    {
        $message = new Message('payload', 'dummy destination', new DateTimeImmutable('now +1 day'), 1);

        $persistenceClientMock = Mockery::mock(PersistenceClientInterface::class);
        $persistenceClientMock->shouldReceive('reset')->once();

        $messageHandler = new MessageHandler(
            $this->getMessageDeliveryServiceThatHandlesMessageCorrectly($message),
            $persistenceClientMock
        );

        $messageHandler->handle($message);
        self::assertFalse($messageHandler->isHandlingMessage());
    }

    /**
     * @test
     */
    public function thatTheMessageFailureRollsbackTransactionAndRequeuesMessage(): void
    {
        $message = new Message('payload', 'dummy destination', new DateTimeImmutable('now +1 day'), 1);

        $exception = new Exception('something went wrong handling the message payload');
        $failedMessage = clone $message;
        $failedMessage->fail($exception);

        $persistenceClientMock = Mockery::mock(PersistenceClientInterface::class);
        $persistenceClientMock->shouldReceive('rollbackTransaction')->once();
        $persistenceClientMock->shouldReceive('persist')
            ->once()
            ->with(IsEqual::equalTo($failedMessage));
        $persistenceClientMock->shouldReceive('reset')->once();

        $messageHandler = new MessageHandler(
            $this->getMessageDeliveryServiceThatHandlesMessageWithException($message, $exception),
            $persistenceClientMock
        );

        $messageHandler->handle($message);
        self::assertFalse($messageHandler->isHandlingMessage());
    }

    /**
     * @test
     */
    public function thatThePersistenceFailureSendsMessageToUnRequeueableMessageList(): void
    {
        $message = new Message('payload', 'dummy destination', new DateTimeImmutable('now +1 day'), 1);

        $exception = new Exception('something went wrong handling the message payload');
        $failedMessage = clone $message;
        $failedMessage->fail($exception);

        $persistenceClientMock = Mockery::mock(PersistenceClientInterface::class);
        $persistenceClientMock->shouldReceive('rollbackTransaction')->once();
        $exceptionMsg = 'EntityManager was closed, stopping processing of messages';
        $persistenceClientMock->shouldReceive('persist')
            ->once()
            ->with(IsEqual::equalTo($failedMessage))
            ->andThrow(
                new UnrecoverablePersistenceException($exceptionMsg)
            );
        $persistenceClientMock->shouldReceive('persistUndeliverableMessage')
            ->once()
            ->with(IsEqual::equalTo($failedMessage), $exceptionMsg);
        $persistenceClientMock->shouldReceive('reset')->once();

        $messageHandler = new MessageHandler(
            $this->getMessageDeliveryServiceThatHandlesMessageWithException($message, $exception),
            $persistenceClientMock
        );

        $this->expectException(UnrecoverablePersistenceException::class);
        $messageHandler->handle($message);
        self::assertFalse($messageHandler->isHandlingMessage());
    }

    /**
     * @test
     */
    public function thatTheFailureToSendMessageToUnRequeueableMessageListBubblesUpLastException(): void
    {
        $message = new Message('payload', 'dummy destination', new DateTimeImmutable('now +1 day'), 1);

        $exception = new Exception('something went wrong handling the message payload');
        $failedMessage = clone $message;
        $failedMessage->fail($exception);

        $persistenceClientMock = Mockery::mock(PersistenceClientInterface::class);
        $persistenceClientMock->shouldReceive('rollbackTransaction')->once();
        $exceptionMsg = 'EntityManager was closed, stopping processing of messages';
        $persistenceClientMock->shouldReceive('persist')
            ->once()
            ->with(IsEqual::equalTo($failedMessage))
            ->andThrow(
                new UnrecoverablePersistenceException($exceptionMsg)
            );
        $persistenceClientMock->shouldReceive('persistUndeliverableMessage')
            ->once()
            ->with(IsEqual::equalTo($failedMessage), $exceptionMsg)
            ->andThrow(new Exception($lastExceptionMessage = 'Yet another DB exception.'));
        $persistenceClientMock->shouldReceive('reset')->once();

        $messageHandler = new MessageHandler(
            $this->getMessageDeliveryServiceThatHandlesMessageWithException($message, $exception),
            $persistenceClientMock
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($lastExceptionMessage);
        $messageHandler->handle($message);
        self::assertFalse($messageHandler->isHandlingMessage());
    }

    /**
     * @return MockInterface|MessageDeliveryServiceInterface
     */
    private function getMessageDeliveryServiceThatHandlesMessageCorrectly(MessageInterface $message): MessageDeliveryServiceInterface
    {
        $mock = $this->getDeliveryServiceMock();
        $mock->shouldReceive('deliver')->once()->with(WithMessage::equalTo($message));

        return $mock;
    }

    /**
     * @return MockInterface|MessageDeliveryServiceInterface
     */
    private function getMessageDeliveryServiceThatHandlesMessageWithException(MessageInterface $message, Exception $e): MessageDeliveryServiceInterface
    {
        $mock = $this->getDeliveryServiceMock();
        $mock->shouldReceive('deliver')->once()->with(new IsEqual($message))->andThrow($e);

        return $mock;
    }

    /**
     * @return MockInterface|MessageDeliveryServiceInterface
     */
    private function getDeliveryServiceMock(): MessageDeliveryServiceInterface
    {
        return Mockery::mock(MessageDeliveryServiceInterface::class);
    }
}
