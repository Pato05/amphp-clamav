<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Amp\ClamAV;
use Amp\Loop;

Loop::run(function () {
    echo 'connecting...' . PHP_EOL;

    if (yield ClamAV\ping()) {
        echo 'connected!' . PHP_EOL;
    } else {
        echo 'connection failed.' . PHP_EOL;
        return;
    }
    echo 'running a streamed scan...' . PHP_EOL;

    /** @var \Amp\File\File */
    $file = yield \Amp\File\openFile('/tmp/eicar.com', 'r');

    /** @var \Amp\ClamAV\ScanResult */
    $res = yield ClamAV\scanFromStream($file);
    yield $file->close(); // always close files to avoid memory leaks
    echo (string) $res . PHP_EOL;
});
