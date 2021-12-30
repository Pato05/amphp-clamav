# amphp-clamav

An asynchronous ClamAV wrapper written with amphp/socket

## Examples

Ping and scan of a file/directory

[`examples/scan.php`](https://github.com/Pato05/amphp-clamav/blob/main/examples/scan.php):

```php
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
```

Scanning from a `ResourceInputStream`

[`examples/scan_stream.php`](https://github.com/Pato05/amphp-clamav/blob/main/examples/scan_stream.php):

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

    $clamav = new ClamAV;
    if (yield $clamav->ping()) {
        $logger->info('connected!');
    } else {
        $logger->critical('connection failed.');
        return;
    }
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
```

## Using a TCP/IP socket instead

When instantiating the `ClamAV` class, you can also define your socket URI, therefore if you want to use a TCP/IP socket instead of a UNIX one, do:

```php
$clamav = new ClamAV('tcp://IP:PORT');
```

Be aware that TCP/IP sockets may be slightly slower than UNIX ones.

## Using MULTISCAN

MULTISCAN is supported but can only be used on non-session instances (due to a ClamAV limitation).

MULTISCAN allows you to make a multithreaded scan.

```php
$result = yield $clamav->multiscan('FILEPATH');
```

## Differences between running a session and without

Sessions run on the same socket connection, while non-session instances will reconnect to the socket for each command. The library supports both, it's up to you deciding which to use.

Instantiating a session is pretty straight forward, just use the `ClamAV::session()` method like this:

```php
$clamSession = yield (new ClamAV)->session();
```

Though you MUST end every session by using the method `Session::end()`:

```php
yield $clamSession->end();
```
