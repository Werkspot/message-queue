<?php

declare(strict_types=1);

namespace Werkspot\MessageQueue\DeliveryQueue;

use DateTime;
use ErrorException;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Werkspot\MessageQueue\Message\MessageInterface;

final class MessageHandler implements MessageHandlerInterface
{
    /**
     * @var MessageDeliveryServiceInterface
     */
    private $messageDeliveryService;

    /**
     * @var PersistenceClientInterface
     */
    private $persistenceClient;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var MessageInterface
     */
    private $message;

    public function __construct(
        MessageDeliveryServiceInterface $messageDeliveryService,
        PersistenceClientInterface $persistenceClient,
        LoggerInterface $logger = null
    ) {
        $this->messageDeliveryService = $messageDeliveryService;
        $this->persistenceClient = $persistenceClient;
        $this->logger = $logger ?? new NullLogger();
    }

    public function handle(MessageInterface $message): void
    {
        // There can be an edge case where we get a fatal/exception, and we go out of this flow and keep handlingMessage
        // on true, but in that case the flow is broken anyway, as handle() should normally handle all cases sanely.
        // If we're in the shutdown handler, the script is being killed anyway. So we don't care anymore.
        $this->initiateHandling($message);

        try {
            $this->process();
        } catch (Throwable $e) {
            $this->onError($e);
        } finally {
            $this->terminateHandling();
            // We clear the persistenceClient (entity manager), to ensure each message is handled without stale entities.
            // Because the workers run for a long time, and we have multiple workers running, we need to prevent
            // having old entities cached in memory. When other workers already updated the entity to a new state.
            // Otherwise we would use the old, out-dated state, and overwrite and stuff happened after it in the other
            // workers.
            $this->persistenceClient->reset();
        }
    }

    public function isHandlingMessage(): bool
    {
        return $this->message !== null;
    }

    public function shutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && $this->isHandlingMessage()) {
            $this->onError(new ErrorException($error['message'], 0, E_ERROR, $error['file'], $error['line']));
        }
    }

    public function onError(Throwable $error): void
    {
        // If we have a fatal error, we were not able to rollback the transaction, so we will need to do it here,
        // otherwise we cannot queue the command below, as it will be in a transaction that will never be committed
        $this->persistenceClient->rollbackTransaction();

        if (!is_subclass_of($this->message, MessageInterface::class)) {
            // This happens when we have a serialization problem in the queue for whatever reason (e.g. a class
            // was moved, or renamed). In that case it's not a Command, but an PHP_Incomplete_Class.
            // If we'd call handleFailure() for an Incomplete_Class we'd get a fatal error, this is not something we
            // want, because the worker will die. And although supervisor will restart it again, it's not good,
            // as supervisor will only restart it for an x amount of tries, before giving up, and we end up with
            // a queue that is stuck.
            // So in this case we can't log the error with the current logic. It's on the todo to just do an update
            // in the database directly here, e.g. 'update QueuedCommand set error = ... where id = .. ' but we are
            // working on the project restructure with a hard deadline and want to prioritize that above this.
            // At least the fix is there, just not the logging, which we'll do later.
            $logMessage = sprintf(
                'Cannot deliver queued message because it is not a MessageInterface %s: %s',
                $this->message->getId(),
                $error->getMessage()
            );

            $this->logger->error($logMessage);
            $this->message->fail($error);
            $this->persistenceClient->persistUndeliverableMessage($this->message, $logMessage);
        } else {
            $this->message->fail($error);

            try {
                $this->persistenceClient->persist($this->message);
            } catch (Exception $exception) {
                // It can happen that we have some problem with persisting most likely a uniqid constraint because of a
                // race condition or if we processed a message twice. In that case persist it in the
                // UnRequeueableMessage table so you can do monitoring on this and fix it.
                $this->persistenceClient->persistUndeliverableMessage($this->message, $exception->getMessage());

                throw $exception;
            }
        }
    }

    private function process(): void
    {
        $this->logger->info('Delivering message: ' . $this->getLogMessage($this->message));

        $this->messageDeliveryService->deliver($this->message);
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

    private function initiateHandling(MessageInterface $message): void
    {
        $this->message = $message;
    }

    private function terminateHandling(): void
    {
        $this->message = null;
    }
}
