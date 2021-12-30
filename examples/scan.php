<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Amp\ByteStream\ResourceOutputStream;
use Amp\ClamAV;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Loop;
use Monolog\Logger;

Loop::run(function () {
    $logHandler = new StreamHandler(new ResourceOutputStream(\STDOUT));
    $logHandler->setFormatter(new ConsoleFormatter);
    $logger = new Logger('server');
    $logger->pushHandler($logHandler);

    $logger->info('connecting...');

    $clamav = new ClamAV;
    if (yield $clamav->ping()) {
        $logger->info('connected successfully!');
    } else {
        $logger->critical('connection failed!');
        return;
    }
    $logger->info('running test scan...');
    $result = yield $clamav->scan('/tmp/eicar.com');
    echo $result . PHP_EOL;
});
