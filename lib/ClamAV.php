<?php

namespace Amp;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\StreamException;
use Amp\ClamAV\Base;
use Amp\ClamAV\ClamException;
use Amp\ClamAV\Session;
use Amp\Socket\Socket;

class ClamAV extends Base
{
    const DEFAULT_SOCK_URI = 'unix:///run/clamav/clamd.ctl';

    /**
     * Constructs the class.
     *
     * @param string $sockuri The socket uri (`unix://PATH` or `tcp://IP:PORT`)
     *
     * @return Promise<ClamAV>
     */
    public function __construct(private $sockuri = self::DEFAULT_SOCK_URI)
    {
    }

    /**
     * Initiates a new ClamAV session
     * Note: you MUST call `Session::end()` once you are done.
     *
     * @return Promise<Session>
     */
    public function session(): Promise
    {
        return call(function () {
            /** @var Socket */
            $socket = yield \Amp\Socket\connect($this->sockuri);

            return yield Session::fromSocket($socket);
        });
    }

    /**
     * Runs a multithreaded ClamAV scan (using the `MULTISCAN` command).
     *
     * @param string $path The file or directory's path
     *
     * @return Promise<ScanResult>
     */
    public function multiscan(string $path): Promise
    {
        return call(function () use ($path) {
            return $this->parseScanOutput(yield from $this->command('MULTISCAN ' . $path));
        });
    }

    /** @inheritdoc */
    public function scanFromStream(InputStream $stream): Promise
    {
        return call(function () use ($stream) {
            /** @var Socket */
            $socket = yield from $this->getSocket();
            try {
                yield from $this->pipeStreamScan($stream, $socket);
            } catch (StreamException $e) {
                if (!$socket->isClosed()) {
                    $message = yield $socket->read();
                    if ($message === 'INSTREAM size limit exceeded') throw new ClamException('INSTREAM size limit exceeded', ClamException::INSTREAM_WRITE_EXCEEDED, $e);
                }
                throw new ClamException($e->getMessage() . $message, ClamException::UNKNOWN, $e);
            }
            return $this->parseScanOutput(yield $socket->read());
        });
    }

    /** @inheritdoc */
    protected function command(string $command, bool $waitForResponse = true): \Generator
    {
        /** @var Socket */
        $socket = yield from $this->getSocket();
        yield $socket->write('z' . $command . "\x0");
        if ($waitForResponse) {
            return \trim(yield $socket->read());
        }
    }

    /**
     * Gets a new socket (to execute a new command).
     *
     * @return \Generator<Socket>
     */
    protected function getSocket(): \Generator
    {
        return yield \Amp\Socket\connect($this->sockuri);
    }
}
