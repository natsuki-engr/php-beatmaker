<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use PhpStrudel\OscClient;

$osc = new OscClient(
    '127.0.0.1',
    57120,
);

$osc->send('/play', 'bd');