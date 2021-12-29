<?php

namespace Amp;

use Amp\ByteStream\InputStream;
use Amp\ClamAV\Base;
use Amp\Promise;
use Amp\Socket\Socket;
use Amp\ClamAV\Session;

use function Amp\call;

class ClamAV extends Base
{
    const DEFAULT_SOCK_URI = 'unix:///run/clamav/clamd.ctl';

    /**
     * Constructs the class
     * 
     * @param string $sockuri The socket uri (unix://PATH or tcp://IP:PORT)
     * 
     * @return Promise<ClamAV>
     */
    public function __construct(private $sockuri = self::DEFAULT_SOCK_URI)
    {
    }

    /**
     * Initiate a new ClamAV session
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

    public function multiscan(string $path)
    {
        $message = yield from $this->command('MULTISCAN ' . $path);
    }

    public function scanFromStream(InputStream $stream): Promise
    {
        return call(function () use ($stream) {
            /** @var Socket */
            $socket = yield $this->getSocket();
            yield $this->pipeStreamScan($stream, $socket);
            return $this->parseScanOutput(yield $socket->read());
        });
    }

    protected function command(string $command, bool $waitForResponse = true): \Generator
    {
        /** @var Socket */
        $socket = yield from $this->getSocket();
        $socket->write('z' . $command . "\x0");
        if ($waitForResponse)
            return trim(yield $socket->read());
    }

    protected function getSocket(): \Generator
    {
        return yield \Amp\Socket\connect($this->sockuri);
    }
}
