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
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\Concerns\EnsuresCanonicalLayoutForSites;
use Tests\TestCase;

class OrganizerAttendanceExceptionTest extends TestCase
{
    use CleansUpTestFixtures;
    use EnsuresCanonicalLayoutForSites;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return $this->trackUser(User::create([
            'name' => 'Attendance ' . $role . ' ' . uniqid(),
            'email' => 'attendance-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => $role === 'community' ? 'approved' : 'none',
        ]));
    }

    /**
     * @return array{booking: Booking, vendor: User, organizer: User, event: CarbootEvent, days: \Illuminate\Support\Collection, sites: \Illuminate\Support\Collection}
     */
    private function fixture(
        string $paymentStatus = 'Unpaid',
        string $approvalStatus = 'Approved',
        int $dayCount = 3,
        string $dayMode = CarbootEvent::DAY_MODE_CALENDAR,
    ): array {
        $vendor = $this->user('community');
        $organizer = $this->user('organizer');
        $space = Space::defaultPhysical();
        $starts = now()->addDays(20)->setTime(8, 0);
        $event = $this->trackEvent(CarbootEvent::create([
            'title' => 'Attendance Event ' . uniqid(),
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addDays(max(0, $dayCount - 1))->setTime(17, 0),
            'status' => 'Open',
            'description' => 'Phase 2B.3 test',
            'max_slots' => 40,
            'day_generation_mode' => $dayMode,
        ]));

        $days = collect(range(0, $dayCount - 1))->map(function (int $offset) use ($event, $starts) {
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

            return $this->attachSiteToFoodLayout($event, $site, 'A');
        });

        Sanctum::actingAs($vendor);
        $response = $this->postJson('/api/bookings', [
            'event_id' => $event->id,
            'event_site_ids' => $sites->pluck('id')->all(),
            'vendor_category_id' => $this->foodVendorCategory()->id,
            'product_category' => 'Food & Beverages',
            'product_details' => 'Three-day attendance exception booking',
        ])->assertCreated()->json();

        $booking = Booking::findOrFail((int) $response['booking']['id']);
        $this->createdBookingIds[] = $booking->id;
        $this->createdInvoiceIds[] = (int) $response['invoice']['id'];
        $this->createdAllocationIds = array_merge(
            $this->createdAllocationIds,
            BookingDayAllocation::where('booking_id', $booking->id)->pluck('id')->all(),
        );
        $booking->update(['approval_status' => $approvalStatus]);
        $booking->invoice()->update([
            'payment_status' => $paymentStatus,
            'payment_proof_path' => $paymentStatus === 'Unpaid' ? null : 'payment-proofs/preserved.jpg',
            'payment_submitted_at' => $paymentStatus === 'Unpaid' ? null : now(),
        ]);
        if ($paymentStatus === 'Paid') {
            app(BookingAllocationLifecycleService::class)->confirmForBooking($booking->fresh());
        }

        return compact('booking', 'vendor', 'organizer', 'event', 'days', 'sites');
    }

    private function endpoint(Booking $booking): string
    {
        return "/api/organizer/bookings/{$booking->id}/attendance-exception";
    }

    public function test_vendor_booking_is_full_event_and_rejects_day_selection_aliases(): void
    {
        $fixture = $this->fixture();
        $this->assertSame(6, BookingDayAllocation::where('booking_id', $fixture['booking']->id)->count());
        $this->assertSame(3, BookingDayAllocation::where('booking_id', $fixture['booking']->id)->distinct()->count('event_day_id'));
        $this->assertSame(2, BookingDayAllocation::where('booking_id', $fixture['booking']->id)->distinct()->count('event_site_id'));

        foreach (['event_day_ids', 'booking_day_ids', 'selected_days', 'attendance_days', 'excluded_day_ids', 'day_exception'] as $field) {
            Sanctum::actingAs($fixture['vendor']);
            $this->postJson('/api/bookings', [
                'event_id' => $fixture['event']->id,
                'event_site_ids' => $fixture['sites']->pluck('id')->all(),
                'vendor_category_id' => $this->foodVendorCategory()->id,
                'product_category' => 'Food & Beverages',
                'product_details' => 'Forbidden day field',
                $field => [$fixture['days']->first()->id],
            ])->assertUnprocessable()->assertJsonValidationErrors([$field]);
        }

        Sanctum::actingAs($fixture['vendor']);
        $this->patchJson("/api/vendor/bookings/{$fixture['booking']->id}", [
            'booking_date' => now()->addMonth()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['booking_date']);
    }

    public function test_organizer_releases_only_excluded_paid_day_and_preserves_finances_and_history(): void
    {
        $fixture = $this->fixture('Paid');
        $booking = $fixture['booking']->fresh('invoice');
        $amount = $booking->invoice->amount;
        $proof = $booking->invoice->payment_proof_path;
        $dayThree = $fixture['days'][2];
        $before = BookingDayAllocation::where('booking_id', $booking->id)
            ->where('event_day_id', $dayThree->id)
            ->get()
            ->mapWithKeys(fn ($row) => [$row->id => [
                'reserved_at' => $row->reserved_at,
                'confirmed_at' => $row->confirmed_at,
            ]]);

        Sanctum::actingAs($fixture['organizer']);
        $this->patchJson($this->endpoint($booking), [
            'retained_event_day_ids' => $fixture['days']->take(2)->pluck('id')->all(),
            'reason' => 'Emergency family commitment on the final event day.',
            'acknowledge_no_refund' => true,
        ])
            ->assertOk()
            ->assertJsonPath('booking.approval_status', 'Approved')
            ->assertJsonPath('booking.invoice.payment_status', 'Paid')
            ->assertJsonPath('booking.invoice.amount', number_format((float) $amount, 2, '.', ''))
            ->assertJsonPath('booking.attendance_policy.has_exception', true)
            ->assertJsonPath('booking.attendance_policy.retained_event_day_count', 2)
            ->assertJsonPath('booking.attendance_policy.released_event_day_count', 1)
            ->assertJsonPath('booking.attendance_policy.released_days.0.id', $dayThree->id)
            ->assertJsonPath('booking.attendance_policy.site_labels.0', 'A01')
            ->assertJsonPath('booking.audit_timeline.1.action', 'organizer_applied_attendance_exception');

        $retained = BookingDayAllocation::where('booking_id', $booking->id)
            ->whereIn('event_day_id', $fixture['days']->take(2)->pluck('id'))
            ->get();
        $released = BookingDayAllocation::where('booking_id', $booking->id)
            ->where('event_day_id', $dayThree->id)
            ->get();
        $this->assertCount(4, $retained);
        $this->assertTrue($retained->every(fn ($row) => $row->allocation_status === 'confirmed' && $row->active_lock === 1));
        $this->assertCount(2, $released);
        $this->assertTrue($released->every(fn ($row) =>
            $row->allocation_status === 'released'
            && $row->active_lock === null
            && $row->release_reason === BookingAllocationLifecycleService::REASON_ORGANIZER_DAY_EXCEPTION
            && $row->released_by === $fixture['organizer']->id
            && $row->reserved_at->equalTo($before[$row->id]['reserved_at'])
            && $row->confirmed_at->equalTo($before[$row->id]['confirmed_at'])
        ));
        $this->assertSame(6, BookingDayAllocation::where('booking_id', $booking->id)->count());
        $this->assertSame('Paid', $booking->invoice()->value('payment_status'));
        $this->assertSame((float) $amount, (float) $booking->invoice()->value('amount'));
        $this->assertSame($proof, $booking->invoice()->value('payment_proof_path'));
        $this->assertDatabaseCount('booking_attendance_exceptions', 1);
        $this->assertDatabaseCount('booking_attendance_exception_days', 3);
        $this->assertDatabaseHas('booking_audit_logs', [
            'booking_id' => $booking->id,
            'action' => 'organizer_applied_attendance_exception',
        ]);
    }

    public function test_acknowledgement_rules_preserve_pending_verification_and_unpaid(): void
    {
        $submitted = $this->fixture('Pending Verification');
        Sanctum::actingAs($submitted['organizer']);
        $payload = [
            'retained_event_day_ids' => $submitted['days']->take(2)->pluck('id')->all(),
            'reason' => 'Operational exception required for final day.',
        ];
        $this->patchJson($this->endpoint($submitted['booking']), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['acknowledge_no_refund']);
        $this->assertSame(6, BookingDayAllocation::where('booking_id', $submitted['booking']->id)->whereNotNull('active_lock')->count());
        $this->assertSame('Pending Verification', $submitted['booking']->invoice()->value('payment_status'));
        $this->assertSame('payment-proofs/preserved.jpg', $submitted['booking']->invoice()->value('payment_proof_path'));

        $unpaid = $this->fixture();
        Sanctum::actingAs($unpaid['organizer']);
        $this->patchJson($this->endpoint($unpaid['booking']), [
            'retained_event_day_ids' => $unpaid['days']->take(2)->pluck('id')->all(),
            'reason' => 'Operational exception required for final day.',
        ])->assertOk()->assertJsonPath('booking.attendance_policy.no_refund_applied', false);
        $this->assertSame('Unpaid', $unpaid['booking']->invoice()->value('payment_status'));
    }

    public function test_event_booking_and_day_validation_boundaries(): void
    {
        $singleSession = $this->fixture('Unpaid', 'Approved', 2, CarbootEvent::DAY_MODE_SINGLE_SESSION);
        Sanctum::actingAs($singleSession['organizer']);
        $this->patchJson($this->endpoint($singleSession['booking']), [
            'retained_event_day_ids' => [$singleSession['days'][0]->id],
            'reason' => 'Single session should not allow reduction.',
        ])->assertConflict();

        $oneDay = $this->fixture('Unpaid', 'Approved', 1);
        Sanctum::actingAs($oneDay['organizer']);
        $this->patchJson($this->endpoint($oneDay['booking']), [
            'retained_event_day_ids' => [$oneDay['days'][0]->id],
            'reason' => 'One day should not allow reduction.',
        ])->assertConflict();

        $terminal = $this->fixture('Unpaid', 'Rejected');
        Sanctum::actingAs($terminal['organizer']);
        $this->patchJson($this->endpoint($terminal['booking']), [
            'retained_event_day_ids' => $terminal['days']->take(2)->pluck('id')->all(),
            'reason' => 'Terminal booking should not be changed.',
        ])->assertConflict();

        $valid = $this->fixture();
        Sanctum::actingAs($valid['organizer']);
        $this->patchJson($this->endpoint($valid['booking']), [
            'retained_event_day_ids' => [$valid['days'][0]->id, $valid['days'][0]->id],
            'reason' => 'Duplicate days are not accepted here.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['retained_event_day_ids.1']);
        $this->patchJson($this->endpoint($valid['booking']), [
            'retained_event_day_ids' => [$singleSession['days'][0]->id],
            'reason' => 'A day from another event is invalid.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['retained_event_day_ids']);
        $this->patchJson($this->endpoint($valid['booking']), [
            'retained_event_day_ids' => [],
            'reason' => 'At least one retained day is required.',
        ])->assertUnprocessable()->assertJsonValidationErrors(['retained_event_day_ids']);
    }

    public function test_started_day_cannot_be_released_but_may_remain(): void
    {
        $fixture = $this->fixture();
        $fixture['days'][0]->update([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        Sanctum::actingAs($fixture['organizer']);
        $this->patchJson($this->endpoint($fixture['booking']), [
            'retained_event_day_ids' => $fixture['days']->skip(1)->pluck('id')->all(),
            'reason' => 'Attempting to release a started day is blocked.',
        ])->assertConflict()->assertJsonPath('error', 'event_day_already_started');

        $this->patchJson($this->endpoint($fixture['booking']), [
            'retained_event_day_ids' => $fixture['days']->take(2)->pluck('id')->all(),
            'reason' => 'Started first day remains while future final day is released.',
        ])->assertOk();
        $this->assertSame(2, BookingDayAllocation::where('booking_id', $fixture['booking']->id)
            ->where('event_day_id', $fixture['days'][0]->id)->whereNotNull('active_lock')->count());
    }

    public function test_reduction_is_idempotent_monotonic_and_can_reduce_again(): void
    {
        $fixture = $this->fixture();
        Sanctum::actingAs($fixture['organizer']);
        $first = [
            'retained_event_day_ids' => $fixture['days']->take(2)->pluck('id')->all(),
            'reason' => 'First reduction releases the final future day.',
        ];
        $this->patchJson($this->endpoint($fixture['booking']), $first)->assertOk();
        $releaseTimestamps = BookingDayAllocation::where('booking_id', $fixture['booking']->id)
            ->where('event_day_id', $fixture['days'][2]->id)
            ->pluck('released_at', 'id');
        $this->patchJson($this->endpoint($fixture['booking']), $first)
            ->assertOk()
            ->assertJsonPath('message', 'Attendance coverage is already unchanged.');
        $this->assertSame(1, BookingAttendanceException::where('booking_id', $fixture['booking']->id)->count());
        $this->assertSame(1, BookingAuditLog::where('booking_id', $fixture['booking']->id)
            ->where('action', 'organizer_applied_attendance_exception')->count());
        $this->assertEquals($releaseTimestamps, BookingDayAllocation::where('booking_id', $fixture['booking']->id)
            ->where('event_day_id', $fixture['days'][2]->id)->pluck('released_at', 'id'));

        $this->patchJson($this->endpoint($fixture['booking']), [
            'retained_event_day_ids' => [$fixture['days'][0]->id],
            'reason' => 'Second reduction releases one additional future day.',
        ])->assertOk()->assertJsonPath('booking.attendance_policy.retained_event_day_count', 1);
        $this->assertSame(2, BookingAttendanceException::where('booking_id', $fixture['booking']->id)->count());

        $this->patchJson($this->endpoint($fixture['booking']), [
            'retained_event_day_ids' => $fixture['days']->pluck('id')->all(),
            'reason' => 'Previously released days must not be restored.',
        ])->assertConflict()->assertJsonPath('error', 'released_event_days_cannot_be_readded');
    }

    public function test_governance_allows_organizer_and_super_admin_only(): void
    {
        $fixture = $this->fixture();
        $payload = [
            'retained_event_day_ids' => $fixture['days']->take(2)->pluck('id')->all(),
            'reason' => 'Governance boundary attendance exception.',
        ];

        auth()->forgetGuards();
        $this->patchJson($this->endpoint($fixture['booking']), $payload)->assertUnauthorized();
        foreach (['community', 'cmart_management'] as $role) {
            Sanctum::actingAs($this->user($role));
            $this->patchJson($this->endpoint($fixture['booking']), $payload)->assertForbidden();
        }

        $super = $this->user('super_admin');
        Sanctum::actingAs($super);
        $this->patchJson($this->endpoint($fixture['booking']), $payload)->assertOk();
    }

    public function test_vendor_presenter_is_safe_and_read_only(): void
    {
        $fixture = $this->fixture('Paid');
        Sanctum::actingAs($fixture['organizer']);
        $this->patchJson($this->endpoint($fixture['booking']), [
            'retained_event_day_ids' => $fixture['days']->take(2)->pluck('id')->all(),
            'reason' => 'Safe vendor presenter attendance exception.',
            'acknowledge_no_refund' => true,
        ])->assertOk();

        Sanctum::actingAs($fixture['vendor']);
        $response = $this->getJson("/api/vendor/bookings/{$fixture['booking']->id}")
            ->assertOk()
            ->assertJsonPath('attendance_policy.has_exception', true)
            ->assertJsonPath('attendance_policy.reason', 'Safe vendor presenter attendance exception.')
            ->assertJsonPath('attendance_policy.applied_by.name', 'Organizer')
            ->assertJsonPath('attendance_policy.no_refund_applied', true)
            ->assertJsonMissingPath('attendance_policy.exception_history')
            ->assertJsonMissingPath('audit_timeline')
            ->assertJsonMissingPath('booking_day_allocations')
            ->assertJsonMissingPath('attendance_policy.applied_by.id');
        $this->assertStringNotContainsString('active_lock', $response->getContent());
        $this->assertStringNotContainsString('Attendance exception #', $response->getContent());
    }

    public function test_release_failure_rolls_back_exception_allocations_audit_and_finances(): void
    {
        $fixture = $this->fixture('Paid');
        $invoice = $fixture['booking']->invoice()->firstOrFail();
        $auditBefore = BookingAuditLog::where('booking_id', $fixture['booking']->id)->count();
        $mock = Mockery::mock(BookingAllocationLifecycleService::class);
        $mock->shouldReceive('releaseForBookingDays')->once()->andThrow(new \RuntimeException('simulated release failure'));
        $this->app->instance(BookingAllocationLifecycleService::class, $mock);

        Sanctum::actingAs($fixture['organizer']);
        try {
            $this->withoutExceptionHandling()->patchJson($this->endpoint($fixture['booking']), [
                'retained_event_day_ids' => $fixture['days']->take(2)->pluck('id')->all(),
                'reason' => 'This simulated failure must roll back fully.',
                'acknowledge_no_refund' => true,
            ]);
            $this->fail('Expected simulated release failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('simulated release failure', $exception->getMessage());
        }

        $this->assertSame(0, BookingAttendanceException::where('booking_id', $fixture['booking']->id)->count());
        $this->assertSame(6, BookingDayAllocation::where('booking_id', $fixture['booking']->id)
            ->where('allocation_status', 'confirmed')->where('active_lock', 1)->count());
        $this->assertSame($auditBefore, BookingAuditLog::where('booking_id', $fixture['booking']->id)->count());
        $this->assertSame('Paid', Invoice::findOrFail($invoice->id)->payment_status);
        $this->assertSame((float) $invoice->amount, (float) Invoice::findOrFail($invoice->id)->amount);
    }
}
