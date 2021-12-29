<?php

namespace Amp\ClamAV;

use Amp\ByteStream\InputStream;
use Amp\Socket\Socket;
use Amp\Promise;
use Amp\Deferred;

use function Amp\call;

class Session extends Base
{
    private Socket $socket;
    private array $deferreds = []; // $i -> \Amp\Deferred
    private int $reqId = 1;
    private function __construct()
    {
    }

    public static function fromSocket(Socket $socket): Promise
    {
        return call(function () use ($socket) {
            $instance = new self;
            $instance->socket = $socket;
            yield from $instance->command('IDSESSION', waitForResponse: false);
            $instance->readLoop();
            return $instance;
        });
    }

    protected function command(string $command, bool $waitForResponse = true): \Generator
    {
        $this->socket->write('z' . $command . "\x0");
        if ($waitForResponse) {
            return yield $this->commandResponsePromise();
        }
    }

    protected function commandResponsePromise(): Promise
    {
        $deferred = new Deferred;
        $this->deferreds[$this->reqId++] = $deferred;
        return $deferred->promise();
    }

    protected function readLoop()
    {
        return call(function () {
            $chunk = '';
            // read from the socket
            while (null !== $chunk = yield $this->socket->read()) {
                // split the message (ex: "1: PONG")
                $parts = explode(' ', $chunk, 2);
                $message = trim($parts[1]);
                $id = (int)substr($parts[0], 0, strpos($parts[0], ':'));
                if (isset($this->deferreds[$id])) {
                    /** @var Deferred */
                    $deferred = $this->deferreds[$id];
                    // resolve the enqueued request
                    $deferred->resolve($message);
                    unset($this->deferreds[$id]);
                }
            }
        });
    }

    public function end(): Promise
    {
        return call(function () {
            yield from $this->command('END', waitForResponse: false);
            yield $this->socket->end();
        });
    }

    /** @inheritdoc */
    public function scanFromStream(InputStream $stream): Promise
    {
        return call(function () use ($stream) {
            $promise = $this->commandResponsePromise();
            yield from $this->pipeStreamScan($stream, $this->socket);
            return $this->parseScanOutput(yield $promise);
        });
    }

    protected function getSocket()
    {
        return $this->socket;
    }
}
