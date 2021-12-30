<?php

namespace Amp\ClamAV;

class ClamException extends \Exception
{
    const TIMEOUT = 1;
    const INSTREAM_WRITE_EXCEEDED = 2;
    const UNKNOWN = -1;
}
