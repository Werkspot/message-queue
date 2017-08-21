<?php

namespace Werkspot\MessageQueue\Test;

use Mockery;
use Mockery\Matcher\Closure;
use Werkspot\MessageQueue\Message\MessageInterface;

final class WithMessage
{
    public static function equalTo(MessageInterface $expectedMessage): Closure
    {
        return Mockery::on(
            function (MessageInterface $actualMessage) use ($expectedMessage) {
                return get_class($expectedMessage) === get_class($actualMessage)
                    && $expectedMessage->getPayload() === $actualMessage->getPayload()
                    && $expectedMessage->getDestination() === $actualMessage->getDestination()
                    && $expectedMessage->getTries() === $actualMessage->getTries()
                    && $expectedMessage->getErrors() === $actualMessage->getErrors()
                    && $expectedMessage->getPriority() === $actualMessage->getPriority()
                    && $expectedMessage->getDeliverAt()->getTimestamp() === $actualMessage->getDeliverAt()->getTimestamp()
                    && $expectedMessage->getMetadata() === $actualMessage->getMetadata();
            }
        );
    }
}
