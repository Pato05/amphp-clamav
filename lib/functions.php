<?php

namespace Amp\ClamAV;

use Amp\ByteStream\InputStream;
use Amp\Loop;
use Amp\Promise;

const LOOP_STATE_IDENTIFIER = ClamAV::class;

/**
 * Get the application-wide `ClamAV` instance.
 *
 * @return \Amp\ClamAV\ClamAV
 */
function clamav(string $sockuri = ClamAV::DEFAULT_SOCK_URI): ClamAV
{
    /** @var ClamAV */
    $instance = Loop::getState(LOOP_STATE_IDENTIFIER);

    if ($instance === null) {
        $instance = new ClamAV($sockuri);
        Loop::setState(LOOP_STATE_IDENTIFIER, $instance);
    }

    return $instance;
}

/**
 * Pings the ClamAV daemon.
 *
 * @return \Amp\Promise<bool>
 */
function ping(): Promise
{
    return clamav()->ping();
}

/**
 * Scans a file or directory using the native ClamD `SCAN` command (ClamD must have access to this file!)
 * 
 * Stops once a malware has been found.
 *
 * @param string $path
 *
 * @return \Amp\Promise<\Amp\ClamAV\ScanResult>
 */
function scan(string $path): Promise
{
    return clamav()->scan($path);
}

/**
 * Runs a multithreaded ClamAV scan (using the `MULTISCAN` command).
 *
 * @param string $path The file or directory's path
 *
 * @return \Amp\Promise<\Amp\ClamAV\ScanResult>
 */
function multiScan(string $path): Promise
{
    return clamav()->multiScan($path);
}

/**
 * Runs a continue scan that stops after the entire file has been checked
 * 
 * @param string $path
 * 
 * @return \Amp\Promise<\Amp\ClamAV\ScanResult[]>
 */
function continueScan(string $path)
{
    return clamav()->continueScan($path);
}

/**
 * Runs the `VERSION` command
 * 
 * @return \Amp\Promise<string>
 */
function version(): Promise
{
    return clamav()->version();
}

/**
 * Scans from a stream.
 *
 * @param $stream
 *
 * @return \Amp\Promise<\Amp\ClamAV\ScanResult>
 * @throws \Amp\ClamAV\ClamException If an exception happens with writing to the stream (if the INSTREAM limit has been reached, the errorCode will be `ClamException::INSTREAM_WRITE_EXCEEDED`)
 * @throws \Amp\ByteStream\ClosedException If the socket has been closed
 */
function scanFromStream(InputStream $stream): Promise
{
    return clamav()->scanFromStream($stream);
}

/**
 * Initiates a new ClamAV session
 * Note: you MUST call `Session::end()` once you are done.
 *
 * @return \Amp\Promise<\Amp\ClamAV\Session>
 */
function session(): Promise
{
    return clamav()->session();
}
