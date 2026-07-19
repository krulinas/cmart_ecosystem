<?php

namespace Tests\Unit;

use App\Services\ItemReservationDuplicateKeyDetector;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\TestCase;

class ItemReservationDuplicateKeyDetectorTest extends TestCase
{
    public function test_only_exact_active_lock_duplicate_is_classified_as_item_conflict(): void
    {
        $detector = new ItemReservationDuplicateKeyDetector;

        $active = $this->duplicate(
            "Duplicate entry '42-1' for key 'item_reservations_item_active_lock_unique'",
        );
        $reference = $this->duplicate(
            "Duplicate entry 'RSV-ABCDEFGH' for key 'item_reservations_public_reference_unique'",
        );
        $unrelated = $this->duplicate("Duplicate entry 'x' for key 'some_other_unique'");

        $this->assertTrue($detector->isActiveLockViolation($active));
        $this->assertFalse($detector->isPublicReferenceViolation($active));
        $this->assertFalse($detector->isActiveLockViolation($reference));
        $this->assertTrue($detector->isPublicReferenceViolation($reference));
        $this->assertFalse($detector->isActiveLockViolation($unrelated));
        $this->assertFalse($detector->isPublicReferenceViolation($unrelated));
    }

    public function test_constraint_name_without_duplicate_driver_code_is_not_classified(): void
    {
        $detector = new ItemReservationDuplicateKeyDetector;
        $exception = $this->queryException(
            1452,
            'item_reservations_item_active_lock_unique',
        );

        $this->assertFalse($detector->isActiveLockViolation($exception));
    }

    private function duplicate(string $message): QueryException
    {
        return $this->queryException(1062, $message);
    }

    private function queryException(int $driverCode, string $message): QueryException
    {
        $previous = new PDOException($message);
        $previous->errorInfo = ['23000', $driverCode, $message];

        return new QueryException('mysql', 'insert into item_reservations', [], $previous);
    }
}
