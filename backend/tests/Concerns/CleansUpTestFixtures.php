<?php

namespace Tests\Concerns;

use App\Models\Booking;
use App\Models\BookingAuditLog;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\User;
use App\Models\VendorBusinessProfile;
use Illuminate\Support\Facades\DB;

/**
 * Reliable teardown for feature tests that write to the persistent local database.
 *
 * PHPUnit uses mysql/cmart_db (see phpunit.xml). Track every created fixture ID
 * and delete in reverse foreign-key order. Always reset tracking arrays in finally.
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

    protected function cleanupTrackedFixtures(): void
    {
        try {
            if ($this->createdAllocationIds !== []) {
                BookingDayAllocation::whereIn('id', $this->createdAllocationIds)->delete();
            }

            if ($this->createdInvoiceIds !== []) {
                Invoice::whereIn('id', $this->createdInvoiceIds)->delete();
            }

            if ($this->createdBookingIds !== []) {
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
                CarbootEvent::whereIn('id', $this->createdEventIds)->delete();
            }

            if ($this->createdUserIds !== []) {
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

        if (class_exists(\App\Models\UserBookingPreference::class)) {
            \App\Models\UserBookingPreference::whereIn('user_id', $userIds)->delete();
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
}
