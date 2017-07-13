<?php

namespace Werkspot\MessageQueue\Test;

use ReflectionObject;

final class ReflectionHelper
{
    public static function setProtectedProperty($object, string $propertyName, $value): void
    {
        $reflectionObject = new ReflectionObject($object);

        $reflectionProperty = $reflectionObject->getProperty($propertyName);
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($object, $value);
    }
}
