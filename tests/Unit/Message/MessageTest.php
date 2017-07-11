<?php

namespace Werkspot\MessageQueue\Test\Unit\Message;

use DateInterval;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use Werkspot\MessageQueue\Message\Message;

class MessageTest extends TestCase
{
    public function testExecutionDate()
    {
        $deliverAt = new DateTimeImmutable();
        $message = new Message('some payload', 'destination', $deliverAt, 1);

        self::assertEquals($deliverAt = $deliverAt->add(new DateInterval('PT0M')), $message->getDeliverAt());

        $message->fail(new Exception());
        self::assertEquals($deliverAt = $deliverAt->add(new DateInterval('PT1M')), $message->getDeliverAt());

        $message->fail(new Exception());
        self::assertEquals($deliverAt = $deliverAt->add(new DateInterval('PT4M')), $message->getDeliverAt());

        $message->fail(new Exception());
        self::assertEquals($deliverAt->add(new DateInterval('PT9M')), $message->getDeliverAt());
    }
}
