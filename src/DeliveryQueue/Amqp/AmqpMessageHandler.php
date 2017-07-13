<?php

namespace Werkspot\MessageQueue\DeliveryQueue\Amqp;

use DateTime;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Werkspot\MessageQueue\DeliveryQueue\Exception\AlreadyClaimedException;
use Werkspot\MessageQueue\DeliveryQueue\Exception\CanNotClaimException;
use Werkspot\MessageQueue\DeliveryQueue\Exception\UnrecoverablePersistenceException;
use Werkspot\MessageQueue\DeliveryQueue\MessageHandlerInterface;
use Werkspot\MessageQueue\DeliveryQueue\PersistenceClientInterface;
use Werkspot\MessageQueue\Message\MessageInterface;

final class AmqpMessageHandler implements AmqpMessageHandlerInterface
{
    const CLAIM_TTL = 3600;
    const UNPACKING_EXCEPTION_MSG_UNSERIALIZABLE_CONTENT = 'It was not possible to unserialize the AmqpMessage contents.';
    const UNPACKING_EXCEPTION_MSG_UNEXPECTED_TYPE = 'The AmqpMessage content is not a MessageQueue MessageInterface.';

    /**
     * @var MessageHandlerInterface
     */
    private $handler;

    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PersistenceClientInterface
     */
    private $persistenceClient;

    /**
     * @var bool
     */
    private $hasRegisteredShutdownHandler = false;

    /**
     * @var AMQPMessage
     */
    private $amqpMessage;

    public function __construct(
        MessageHandlerInterface $handler,
        CacheInterface $cache,
        PersistenceClientInterface $persistenceClient,
        LoggerInterface $logger = null
    ) {
        $this->handler = $handler;
        $this->cache = $cache;
        $this->persistenceClient = $persistenceClient;
        $this->logger = $logger ?? new NullLogger();
    }

    public function handle(AMQPMessage $amqpMessage): void
    {
        $this->initiateHandling($amqpMessage);
        $this->registerShutdownHandler();

        // RabbitMQ can and WILL send duplicate messages to clients. You are recommended to handle message in an
        // idempotent way, but for us this is not really possible, because sending out emails is never idempotent.
        // So we are doing the next best thing, deduplicating them.
        //
        // When deduplicating them we should do it as early as possible and in the most performing way as possible to
        // prevent race conditions where 2 consumers get the message at near the same time. So make sure we deduplicate
        // as soon as possible to keep the duplicate executing at a minimum.
        //
        // See: https://www.rabbitmq.com/reliability.html#consumer
        //  > [..] messages can be duplicated, and consumers must be prepared to handle them. If possible, ensure that
        //  > your consumers handle messages in an idempotent way rather than explicitly deal with deduplication.

        try {
            $this->claim($amqpMessage);
        } catch (AlreadyClaimedException $e) {
            $this->logAlreadyClaimedMessage($amqpMessage);
            // If we already processed it, we should still ack it here, otherwise rabbit will try to do it again, which
            // is exactly what we don't want.
            $this->acknowledgeMessage($amqpMessage);
            $this->terminateHandling();
            return;
        } catch (CanNotClaimException $e) {
            $this->logger->notice($e->getMessage());
            // if we don't have a message ID we can not claim it nor deduplicate it so
            // we should always process it, just to be sure we don't lose a message
        }

        try {
            $this->handler->handle($this->unpack($amqpMessage));
            $this->acknowledgeMessage($amqpMessage);
        } catch (AmqpMessageUnpackingException $e) {
            $this->logger->warning($e->getMessage());
            $this->persistenceClient->persistUndeliverableMessage($amqpMessage->body, $e->getMessage());
            $this->acknowledgeMessage($amqpMessage);
        } catch (UnrecoverablePersistenceException $e) {
            $this->logger->warning($e->getMessage());
            $this->acknowledgeMessage($amqpMessage);
            throw $e;
        } finally {
            $this->terminateHandling();
        }
        // We do not acknowledge the amqp msg when there was any other exception because if we could not put the msg
        // back in the scheduled queue, we would lose the message. This way it stays in rabbitMq and is tried again.
    }

    public function shutdownHandler(): void
    {
        if (!$this->isHandlingMessage()) {
            return;
        }

        $this->handler->shutdown();

        $this->acknowledgeMessage($this->amqpMessage);
    }

    private function registerShutdownHandler(): void
    {
        // The only way to catch (all) fatal errors is to have a shutdownHandler
        // This piece of code for example will not throw a Throwable, but just kill the php script:
        // ```
        //   $client = new SoapClient(null, ['location' => "http://localhost/soap.php", 'uri' => "http://test-uri/"]);
        //   $client->__setSoapHeaders([0 => null]);
        // ```
        //
        // But this is a big problem, because in that case we would not send an 'ACK' back to rabbit, as the fatal
        // error would just kill the php script, so rabbit would try again with the next available worker (as the
        // connection was hard interrupted).
        //
        // So if it fails again in the next worker with a fatal error again we get an endless retry loop in rabbit
        // which will basically break the queue, as nothing else is processed except for endlessly retrying the fatal.
        //
        // To remedy this we register the shutdown handler, we add the fatal error message to the command, and store
        // it in the queue to try again after the normal retry period, finally we send the ACK to rabbit, as we've
        // dealt with it properly (we will retry it in the future, and have logged the error nicely in the queue).
        if (!$this->hasRegisteredShutdownHandler) {
            register_shutdown_function([$this, 'shutdownHandler']);
            $this->hasRegisteredShutdownHandler = true;
        }
    }

    /**
     * @throws AlreadyClaimedException
     * @throws CanNotClaimException
     */
    private function claim(AMQPMessage $amqpMessage): void
    {
        if (!$amqpMessage->has('message_id')) {
            throw new CanNotClaimException(
                'Could not claim AMQP message because it does not have a message ID. Message body: '
                . $amqpMessage->body
            );
        }

        $cacheKey = 'msg_handled.' . $amqpMessage->get('message_id');

        if ($this->cache->has($cacheKey)) {
            throw new AlreadyClaimedException('AMQP message has already been claimed, with cache key ' . $cacheKey);
        }

        $this->cache->set($cacheKey, true, self::CLAIM_TTL);
    }

    private function acknowledgeMessage(AMQPMessage $amqpMessage): void
    {
        $channel = $amqpMessage->delivery_info['channel'];
        $channel->basic_ack($amqpMessage->delivery_info['delivery_tag']);
    }

    private function unpack(AMQPMessage $amqpMessage): MessageInterface
    {
        $result = @unserialize($amqpMessage->body);

        if ($result === false) {
            throw new AmqpMessageUnpackingException(self::UNPACKING_EXCEPTION_MSG_UNSERIALIZABLE_CONTENT);
        }

        if (! $result instanceof MessageInterface) {
            throw new AmqpMessageUnpackingException(self::UNPACKING_EXCEPTION_MSG_UNEXPECTED_TYPE);
        }

        return $result;
    }

    private function getLogMessage(MessageInterface $message): string
    {
        return $message->getId()
            . ', ' . $message->getDestination()
            . ', ' . $message->getDeliverAt()->format(DateTime::ATOM)
            . ', ' . $message->getCreatedAt()->format(DateTime::ATOM)
            . ', ' . $message->getUpdatedAt()->format(DateTime::ATOM)
            . ', ' . $message->getTries()
            . ', ' . $message->getPriority()
            . ', ' . $this->getPayloadType($message);
    }

    private function getPayloadType(MessageInterface $message): string
    {
        if (is_object($message->getPayload())) {
            return get_class($message->getPayload());
        }

        if (is_array($message->getPayload())) {
            return 'array';
        }

        return $message->getPayload();
    }

    /**
     * @param AMQPMessage $amqpMessage
     *
     */
    private function logAlreadyClaimedMessage(AMQPMessage $amqpMessage): void
    {
        $queuedMessage = $this->unpack($amqpMessage);

        $message = 'Queue message was already claimed: ' . $this->getLogMessage($queuedMessage);

        $this->logger->notice($message);
    }

    private function initiateHandling(AMQPMessage $amqpMessage): void
    {
        $this->amqpMessage = $amqpMessage;
    }

    private function terminateHandling(): void
    {
        $this->amqpMessage = null;
    }

    private function isHandlingMessage(): bool
    {
        return $this->amqpMessage !== null;
    }
}
