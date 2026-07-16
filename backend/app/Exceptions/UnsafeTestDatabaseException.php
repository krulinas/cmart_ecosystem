<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when PHPUnit or artisan testing would use an unsafe database.
 */
class UnsafeTestDatabaseException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message,
        public readonly ?string $resolvedDatabase = null,
        public readonly ?string $approvedDatabase = null,
    ) {
        parent::__construct($message);
    }
}
