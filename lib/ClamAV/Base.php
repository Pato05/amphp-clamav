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
     * Stops once a malware has been found (!).
     *
     * @param string $path
     * @param bool $multi If enabled, runs a multithreaded scan instead of a singlethreaded one
     *
     * @return Promise<ScanResult>
     */
    public function scan(string $path): Promise
    {
        return call(function () use ($path): \Generator {
            $message = yield from $this->command('SCAN ' . $path);
            return $this->parseScanOutput($message);
        });
    }

    /**
     * Scans from a stream.
     *
     * @param $stream
     *
     * @return Promise<ScanResult>
     */
    abstract public function scanFromStream(InputStream $stream): Promise;

    /**
     * Pipes an InputStream to a ClamD socket by using the `INSTREAM` command.
     *
     * @param InputStream $stream The stream to pipe
     * @param Socket $socket The destination socket
     *
     * @return \Generator<void>
     */
    protected function pipeStreamScan(InputStream $stream, Socket $socket): \Generator
    {
        yield $socket->write("zINSTREAM\x0");
        $chunk = '';
        while (null !== $chunk = yield $stream->read()) {
            // The format of the chunk is:
            // '<length><data>' where <length> is the size of the  following
            // data  in bytes expressed as a 4 byte unsigned integer in network
            // byte order and <data> is the actual chunk.
            // man: clamd

            // pack the chunk length
            $lengthData = \pack('N', \strlen($chunk));
            $socket->write($lengthData . $chunk);
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
        $parts = \explode(': ', $output, 2);
        $filename = $parts[0];
        $result = $parts[1];
        if (empty($filename) || empty($result)) {
            throw new ParseException('Could not parse string: ' . $output);
        }
        // filepath: <virtype> FOUND/OK/ERROR
        if ($result === 'OK') {
            return new ScanResult($filename, false, null);
        }

        if (\strpos($result, ' FOUND') !== false) {
            return new ScanResult($filename, true, \substr($result, 0, \strpos($result, ' FOUND')));
        }

        if (\strpos($result, ' ERROR') !== false) {
            throw new ClamException(\substr($result, 0, \strpos($result, ' ERROR')));
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
