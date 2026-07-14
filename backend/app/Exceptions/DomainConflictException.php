<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain-level conflict suitable for HTTP 409 mapping (Phase 2A.6+).
 */
class DomainConflictException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $error = 'conflict',
    ) {
        parent::__construct($message);
    }
}
