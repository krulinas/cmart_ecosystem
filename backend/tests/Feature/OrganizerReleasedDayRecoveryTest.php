<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingAttendanceException;
use App\Models\BookingAuditLog;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use App\Services\BookingAllocationLifecycleService;
use App\Services\OrganizerReleasedDayRecoveryService;
use App\Services\VendorBookingPresenter;
use App\Services\VendorEventSiteAvailabilityService;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\TestCase;

class OrganizerReleasedDayRecoveryTest extends TestCase
{
    use CleansUpTestFixtures;

    private const RECOVERY_ENDPOINT = '/api/organizer/released-day-recovery';

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return $this->trackUser(User::create([
            'name' => 'Recovery ' . $role . ' ' . uniqid(),
            'email' => 'recovery-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'none',
        ]));
    }

    /**
     * @return array{
     *   booking: Booking,
     *   vendor: User,
     *   organizer: User,
     *   event: CarbootEvent,
     *   days: \Illuminate\Support\Collection,
     *   sites: \Illuminate\Support\Collection,
     *   released_day: EventDay
     * }
     */
    private function partialExceptionFixture(bool $applyException = true): array
    {
        $vendor = $this->user('community');
        $organizer = $this->user('organizer');
        $space = Space::query()->firstOrCreate(
            ['space_size' => 'Standard (1 Parking Lot)'],
            ['price' => 30.00, 'status' => 'Available'],
        );
        $starts = now()->addDays(20)->setTime(8, 0);
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Recovery Event ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addDays(2)->setTime(17, 0),
            'status' => 'Open',
            'description' => 'Phase 2B.4 test',
            'max_slots' => 40,
            'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
        ]));

        $days = collect(range(0, 2))->map(function (int $offset) use ($event, $starts) {
            $dayStart = $starts->copy()->addDays($offset);
            $day = EventDay::create([
                'carboot_event_id' => $event->id,
                'operational_date' => $dayStart->toDateString(),
                'starts_at' => $dayStart,
                'ends_at' => $dayStart->copy()->setTime(17, 0),
                'operational_status' => EventDay::STATUS_ACTIVE,
                'display_order' => $offset + 1,
            ]);
            $this->createdDayIds[] = $day->id;

            return $day;
        });

        $sites = collect([1, 2])->map(function (int $position) use ($event, $space) {
            $site = EventSite::create([
                'carboot_event_id' => $event->id,
                'space_id' => $space->id,
                'label' => sprintf('A%02d', $position),
                'row_label' => 'A',
                'position_number' => $position,
                'grid_row' => 1,
                'grid_column' => $position,
                'display_order' => $position,
                'operational_status' => EventSite::STATUS_ACTIVE,
            ]);
            $this->createdSiteIds[] = $site->id;

            return $site;
        });

        Sanctum::actingAs($vendor);
        $response = $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => $sites->pluck('id')->all(),
            'product_category' => 'Food & Beverages',
            'product_details' => 'Recovery queue booking',
        ])->assertCreated()->json();

        $booking = Booking::findOrFail((int) $response['booking']['id']);
        $this->createdBookingIds[] = $booking->id;
        $this->createdInvoiceIds[] = (int) $response['invoice']['id'];
        $this->createdAllocationIds = array_merge(
            $this->createdAllocationIds,
            BookingDayAllocation::where('booking_id', $booking->id)->pluck('id')->all(),
        );
        $booking->update(['approval_status' => 'Approved']);
        $booking->invoice()->update([
            'payment_status' => 'Paid',
            'payment_proof_path' => 'payment-proofs/recovery.jpg',
            'payment_submitted_at' => now(),
        ]);
        app(BookingAllocationLifecycleService::class)->confirmForBooking($booking->fresh());

        $releasedDay = $days[2];
        if ($applyException) {
            Sanctum::actingAs($organizer);
            $this->patchJson("/api/organizer/bookings/{$booking->id}/attendance-exception", [
                'retained_event_day_ids' => $days->take(2)->pluck('id')->all(),
                'reason' => 'Emergency family commitment on the final event day.',
                'acknowledge_no_refund' => true,
            ])->assertOk();
        }

        return [
            'booking' => $booking->fresh(['invoice', 'user.businessProfile', 'carbootEvent']),
            'vendor' => $vendor,
            'organizer' => $organizer,
            'event' => $event->fresh(),
            'days' => $days,
            'sites' => $sites,
            'released_day' => $releasedDay,
        ];
    }

    private function addCompetingAllocation(
        array $fixture,
        EventSite $site,
        ?EventDay $day = null,
    ): Booking {
        $day ??= $fixture['released_day'];
        $competitor = $this->user('community');
        $booking = Booking::create([
            'user_id' => $competitor->id,
            'space_id' => $site->space_id,
            'carboot_event_id' => $fixture['event']->id,
            'booking_date' => $day->operational_date,
            'product_category' => 'Others',
            'product_details' => 'Competing recovery allocation',
            'approval_status' => 'Approved',
        ]);
        $this->createdBookingIds[] = $booking->id;
        $allocation = BookingDayAllocation::create([
            'booking_id' => $booking->id,
            'event_day_id' => $day->id,
            'event_site_id' => $site->id,
            'allocation_status' => BookingDayAllocation::STATUS_CONFIRMED,
            'reserved_at' => now(),
            'confirmed_at' => now(),
            'active_lock' => 1,
        ]);
        $this->createdAllocationIds[] = $allocation->id;

        return $booking;
    }

    public function test_partial_exception_release_appears_grouped_in_recovery_queue(): void
    {
        $fixture = $this->partialExceptionFixture();
        Sanctum::actingAs($fixture['organizer']);

        $response = $this->getJson(self::RECOVERY_ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.0.source_booking.id', $fixture['booking']->id)
            ->assertJsonPath('data.0.event_day.id', $fixture['released_day']->id)
            ->assertJsonPath('data.0.recovery_state', OrganizerReleasedDayRecoveryService::RECOVERY_RECOVERABLE)
            ->assertJsonPath('data.0.source_payment_state', VendorBookingPresenter::PAYMENT_STATE_PAID)
            ->assertJsonPath('data.0.standard_full_event_available', false)
            ->assertJsonPath('data.0.release.reason', BookingAllocationLifecycleService::REASON_ORGANIZER_DAY_EXCEPTION);

        $row = $response->json('data.0');
        $this->assertSame(['A01', 'A02'], collect($row['released_sites'])->pluck('label')->sort()->values()->all());
        $this->assertSame(2, $row['recoverable_site_count']);
        $this->assertStringContainsString('Emergency family commitment', $row['attendance_exception_reason']);
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $row['source_invoice_amount']);
        $this->assertArrayNotHasKey('active_lock', $row);
        $this->assertArrayNotHasKey('payment_proof_path', $row);
        foreach ($row['released_sites'] as $site) {
            $this->assertArrayNotHasKey('allocation_id', $site);
        }
    }

    public function test_full_withdrawal_release_is_excluded_from_partial_recovery_queue(): void
    {
        $fixture = $this->partialExceptionFixture(false);
        Sanctum::actingAs($fixture['vendor']);
        $this->patchJson("/api/bookings/{$fixture['booking']->id}/withdraw", [
            'acknowledge_no_refund' => true,
        ])->assertOk();

        Sanctum::actingAs($fixture['organizer']);
        $this->getJson(self::RECOVERY_ENDPOINT)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_recovery_state_classification_and_replacement_detection(): void
    {
        $fixture = $this->partialExceptionFixture();
        Sanctum::actingAs($fixture['organizer']);

        $this->getJson(self::RECOVERY_ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.0.recovery_state', OrganizerReleasedDayRecoveryService::RECOVERY_RECOVERABLE);

        $this->addCompetingAllocation($fixture, $fixture['sites'][1]);
        $partial = $this->getJson(self::RECOVERY_ENDPOINT)->assertOk()->json('data.0');
        $this->assertSame(OrganizerReleasedDayRecoveryService::RECOVERY_PARTIALLY_BLOCKED, $partial['recovery_state']);
        $sitesByLabel = collect($partial['released_sites'])->keyBy('label');
        $this->assertSame(OrganizerReleasedDayRecoveryService::RECOVERY_RECOVERABLE, $sitesByLabel['A01']['recovery_state']);
        $this->assertSame('Occupied by another active booking', $sitesByLabel['A02']['blocker']);

        $this->addCompetingAllocation($fixture, $fixture['sites'][0]);
        $this->getJson(self::RECOVERY_ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.0.recovery_state', OrganizerReleasedDayRecoveryService::RECOVERY_FULLY_BLOCKED);
    }

    public function test_expired_disabled_and_operational_unavailable_states(): void
    {
        $fixture = $this->partialExceptionFixture();
        $fixture['released_day']->update([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(8),
        ]);

        Sanctum::actingAs($fixture['organizer']);
        $this->getJson(self::RECOVERY_ENDPOINT . '?recovery_state=expired')
            ->assertOk()
            ->assertJsonPath('data.0.recovery_state', OrganizerReleasedDayRecoveryService::RECOVERY_EXPIRED);

        $fixture['released_day']->update([
            'starts_at' => now()->addDays(5),
            'operational_status' => EventDay::STATUS_DISABLED,
        ]);
        $this->getJson(self::RECOVERY_ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.0.recovery_state', OrganizerReleasedDayRecoveryService::RECOVERY_OPERATIONALLY_UNAVAILABLE);

        $fixture['released_day']->update(['operational_status' => EventDay::STATUS_ACTIVE]);
        $fixture['sites'][0]->update(['operational_status' => EventSite::STATUS_DISABLED]);
        $partial = $this->getJson(self::RECOVERY_ENDPOINT)->assertOk()->json('data.0');
        $this->assertSame(OrganizerReleasedDayRecoveryService::RECOVERY_PARTIALLY_BLOCKED, $partial['recovery_state']);
    }

    public function test_standard_full_event_availability_boundary(): void
    {
        $fixture = $this->partialExceptionFixture();
        Sanctum::actingAs($fixture['vendor']);
        $availability = app(VendorEventSiteAvailabilityService::class)->forEvent($fixture['event']->fresh());
        foreach (['A01', 'A02'] as $label) {
            $site = collect($availability['sites'])->firstWhere('label', $label);
            $this->assertSame(VendorEventSiteAvailabilityService::AVAILABILITY_OCCUPIED, $site['availability_status']);
        }

        Sanctum::actingAs($fixture['organizer']);
        $this->getJson(self::RECOVERY_ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.0.standard_full_event_available', false);

        Sanctum::actingAs($fixture['vendor']);
        $this->patchJson("/api/bookings/{$fixture['booking']->id}/withdraw", [
            'acknowledge_no_refund' => true,
        ])->assertOk();

        $availability = app(VendorEventSiteAvailabilityService::class)->forEvent($fixture['event']->fresh());
        foreach (['A01', 'A02'] as $label) {
            $site = collect($availability['sites'])->firstWhere('label', $label);
            $this->assertSame(VendorEventSiteAvailabilityService::AVAILABILITY_AVAILABLE, $site['availability_status']);
        }
    }

    public function test_source_booking_later_withdrawal_updates_interpretation(): void
    {
        $fixture = $this->partialExceptionFixture();
        Sanctum::actingAs($fixture['vendor']);
        $this->patchJson("/api/bookings/{$fixture['booking']->id}/withdraw", [
            'acknowledge_no_refund' => true,
        ])->assertOk();

        Sanctum::actingAs($fixture['organizer']);
        $this->getJson(self::RECOVERY_ENDPOINT)
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->assertSame(1, BookingAttendanceException::where('booking_id', $fixture['booking']->id)->count());
    }

    public function test_governance_and_privacy_boundaries(): void
    {
        $fixture = $this->partialExceptionFixture();
        $management = $this->user('cmart_management');
        $community = $fixture['vendor'];

        auth()->forgetGuards();
        $this->getJson(self::RECOVERY_ENDPOINT)->assertUnauthorized();
        Sanctum::actingAs($management);
        $this->getJson(self::RECOVERY_ENDPOINT)->assertForbidden();
        Sanctum::actingAs($community);
        $this->getJson(self::RECOVERY_ENDPOINT)->assertForbidden();

        Sanctum::actingAs($fixture['organizer']);
        $this->getJson(self::RECOVERY_ENDPOINT)->assertOk();
        Sanctum::actingAs($this->user('super_admin'));
        $this->getJson(self::RECOVERY_ENDPOINT)->assertOk();

        Sanctum::actingAs($fixture['vendor']);
        $vendorPayload = $this->getJson("/api/vendor/bookings/{$fixture['booking']->id}")
            ->assertOk()
            ->json();
        $this->assertArrayNotHasKey('recovery_state', $vendorPayload);
        $this->assertArrayNotHasKey('recovery_channel', $vendorPayload);
    }

    public function test_repeated_reads_filters_and_pagination_do_not_mutate_history(): void
    {
        $fixture = $this->partialExceptionFixture();
        Sanctum::actingAs($fixture['organizer']);

        $beforeExceptions = BookingAttendanceException::count();
        $beforeAudit = BookingAuditLog::count();
        $beforeAllocations = BookingDayAllocation::count();

        $this->getJson(self::RECOVERY_ENDPOINT)->assertOk();
        $this->getJson(self::RECOVERY_ENDPOINT)->assertOk();
        $this->getJson(self::RECOVERY_ENDPOINT . '?payment_state=paid')->assertOk();
        $this->getJson(self::RECOVERY_ENDPOINT . '?search=' . $fixture['event']->title)->assertOk();
        $this->getJson(self::RECOVERY_ENDPOINT . '?per_page=5&page=1')->assertOk()
            ->assertJsonPath('meta.per_page', 5);

        $this->assertSame($beforeExceptions, BookingAttendanceException::count());
        $this->assertSame($beforeAudit, BookingAuditLog::count());
        $this->assertSame($beforeAllocations, BookingDayAllocation::count());
    }
}
