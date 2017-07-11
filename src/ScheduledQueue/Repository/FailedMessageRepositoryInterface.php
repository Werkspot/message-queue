<?php

namespace Werkspot\MessageQueue\ScheduledQueue\Repository;

interface FailedMessageRepositoryInterface
{
    /**
     * TODO [POST CLEANUP] change this array into a DTO
     *
     * @return array with each element presented as an array(0 => MessageInterface, 1 => COUNT)
     */
    public function findMessagesThatFailed(): array;

    public function getNumberOfStuckMessages(): int;
}
