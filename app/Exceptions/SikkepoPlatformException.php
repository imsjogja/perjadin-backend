<?php

namespace App\Exceptions;

use RuntimeException;

class SikkepoPlatformException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $upstreamStatus = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
