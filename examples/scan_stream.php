<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\ClamAV;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Loop;
use Monolog\Logger;

Loop::run(function () {
    $logHandler = new StreamHandler(new ResourceOutputStream(STDOUT));
    $logHandler->setFormatter(new ConsoleFormatter);
    $logger = new Logger('scan_stream');
    $logger->pushHandler($logHandler);

    $logger->info('connecting...');
    $clamav = yield (new ClamAV)->session();
    if (yield $clamav->ping()) {
        $logger->info('connected!');
    } else {
        $logger->critical('connection failed.');
        return;
    }

    $file = fopen('/tmp/eicar.com', 'r');
    /** @var \Amp\ClamAV\ScanResult */
    $res = yield $clamav->scanFromStream(new ResourceInputStream($file));
    echo 'done' . PHP_EOL;
    fclose($file);
    yield $clamav->end();
});
