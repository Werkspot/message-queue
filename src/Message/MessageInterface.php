<?php

namespace Werkspot\MessageQueue\Message;

use DateTimeImmutable;
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

    public function getDeliverAt(): DateTimeImmutable;

    public function getCreatedAt(): DateTimeImmutable;

    public function getUpdatedAt(): DateTimeImmutable;

    public function getTries(): int;

    public function getErrors(): ?string;

    public function fail(Throwable $error): void;
}
