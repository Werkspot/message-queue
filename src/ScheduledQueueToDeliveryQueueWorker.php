<?php

namespace Werkspot\MessageQueue;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Werkspot\MessageQueue\DeliveryQueue\ProducerInterface;
use Werkspot\MessageQueue\Message\MessageInterface;
use Werkspot\MessageQueue\ScheduledQueue\ScheduledQueueServiceInterface;

final class ScheduledQueueToDeliveryQueueWorker implements ScheduledQueueToDeliveryQueueWorkerInterface
{
    /**
     * @var ScheduledQueueServiceInterface
     */
    private $scheduledMessageService;

    /**
     * @var ProducerInterface
     */
    private $deliveryQueueMessageProducer;

    /**
     * @var string
     */
    private $deliveryQueueName;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(
        ScheduledQueueServiceInterface $scheduledMessageService,
        EntityManagerInterface $entityManager,
        ProducerInterface $deliveryQueueMessageProducer,
        string $deliveryQueueName,
        LoggerInterface $logger = null
    ) {
        $this->scheduledMessageService = $scheduledMessageService;
        $this->entityManager = $entityManager;
        $this->deliveryQueueMessageProducer = $deliveryQueueMessageProducer;
        $this->deliveryQueueName = $deliveryQueueName;
        $this->logger = $logger ?? new NullLogger();
    }

    public function moveMessageBatch(int $batchSize, callable $afterMessageTransfer = null): int
    {
        $afterMessageTransfer = $afterMessageTransfer ?? function(){};
        $messageList = $this->scheduledMessageService->findScheduledMessageList($batchSize);

        foreach ($messageList as $message) {
            $this->moveMessage($message);
            $afterMessageTransfer();
        }

        return count($messageList);
    }

    private function moveMessage(MessageInterface $message): void
    {
        $this->logger->info('Moving scheduled message to delivery queue: ' . $this->getLogMessage($message));

        try {
            $this->sendToDeliveryQueue($message);
            $this->scheduledMessageService->unscheduleMessage($message);
            $this->entityManager->flush();
            $this->logger->info('Successfully moved scheduled message to delivery queue: ' . $message->getId());
        } catch (Throwable $error) {
            $this->logger->error('Error moving scheduled message ' . $message->getId() . ' to delivery queue: ' . $this->getLogErrorMessage($error));
        }
    }

    private function getLogMessage(MessageInterface $message): string
    {
        return $message->getId()
            . ', ' . $message->getDestination()
            . ', ' . $message->getDeliverAt()->format(DateTime::ATOM)
            . ', ' . $message->getCreatedAt()->format(DateTime::ATOM)
            . ', ' . $message->getUpdatedAt()->format(DateTime::ATOM)
            . ', ' . $message->getTries()
            . ', ' . $message->getPriority();
    }

    private function getLogErrorMessage(Throwable $error): string
    {
        return 'An error occurred while trying to move the scheduled message to the delivery queue: '
            . "\n" . $error->getFile()
            . ', ' . $error->getLine()
            . ', ' . $error->getMessage();
    }

    private function sendToDeliveryQueue(MessageInterface $message): void
    {
        $this->deliveryQueueMessageProducer->send($message, $this->deliveryQueueName);
    }
}
