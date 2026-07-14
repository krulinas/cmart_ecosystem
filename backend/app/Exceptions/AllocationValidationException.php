<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Reservation validation failure (integration-ready for HTTP 422 in Phase 2A.7).
 */
class AllocationValidationException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        public readonly string $error = 'allocation_validation_failed',
    ) {
        parent::__construct($message);
    }
}
