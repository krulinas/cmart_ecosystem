<?php

namespace App\Services;

use Illuminate\Database\QueryException;

class ItemReservationDuplicateKeyDetector
{
    public const ACTIVE_LOCK_CONSTRAINT = 'item_reservations_item_active_lock_unique';

    public const PUBLIC_REFERENCE_CONSTRAINT = 'item_reservations_public_reference_unique';

    public function isActiveLockViolation(QueryException $exception): bool
    {
        return $this->isConstraintViolation($exception, self::ACTIVE_LOCK_CONSTRAINT);
    }

    public function isPublicReferenceViolation(QueryException $exception): bool
    {
        return $this->isConstraintViolation($exception, self::PUBLIC_REFERENCE_CONSTRAINT);
    }

    private function isConstraintViolation(
        QueryException $exception,
        string $constraint,
    ): bool {
        return ($exception->errorInfo[1] ?? null) === 1062
            && str_contains($exception->getMessage(), $constraint);
    }
}
