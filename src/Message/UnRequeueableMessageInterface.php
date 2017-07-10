<?php

namespace Werkspot\MessageQueue\Message;

interface UnRequeueableMessageInterface
{
    public function getId(): int;

    public function getMessage();
}
