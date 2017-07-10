<?php

namespace Werkspot\MessageQueue\Test\Unit\DeliveryQueue;

use DateInterval;
use DateTime;
use DateTimeInterface;
use Throwable;
use Werkspot\MessageQueue\Message\MessageInterface;

class MessageStub implements MessageInterface
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $destination;

    /**
     * @var string
     */
    private $payload;

    /**
     * @var int
     */
    private $priority;

    /**
     * @var DateTimeInterface
     */
    private $deliverAt;

    /**
     * @var DateTime
     */
    private $createdAt;

    /**
     * @var DateTime|null
     */
    private $updatedAt;

    /**
     * @var int
     */
    private $tries = 0;

    /**
     * @var string
     */
    private $errors;

    public function __construct($payload, string $destination = '', DateTimeInterface $dequeueAt = null, int $priority = 0)
    {
        $this->id = uniqid();
        $this->destination = $destination;
        $this->payload = $payload;
        $this->priority = $priority;
        $this->deliverAt = $dequeueAt ?? $this->defineDequeueDate();
        $this->createdAt = new DateTime();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }

    /**
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getDeliverAt(): DateTimeInterface
    {
        return $this->deliverAt;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function getTries(): int
    {
        return $this->tries;
    }

    public function getErrors(): ?string
    {
        return $this->errors;
    }

    public function fail(Throwable $error): void
    {
        $now = new DateTime();

        $errorMessage = sprintf(
            "[%s] '%s': '%s'\n%s",
            $now->format(DateTime::ATOM),
            get_class($error),
            $error->getMessage(),
            $error->getTraceAsString()
        );

        $this->errors .= $errorMessage . "\n\n";

        $this->tries++;
        $this->updateDequeueDate();
    }

    private function updateDequeueDate(): void
    {
        $this->deliverAt = $this->defineDequeueDate();
    }

    private function defineDequeueDate(): DateTimeInterface
    {
        $interval = $this->getDateTimeIntervalForTry($this->tries + 1);

        return (new DateTime())->add($interval);
    }

    /**
     * By default we try the command in:
     *  - try 1: 0 minutes
     *  - try 2: 1 minutes
     *  - try 3: 4 minutes
     *  - try 4: 9 minutes
     *
     * @param int $try The try of the command, try 1 is the first time the message is delivered
     */
    private function getDateTimeIntervalForTry(int $try): DateInterval
    {
        $waitingTimeInMinutes = ($try - 1) * ($try - 1);

        return new DateInterval(sprintf('PT%dM', $waitingTimeInMinutes));
    }
}
