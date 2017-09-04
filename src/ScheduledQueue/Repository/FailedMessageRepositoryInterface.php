<?php

namespace Werkspot\MessageQueue\ScheduledQueue\Repository;

interface FailedMessageRepositoryInterface
{
    public function findMessagesThatFailed(): FailedMessageCollection;

    public function getNumberOfStuckMessages(): int;

    public function getNumberOfScheduledMessages(): int;
}
