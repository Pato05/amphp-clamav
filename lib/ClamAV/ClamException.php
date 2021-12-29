<?php

namespace Amp\ClamAV;

class ClamException extends \Exception
{
    const TIMEOUT = 1;
    const UNKNOWN = -1;
}
