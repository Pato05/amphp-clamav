<?php

namespace Amp\ClamAV;

class ClamException extends \Exception
{
    /**
     * ClamD returned `COMMAND READ TIMED OUT` (should not happen)
     * 
     * @var int
     */
    const TIMEOUT = 1;

    /**
     * The provided stream to `scanFromStream` is too large, and ClamD rejected it
     * because of the limit set inside clamd.conf.
     * 
     * @var int
     */
    const INSTREAM_WRITE_EXCEEDED = 2;

    /**
     * ClamD unexpectedly ended the stream or another unknown error
     * 
     * @var int
     */
    const UNKNOWN = -1;
}
