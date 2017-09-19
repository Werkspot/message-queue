<?php

function getEnvironment(): string
{
    return getenv('ENV') ?: 'dev';
}

function isDevEnvironment(): bool
{
    return getEnvironment() === 'dev';
}

(function (): void {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (isDevEnvironment()) {
        $secondsToSleep = (int) getenv('SECONDS_TO_WAIT_FOR_CONTAINERS');
        echo "Waiting ${secondsToSleep}s for all containers to be available...\n";
        sleep($secondsToSleep);
    }
})();
