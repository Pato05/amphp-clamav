<?php

namespace Amp\ClamAV;

use Amp\ByteStream\InputStream;
use Amp\Deferred;
use Amp\Promise;
use Amp\Socket\Socket;

use function Amp\call;

class Session extends Base
{
    private Socket $socket;
    private array $deferreds = []; // $i -> \Amp\Deferred
    private int $reqId = 1;

    private function __construct()
    {
    }

    /**
     * Makes a Session instance from a socket (shouldn't be used as part of the public API, use ClamAV::session() instead!).
     *
     * @internal
     *
     * @param Socket $socket
     *
     * @return Promise<Session>
     */
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

    /** @inheritdoc */
    protected function command(string $command, bool $waitForResponse = true): \Generator
    {
        $this->socket->write('z' . $command . "\x0");
        if ($waitForResponse) {
            return yield $this->commandResponsePromise($this->reqId++);
        }
    }

    /**
     * Gets or creates a command response promise (that will be later resolved by the readLoop).
     *
     * @param int $reqId The request's id (an auto-increment integer, which will be used by ClamD to identify this request)
     *
     * @return Promise<string>
     */
    protected function commandResponsePromise(int $reqId): Promise
    {
        if (isset($this->deferreds[$reqId])) {
            return $this->deferreds[$reqId];
        }
        $deferred = new Deferred;
        $this->deferreds[$reqId] = $deferred;
        return $deferred->promise();
    }

    /**
     * A read loop for the ClamD socket (given that it might send responses unordered).
     *
     * @return Promise<never>
     */
    protected function readLoop()
    {
        return call(function () {
            $chunk = '';
            // read from the socket
            while (null !== $chunk = yield $this->socket->read()) {
                // split the message (ex: "1: PONG")
                $parts = \explode(' ', $chunk, 2);
                $message = \trim($parts[1]);
                $id = (int) \substr($parts[0], 0, \strpos($parts[0], ':'));
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

    /**
     * Ends this session.
     *
     * @return Promise<void>
     */
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
            $promise = $this->commandResponsePromise($this->reqId++);
            yield from $this->pipeStreamScan($stream, $this->socket);
            return $this->parseScanOutput(yield $promise);
        });
    }
}
