<?php

namespace Amp\ClamAV;

class ScanResult
{
    public function __construct(
        /**
         * The infected file's name.
         *
         * @var string
         */
        public string $filename,

        /**
         * Whether the file is infected or not.
         *
         * @var bool
         */
        public bool $isInfected,

        /**
         * The malware's type.
         *
         * @var string|null
         */
        public ?string $malwareType
    ) {
    }

    public function __toString()
    {
        return 'ScanResult(filename: ' . \var_export($this->filename, true) . ', isInfected: ' . \var_export($this->isInfected, true) . ', malwareType: ' . \var_export($this->malwareType, true) . ')';
    }
}
