<?php

namespace Werkspot\MessageQueue\Message;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Ramsey\Uuid\Uuid;
use Throwable;

final class Message implements MessageInterface
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
     * @var mixed
     */
    private $payload;

    /**
     * @var int
     */
    private $priority;

    /**
     * @var DateTimeInterface|DateTime|DateTimeImmutable
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
     * @var string|null
     */
    private $errors;

    /**
     * TODO create a PriorityEnum and use it here instead of an int
     * TODO change DateTimeInterface to DateTimeImmutable
     */
    public function __construct(
        $payload,
        string $destination,
        DateTimeInterface $deliverAt,
        int $priority
    ) {
        $this->id = Uuid::uuid4()->toString();
        $this->payload = $payload;
        $this->destination = $destination;
        $this->deliverAt = $deliverAt;
        $this->priority = $priority;
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
        $this->updateDeliveryDate();
    }

    private function updateDeliveryDate(): void
    {
        $this->deliverAt = $this->defineDeliveryDate();
    }

    private function defineDeliveryDate(): DateTimeInterface
    {
        $interval = $this->getDateTimeIntervalForTry($this->tries + 1);

        return $this->deliverAt->add($interval);
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
