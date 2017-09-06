<?php

declare(strict_types=1);

namespace Werkspot\MessageQueue\Test\Unit;

final class TestDataObject
{
    public function getData(): array
    {
        return ['test' => 'metadata'];
    }

    public function getSchemaUri(): string
    {
        return 'test-schema';
    }
}
