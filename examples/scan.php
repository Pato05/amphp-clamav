<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Amp\ClamAV;
use Amp\Loop;

Loop::run(function () {

    echo 'connecting...' . PHP_EOL;

    $clamav = new ClamAV;
    if (yield $clamav->ping()) {
        echo 'connected successfully!' . PHP_EOL;
    } else {
        echo 'connection failed!' . PHP_EOL;
        return;
    }
    echo 'running test scan...' . PHP_EOL;
    /** @var \Amp\ClamAV\ScanResult */
    $result = yield $clamav->scan('/tmp/eicar.com');
    echo (string) $result . PHP_EOL;
});
