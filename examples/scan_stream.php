<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Amp\ByteStream\ResourceInputStream;
use Amp\ClamAV;
use Amp\Loop;

Loop::run(function () {
    echo 'connecting...' . PHP_EOL;

    $clamav = new ClamAV;
    if (yield $clamav->ping()) {
        echo 'connected!' . PHP_EOL;
    } else {
        echo 'connection failed.' . PHP_EOL;
        return;
    }
    echo 'running a streamed scan...' . PHP_EOL;
    /*
        This is absolutely NOT RECOMMENDED to do and this is given only as an example of usage of the scanFromStream method.
        It is recommended to use amphp/file instead, as it is written just below.
        DON'T USE THIS SNIPPET APART FROM TESTING REASONS.

        $file = yield \Amp\File\open('/tmp/eicar.com', 'r');
        $res = yield $clamav->scanFromStream($file);

        fopen is blocking and SHOULD NOT be used within asynchronous applications.
    */
    $file = \fopen('/tmp/eicar.com', 'r');
    $stream = new ResourceInputStream($file);

    /** @var \Amp\ClamAV\ScanResult */
    $res = yield $clamav->scanFromStream($stream);
    echo (string) $res . PHP_EOL;
    \fclose($file);
});
