<?php

namespace Werkspot\MessageQueue\Message;

use Ramsey\Uuid\Uuid;

class UnRequeueableMessage implements UnRequeueableMessageInterface
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var mixed
     */
    private $message;

    /**
     * @var string
     */
    private $reason;

    public function __construct($message, string $reason = '')
    {
        $this->id = Uuid::uuid4()->toString();
        $this->message = $message;
        $this->reason = $reason;
    }

    public function getId(): string
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

    public function getReason(): string
    {
        return $this->reason;
    }
}
