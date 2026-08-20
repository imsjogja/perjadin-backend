<?php

namespace App\Exceptions;

use DomainException;

class SppdAlreadyExistsException extends DomainException
{
    public function __construct(
        string $message,
        public readonly string $apiCode
    ) {
        parent::__construct($message);
    }
}
