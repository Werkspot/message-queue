<?php

namespace Werkspot\MessageQueue\Message;

final class UnRequeueableMessage implements UnRequeueableMessageInterface
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var mixed
     */
    private $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }
}
