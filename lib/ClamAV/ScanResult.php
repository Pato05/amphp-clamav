<?php

namespace Amp\ClamAV;

class ScanResult
{
    public function __construct(public string $filename, public bool $isInfected, public ?string $virusType)
    {
    }

    public function __toString()
    {
        return 'ScanResult(filename: ' . var_export($this->filename, true) . ', isInfected: ' . var_export($this->isInfected, true) . ', virusType: ' . var_export($this->virusType, true) . ')';
    }
}
