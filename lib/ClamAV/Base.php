<?php

namespace Amp\ClamAV;

use Amp\ByteStream\InputStream;
use Amp\Promise;
use Amp\Socket\Socket;

use function Amp\call;

abstract class Base
{
    /**
     * Pings the ClamAV daemon.
     *
     * @return Promise<bool>
     */
    public function ping(): Promise
    {
        return call(function (): \Generator {
            return 'PONG' === yield from $this->command('PING');
        });
    }

    /**
     * Scans a file or directory using the native ClamD `SCAN` command (ClamD must have access to this file!)
     * 
     * Stops once a malware has been found.
     *
     * @param string $path
     *
     * @return Promise<ScanResult>
     */
    public function scan(string $path): Promise
    {
        return call(function () use ($path): \Generator {
            return $this->parseScanOutput(yield from $this->command('SCAN ' . $path));
        });
    }

    /**
     * Runs a continue scan that stops after the entire file has been checked
     * 
     * @param string $path
     * 
     * @return Promise<array<ScanResult>>
     */
    public function continueScan(string $path): Promise
    {
        return call(function () use ($path) {
            $output = \trim(yield from $this->command('CONTSCAN ' . $path));
            return \array_map([$this, 'parseScanOutput'], array_filter(explode("\n", $output), fn ($val) => !empty($val)));
        });
    }

    /**
     * Runs the `VERSION` command
     * 
     * @return Promise<string>
     */
    public function version()
    {
        return call(function () {
            return \trim(yield from $this->command('VERSION'));
        });
    }

    /**
     * Scans from a stream.
     *
     * @param $stream
     *
     * @return Promise<ScanResult>
     * @throws ClamException If an exception happens with writing to the stream (if the INSTREAM limit has been reached, the errorCode will be `ClamException::INSTREAM_WRITE_EXCEEDED`)
     * @throws \Amp\ByteStream\ClosedException If the socket has been closed
     */
    abstract public function scanFromStream(InputStream $stream): Promise;

    /**
     * Pipes an InputStream to a ClamD socket by using the `INSTREAM` command.
     *
     * @param InputStream $stream The stream to pipe
     * @param Socket $socket The destination socket
     *
     * @return \Generator<void>
     * @throws \Amp\ByteStream\ClosedException If the socket has been closed
     * @throws \Amp\ByteStream\StreamException If the writing fails
     */
    protected function pipeStreamScan(InputStream $stream, Socket $socket): \Generator
    {
        yield $socket->write("zINSTREAM\x0");
        while (null !== $chunk = yield $stream->read()) {
            if (empty($chunk)) {
                continue;
            }
            // The format of the chunk is:
            // '<length><data>' where <length> is the size of the  following
            // data in bytes expressed as a 4 byte unsigned integer in network
            // byte order and <data> is the actual chunk.
            // man: clamd

            // pack the chunk length
            $lengthData = \pack('N', \strlen($chunk));
            yield $socket->write($lengthData . $chunk);
            $chunk = null;
        }
        yield $socket->write(\pack('N', 0));
    }

    /**
     * Parses the scan's output (of a `SCAN`, `MULTISCAN`, `CONTSCAN`, ... command).
     *
     * @param string $output The unparsed output
     *
     * @return ScanResult
     * @throws ClamException|ParseException
     */
    protected function parseScanOutput(string $output): ScanResult
    {
        $output = \trim($output);
        $separatorPos = \strrpos($output, ': ');
        $separatorLength = 2;
        $filename = \substr($output, 0, $separatorPos);
        $result = \substr($output, $separatorPos + $separatorLength);
        if (empty($filename) || empty($result)) {
            throw new ParseException('Could not parse string: ' . $output);
        }
        // filepath: <virtype> FOUND/OK/ERROR
        if ($result === 'OK') {
            return new ScanResult($filename, false, null);
        }

        if (\str_ends_with($output, ' FOUND')) {
            return new ScanResult($filename, true, \substr($result, 0, \strrpos($result, ' FOUND')));
        }

        if (\str_ends_with($output, ' ERROR')) {
            throw new ClamException(\substr($result, 0, \strrpos($result, ' ERROR')));
        }

        if ($result === 'COMMAND READ TIMED OUT') {
            throw new ClamException('timeout', ClamException::TIMEOUT);
        }

        throw new ClamException('ClamAV sent an invalid or unknown response: ' . $output, ClamException::UNKNOWN);
    }

    /**
     * Executes a command to ClamD and if waitForResponse is true, wait for the response (see different implementation).
     *
     * @param string $command The command to execute
     * @param bool $waitForResponse Wait for the response
     *
     * @return \Generator<string>
     */
    abstract protected function command(string $command, bool $waitForResponse = true): \Generator;
}
