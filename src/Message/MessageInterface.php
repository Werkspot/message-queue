<?php

namespace Werkspot\MessageQueue\Message;

use DateTime;
use DateTimeInterface;
use Throwable;

interface MessageInterface
{
    public function getId(): string;

    public function getDestination(): string;

    /**
     * @return mixed
     */
    public function getPayload();

    public function getPriority(): int;

    public function getDeliverAt(): DateTimeInterface;

    public function getCreatedAt(): DateTime;

    public function getUpdatedAt(): DateTime;

    public function getTries(): int;

    public function getErrors(): ?string;

    public function fail(Throwable $error): void;
}
