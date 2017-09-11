<?php

(function (): void {
    require_once __DIR__ . '/../vendor/autoload.php';
    $secondsToSleep = 3;
    echo "Waiting ${secondsToSleep}s for all containers to be available...\n";
    sleep($secondsToSleep);
})();
