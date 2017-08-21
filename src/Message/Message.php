<?php

namespace Werkspot\MessageQueue\Message;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use Throwable;

class Message implements MessageInterface
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
     * @var DateTimeImmutable
     */
    private $deliverAt;

    /**
     * @var DateTimeImmutable
     */
    private $createdAt;

    /**
     * @var DateTimeImmutable|null
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
     * @var array
     */
    private $metadata;

    public function __construct(
        $payload,
        string $destination,
        DateTimeImmutable $deliverAt,
        int $priority,
        array $metadata = []
    ) {
        $this->id = Uuid::uuid4()->toString();
        $this->payload = $payload;
        $this->destination = $destination;
        $this->deliverAt = $deliverAt;
        $this->priority = $priority;
        $this->metadata = $metadata;
        $this->createdAt = new DateTimeImmutable();
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

    public function getDeliverAt(): DateTimeImmutable
    {
        return $this->deliverAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
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
        $now = new DateTimeImmutable();

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

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    private function updateDeliveryDate(): void
    {
        $this->deliverAt = $this->defineDeliveryDate();
    }

    private function defineDeliveryDate(): DateTimeImmutable
    {
        $interval = $this->getDateTimeImmutableIntervalForTry($this->tries + 1);

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
    private function getDateTimeImmutableIntervalForTry(int $try): DateInterval
    {
        $waitingTimeInMinutes = ($try - 1) * ($try - 1);

        return new DateInterval(sprintf('PT%dM', $waitingTimeInMinutes));
    }
}
