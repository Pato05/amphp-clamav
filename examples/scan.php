<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Logger;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Loop;
use Amp\ClamAV;

Loop::run(function () {
    $logHandler = new StreamHandler(new ResourceOutputStream(\STDOUT));
    $logHandler->setFormatter(new ConsoleFormatter);
    $logger = new Logger('server');
    $logger->pushHandler($logHandler);

    $logger->info('connecting...');

    /** @var ClamAV\Session */
    $clamav = yield (new ClamAV())->session();
    if (yield $clamav->ping()) {
        $logger->info('connected successfully!');
    } else {
        $logger->critical('connection failed!');
        $clamav->end();
        return;
    }
    $logger->info('running test scan...');
    $result = yield $clamav->scan('/tmp/eicar.com');
    echo $result . PHP_EOL;
    $clamav->end();
});
