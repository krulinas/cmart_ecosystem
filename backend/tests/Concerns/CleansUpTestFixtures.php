<?php

namespace Tests\Concerns;

use App\Models\Booking;
use App\Models\BookingAttendanceException;
use App\Models\BookingAttendanceExceptionDay;
use App\Models\BookingAuditLog;
use App\Models\BookingCategoryOverride;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\CategoryMigrationAudit;
use App\Models\EventDay;
use App\Models\EventLayoutAuditLog;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserBookingPreference;
use App\Models\VendorBusinessProfile;
use App\Models\VendorItem;
use Illuminate\Support\Facades\DB;

/**
 * Reliable teardown for feature tests that write to the disposable test database.
 *
 * PHPUnit resolves mysql/cmart_test (see phpunit.xml and TestingDatabaseGuard).
 * Track every created fixture ID and delete in reverse foreign-key order.
 * Always reset tracking arrays in finally.
 */
trait CleansUpTestFixtures
{
    /** @var list<int> */
    protected array $createdUserIds = [];

    /** @var list<int> */
    protected array $createdEventIds = [];

    /** @var list<int> */
    protected array $createdSiteIds = [];

    /** @var list<int> */
    protected array $createdDayIds = [];

    /** @var list<int> */
    protected array $createdBookingIds = [];

    /** @var list<int> */
    protected array $createdAllocationIds = [];

    /** @var list<int> */
    protected array $createdInvoiceIds = [];

    /** @var list<int> */
    protected array $createdItemReservationIds = [];

    protected function cleanupTrackedFixtures(): void
    {
        try {
            if ($this->createdItemReservationIds !== []) {
                DB::table('item_reservation_audits')
                    ->whereIn('item_reservation_id', $this->createdItemReservationIds)
                    ->delete();
                DB::table('item_reservations')
                    ->whereIn('id', $this->createdItemReservationIds)
                    ->delete();
            }

            if ($this->createdBookingIds !== []) {
                $exceptionIds = BookingAttendanceException::whereIn(
                    'booking_id',
                    $this->createdBookingIds,
                )->pluck('id');
                BookingAttendanceExceptionDay::whereIn(
                    'booking_attendance_exception_id',
                    $exceptionIds,
                )->delete();
                BookingAttendanceException::whereIn('id', $exceptionIds)->delete();
            }

            if ($this->createdAllocationIds !== []) {
                BookingDayAllocation::whereIn('id', $this->createdAllocationIds)->delete();
            }

            if ($this->createdInvoiceIds !== []) {
                Invoice::whereIn('id', $this->createdInvoiceIds)->delete();
            }

            if ($this->createdBookingIds !== []) {
                CategoryMigrationAudit::query()
                    ->where('source_table', 'bookings')
                    ->whereIn('source_primary_key', $this->createdBookingIds)
                    ->delete();
                BookingCategoryOverride::whereIn('booking_id', $this->createdBookingIds)->delete();
                BookingDayAllocation::whereIn('booking_id', $this->createdBookingIds)->delete();
                BookingAuditLog::whereIn('booking_id', $this->createdBookingIds)->delete();
                Booking::whereIn('id', $this->createdBookingIds)->delete();
            }

            if ($this->createdDayIds !== []) {
                EventDay::whereIn('id', $this->createdDayIds)->delete();
            }

            if ($this->createdSiteIds !== []) {
                EventSite::whereIn('id', $this->createdSiteIds)->delete();
            }

            if ($this->createdEventIds !== []) {
                EventSite::whereIn('carboot_event_id', $this->createdEventIds)->delete();
                EventLayoutAuditLog::whereIn('carboot_event_id', $this->createdEventIds)->delete();
                EventLayoutRow::whereIn('carboot_event_id', $this->createdEventIds)->delete();
                CarbootEvent::whereIn('id', $this->createdEventIds)->delete();
            }

            if ($this->createdUserIds !== []) {
                EventLayoutAuditLog::whereIn('actor_user_id', $this->createdUserIds)->delete();
                $this->deleteUsersAndDependencies($this->createdUserIds);
            }
        } finally {
            $this->resetTrackedFixtures();
        }
    }

    /**
     * @param  list<int>  $userIds
     */
    protected function deleteUsersAndDependencies(array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $userIds)
            ->delete();

        $profileIds = VendorBusinessProfile::query()
            ->whereIn('user_id', $userIds)
            ->pluck('id');
        CategoryMigrationAudit::query()
            ->where('source_table', 'vendor_business_profiles')
            ->whereIn('source_primary_key', $profileIds)
            ->delete();

        if (class_exists(UserBookingPreference::class)) {
            $preferenceIds = UserBookingPreference::query()
                ->whereIn('user_id', $userIds)
                ->pluck('id');
            CategoryMigrationAudit::query()
                ->where('source_table', 'user_booking_preferences')
                ->whereIn('source_primary_key', $preferenceIds)
                ->delete();
            UserBookingPreference::whereIn('user_id', $userIds)->delete();
        }

        if (class_exists(VendorItem::class)) {
            $itemIds = VendorItem::query()
                ->whereIn('user_id', $userIds)
                ->pluck('id');
            CategoryMigrationAudit::query()
                ->where('source_table', 'vendor_items')
                ->whereIn('source_primary_key', $itemIds)
                ->delete();
            VendorItem::whereIn('user_id', $userIds)->delete();
        }

        VendorBusinessProfile::whereIn('user_id', $userIds)->delete();

        BookingAuditLog::whereIn('actor_user_id', $userIds)->delete();

        User::whereIn('id', $userIds)->delete();
    }

    protected function resetTrackedFixtures(): void
    {
        $this->createdUserIds = [];
        $this->createdEventIds = [];
        $this->createdSiteIds = [];
        $this->createdDayIds = [];
        $this->createdBookingIds = [];
        $this->createdAllocationIds = [];
        $this->createdInvoiceIds = [];
        $this->createdItemReservationIds = [];
    }

    protected function trackUser(User $user): User
    {
        $this->createdUserIds[] = $user->id;

        return $user;
    }

    protected function trackEvent(CarbootEvent $event): CarbootEvent
    {
        $this->createdEventIds[] = $event->id;

        return $event;
    }

    protected function trackItemReservationId(int $reservationId): int
    {
        $this->createdItemReservationIds[] = $reservationId;

        return $reservationId;
    }
}
