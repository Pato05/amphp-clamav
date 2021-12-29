# amphp-clamav

An asynchronous ClamAV wrapper written with amphp/socket

## Examples

Ping and scan of a file/directory

`examples/scan.php`:

```php
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
    $logger->info('running test scan on /tmp/testscan...');
    yield $clamav->scan('/tmp/testscan');
    $clamav->end();
});
```

Scanning from a `ResourceInputStream`

`examples/scan_stream.php`:

```php
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
    echo (yield $clamav->scanFromStream(new ResourceInputStream($file))) . PHP_EOL;
    echo 'done' . PHP_EOL;
    fclose($file);
    yield $clamav->end();
});
```

## Using a TCP/IP socket instead

When instantiating the `ClamAV` class, you can also define your socket URI, therefore if you want to use a TCP/IP socket instead of a UNIX one, do:

```php
$clamav = new ClamAV('tcp://IP:PORT');
```

Be aware that TCP/IP sockets may be slightly slower than UNIX ones.

## Using MULTISCAN

MULTISCAN is supported but can only be used on non-session instances (due to a clamav limitation).

MULTISCAN allows you to make a multithreaded scan.

## Differences between running a session and without

Sessions run on the same socket connection, while non-session instances will reconnect to the socket for each command. The library supports both, it's up to you deciding which to use.
