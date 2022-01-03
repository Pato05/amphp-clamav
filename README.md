# amphp-clamav

An asynchronous ClamAV wrapper written with amphp/socket

## Installing

```
composer require pato05/amphp-clamav
```

## Examples

Ping and scan of a file/directory

[`examples/scan.php`](https://github.com/Pato05/amphp-clamav/blob/main/examples/scan.php):

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Amp\ClamAV;
use Amp\Loop;

Loop::run(function () {
    echo 'connecting...' . PHP_EOL;

    if (yield ClamAV\ping()) {
        echo 'connected successfully!' . PHP_EOL;
    } else {
        echo 'connection failed!' . PHP_EOL;
        return;
    }
    echo 'running test scan...' . PHP_EOL;

    /** @var ClamAV\ScanResult */
    $result = yield ClamAV\scan('/tmp/eicar.com');
    echo (string) $result . PHP_EOL;
});
```

Scanning from a `InputStream` (in this case a `File` instance which implements `InputStream`)

[`examples/scan_stream.php`](https://github.com/Pato05/amphp-clamav/blob/main/examples/scan_stream.php):

```php
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
```

## Using a TCP/IP socket instead

If you want to use a TCP/IP socket instead of a UNIX one, you should use the `ClamAV\clamav()` function prior to any other call, or just use a custom `ClamAV` instance:

```php
\Amp\ClamAV\clamav('tcp://IP:PORT'); // to access it statically
// or
$clamav = new \Amp\ClamAV\ClamAV('tcp://IP:PORT');
```

Be aware that TCP/IP sockets may be slightly slower than UNIX ones.

## Using MULTISCAN

MULTISCAN is supported but can only be used on non-session instances (due to a ClamAV limitation).

MULTISCAN allows you to make a multithreaded scan.

```php
$result = yield \Amp\ClamAV\multiScan('FILEPATH');
```

## Differences between running a session and without

Sessions run on the same socket connection, while non-session instances will reconnect to the socket for each command. The library supports both, it's up to you deciding which to use.

Instantiating a session is pretty straight forward, just use the `ClamAV::session()` method like this:

```php
$clamSession = yield \Amp\ClamAV\session();
```

Though you MUST end every session by using the method `Session::end()`:

```php
yield $clamSession->end();
```

Be aware that in a session you can only execute ONE COMMAND AT A TIME, therefore, if you want to run more than one command in parallel, use the main `ClamAV` class instead.

Multiple `Session`s can also be instantiated.
