<?php

namespace Werkspot\MessageQueue\Message;

interface UnRequeueableMessageInterface
{
    public function getId(): string;

    public function getMessage();

    public function getReason(): string;
}
