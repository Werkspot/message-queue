<?php

namespace Werkspot\MessageQueue\ScheduledQueue\Repository;

interface FailedMessageRepositoryInterface
{
    /**
     * TODO change this array into a DTO
     *
     * @return array with each element presented as an array(0 => QueuedCommandInterface, 1 => COUNT)
     */
    public function findMessagesThatFailed(): array;

    public function getNumberOfStuckMessages(): int;
}
