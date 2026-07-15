<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\BookingAttendanceException;
use App\Models\BookingAttendanceExceptionDay;
use App\Models\BookingAuditLog;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use App\Services\BookingAllocationLifecycleService;
use App\Services\BookingAllocationReservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2A.8.1 — test-only EventSite/EventDay fixtures for browser E2E.
 *
 * Creates a self-contained, bookable Carboot event with active EventDays and a
 * contiguous active EventSite row so the cinema-style selector can be exercised
 * end-to-end without relying on permanent local demo layout data.
 *
 * Safety:
 * - local environment only
 * - never deletes the shared Space catalogue (baseline spaces stay intact)
 * - all fixtures carry the E2E-SITE-FIX marker and are removed by --cleanup
 * - no HTTP surface: CLI only, so it is not production-accessible
 */
class E2ESiteFixtures extends Command
{
    protected $signature = 'e2e:site-fixtures
                            {action=create : create or cleanup}
                            {--json : Emit machine-readable JSON for the E2E harness}
                            {--site= : Site label for recovery competing allocation}';

    protected $description = 'Phase 2A.8.1: Create or remove temporary E2E EventSite/EventDay fixtures';

    public const MARKER = 'E2E-SITE-FIX';
    private const EVENT_TITLE = self::MARKER . ' Carboot Weekend';
    private const VENDOR_EMAIL = 'e2e-site-fix-vendor@example.com';
    private const ORGANIZER_EMAIL = 'e2e-site-fix-organizer@example.com';
    private const SPACE_SIZE = 'Standard (1 Parking Lot)';

    public function handle(): int
    {
        $env = config('app.env');
        if ($env !== 'local' && ! app()->runningUnitTests()) {
            $this->error("Refusing to run: app environment is [{$env}], expected [local].");

            return self::FAILURE;
        }

        return match ($this->argument('action')) {
            'create' => $this->createFixtures(),
            'create-paid-booking' => $this->createPaidBookingFixture(),
            'create-payment-submitted-booking' => $this->createPaymentSubmittedBookingFixture(),
            'create-paid-three-day-booking' => $this->createPaidThreeDayBookingFixture(),
            'create-released-day-recovery' => $this->createReleasedDayRecoveryFixture(),
            'recovery-add-competing-allocation' => $this->recoveryAddCompetingAllocation(),
            'recovery-status' => $this->recoveryStatus(),
            'attendance-status' => $this->attendanceStatus(),
            'cleanup' => $this->cleanupFixtures(),
            default => $this->invalidAction(),
        };
    }

    private function invalidAction(): int
    {
        $this->error(
            "Unknown action [{$this->argument('action')}]. Use 'create', "
            . "'create-paid-booking', 'create-payment-submitted-booking', "
            . "'create-paid-three-day-booking', 'create-released-day-recovery', "
            . "'recovery-add-competing-allocation', 'recovery-status', "
            . "'attendance-status', or 'cleanup'.",
        );

        return self::FAILURE;
    }

    private function createPaidBookingFixture(): int
    {
        return $this->createWithdrawalBookingFixture(
            'Paid',
            'E2E paid booking fixture created.',
            true,
            2,
        );
    }

    private function createPaymentSubmittedBookingFixture(): int
    {
        return $this->createWithdrawalBookingFixture(
            'Pending Verification',
            'E2E payment-submitted booking fixture created.',
            false,
            2,
        );
    }

    private function createPaidThreeDayBookingFixture(): int
    {
        return $this->createWithdrawalBookingFixture(
            'Paid',
            'E2E paid three-day attendance fixture created.',
            true,
            3,
        );
    }

    private function createReleasedDayRecoveryFixture(): int
    {
        $this->purge();
        $base = $this->buildBaseFixtures(3);

        $payload = DB::transaction(function () use ($base) {
            $vendor = User::where('email', self::VENDOR_EMAIL)->firstOrFail();
            $organizer = User::firstOrCreate(
                ['email' => self::ORGANIZER_EMAIL],
                [
                    'name' => 'E2E Site Fixture Organizer',
                    'password' => bcrypt('password123'),
                    'role' => 'organizer',
                    'vendor_status' => 'none',
                ],
            );
            $event = CarbootEvent::findOrFail($base['event_id']);
            $siteIds = array_slice($base['site_ids'], 0, 2);
            $dayIds = $base['day_ids'];
            $releasedDayId = $dayIds[2];

            $booking = Booking::create([
                'user_id' => $vendor->id,
                'space_id' => $base['space_id'],
                'carboot_event_id' => $event->id,
                'booking_date' => $event->starts_at->toDateString(),
                'product_category' => 'Food & Beverages',
                'product_details' => self::MARKER . ' released-day recovery E2E booking',
                'approval_status' => 'Approved',
            ]);

            $reservationService = app(BookingAllocationReservationService::class);
            $reservation = $reservationService->reserveForBookingInExistingTransaction($booking, $siteIds);
            $invoice = Invoice::create([
                'booking_id' => $booking->id,
                'amount' => $reservation->amount,
                'payment_status' => 'Paid',
                'payment_proof_path' => self::MARKER . '/payment-proof-marker.jpg',
                'payment_submitted_at' => now(),
            ]);
            app(BookingAllocationLifecycleService::class)->confirmForBooking($booking->fresh());

            $reason = 'Emergency family commitment on the final event day.';
            app(BookingAllocationLifecycleService::class)->releaseForBookingDays(
                $booking->fresh(),
                [$releasedDayId],
                $organizer,
                BookingAllocationLifecycleService::REASON_ORGANIZER_DAY_EXCEPTION,
            );

            $exception = BookingAttendanceException::create([
                'booking_id' => $booking->id,
                'applied_by' => $organizer->id,
                'applied_by_name' => $organizer->name,
                'reason' => $reason,
                'payment_state' => BookingAttendanceException::PAYMENT_PAID,
                'no_refund_acknowledged' => true,
                'previous_retained_day_count' => 3,
                'retained_day_count' => 2,
                'released_day_count' => 1,
                'applied_at' => now(),
            ]);
            foreach ($dayIds as $index => $dayId) {
                BookingAttendanceExceptionDay::create([
                    'booking_attendance_exception_id' => $exception->id,
                    'event_day_id' => $dayId,
                    'disposition' => $index < 2
                        ? BookingAttendanceExceptionDay::DISPOSITION_RETAINED
                        : BookingAttendanceExceptionDay::DISPOSITION_RELEASED,
                ]);
            }
            BookingAuditLog::create([
                'booking_id' => $booking->id,
                'actor_user_id' => $organizer->id,
                'action' => 'organizer_applied_attendance_exception',
                'from_status' => 'Approved',
                'to_status' => 'Approved',
                'revision_comment' => $reason,
            ]);

            $booking->refresh()->load(['invoice', 'bookingDayAllocations.eventSite', 'bookingDayAllocations.eventDay']);

            return array_merge($base, [
                'booking_id' => $booking->id,
                'invoice_id' => $invoice->id,
                'invoice_amount' => (float) $invoice->amount,
                'payment_status' => $invoice->payment_status,
                'released_day_id' => $releasedDayId,
                'released_day_operational_date' => EventDay::find($releasedDayId)?->operational_date?->format('Y-m-d'),
                'retained_day_ids' => array_slice($dayIds, 0, 2),
                'site_labels' => ['A01', 'A02'],
                'organizer_email' => self::ORGANIZER_EMAIL,
                'organizer_password' => 'password123',
                'exception_reason' => $reason,
                'allocation_count' => $booking->bookingDayAllocations->count(),
                'released_allocation_count' => $booking->bookingDayAllocations
                    ->where('allocation_status', BookingDayAllocation::STATUS_RELEASED)
                    ->count(),
                'active_allocation_count' => $booking->bookingDayAllocations
                    ->where('active_lock', 1)
                    ->count(),
            ]);
        });

        return $this->emitFixtureResult('E2E released-day recovery fixture created.', $payload);
    }

    private function recoveryAddCompetingAllocation(): int
    {
        $siteLabel = strtoupper((string) $this->option('site'));
        if ($siteLabel === '') {
            $this->error('The --site option is required (for example --site=A02).');

            return self::FAILURE;
        }

        $booking = Booking::query()
            ->whereHas('carbootEvent', fn ($query) => $query->where('title', 'like', self::MARKER . '%'))
            ->whereHas('bookingDayAllocations', fn ($query) => $query->where(
                'release_reason',
                BookingAllocationLifecycleService::REASON_ORGANIZER_DAY_EXCEPTION,
            ))
            ->latest('id')
            ->firstOrFail();
        $releasedDayId = BookingDayAllocation::query()
            ->where('booking_id', $booking->id)
            ->where('release_reason', BookingAllocationLifecycleService::REASON_ORGANIZER_DAY_EXCEPTION)
            ->value('event_day_id');
        $site = EventSite::query()
            ->where('carboot_event_id', $booking->carboot_event_id)
            ->where('label', $siteLabel)
            ->firstOrFail();
        $competitor = User::firstOrCreate(
            ['email' => 'e2e-site-fix-competitor@example.com'],
            [
                'name' => 'E2E Recovery Competitor',
                'password' => bcrypt('password123'),
                'role' => 'community',
                'vendor_status' => 'approved',
            ],
        );

        $payload = DB::transaction(function () use ($booking, $releasedDayId, $site, $competitor) {
            $competingBooking = Booking::create([
                'user_id' => $competitor->id,
                'space_id' => $site->space_id,
                'carboot_event_id' => $booking->carboot_event_id,
                'booking_date' => EventDay::find($releasedDayId)?->operational_date,
                'product_category' => 'Others',
                'product_details' => self::MARKER . ' recovery competing allocation',
                'approval_status' => 'Approved',
            ]);
            $allocation = BookingDayAllocation::create([
                'booking_id' => $competingBooking->id,
                'event_day_id' => $releasedDayId,
                'event_site_id' => $site->id,
                'allocation_status' => BookingDayAllocation::STATUS_CONFIRMED,
                'reserved_at' => now(),
                'confirmed_at' => now(),
                'active_lock' => 1,
            ]);

            return [
                'source_booking_id' => $booking->id,
                'competing_booking_id' => $competingBooking->id,
                'competing_allocation_id' => $allocation->id,
                'event_day_id' => $releasedDayId,
                'event_site_id' => $site->id,
                'site_label' => $site->label,
            ];
        });

        return $this->emitFixtureResult('E2E recovery competing allocation created.', $payload);
    }

    private function recoveryStatus(): int
    {
        $booking = Booking::query()
            ->whereHas('carbootEvent', fn ($query) => $query->where('title', 'like', self::MARKER . '%'))
            ->whereHas('bookingDayAllocations', fn ($query) => $query->where(
                'release_reason',
                BookingAllocationLifecycleService::REASON_ORGANIZER_DAY_EXCEPTION,
            ))
            ->with('invoice')
            ->latest('id')
            ->firstOrFail();

        return $this->emitFixtureResult('E2E recovery fixture status.', [
            'booking_id' => $booking->id,
            'approval_status' => $booking->approval_status,
            'payment_status' => $booking->invoice?->payment_status,
            'allocation_count' => BookingDayAllocation::where('booking_id', $booking->id)->count(),
            'released_count' => BookingDayAllocation::where('booking_id', $booking->id)
                ->where('allocation_status', BookingDayAllocation::STATUS_RELEASED)
                ->count(),
            'active_count' => BookingDayAllocation::where('booking_id', $booking->id)
                ->where('active_lock', 1)
                ->count(),
            'exception_count' => BookingAttendanceException::where('booking_id', $booking->id)->count(),
            'audit_count' => BookingAuditLog::where('booking_id', $booking->id)->count(),
        ]);
    }

    private function attendanceStatus(): int
    {
        $booking = Booking::query()
            ->whereHas('carbootEvent', fn ($query) =>
                $query->where('title', 'like', self::MARKER . '%'))
            ->with('invoice')
            ->latest('id')
            ->firstOrFail();
        $allocations = BookingDayAllocation::where('booking_id', $booking->id)->get();

        return $this->emitFixtureResult('E2E attendance fixture status.', [
            'booking_id' => $booking->id,
            'approval_status' => $booking->approval_status,
            'invoice_amount' => (float) $booking->invoice?->amount,
            'payment_status' => $booking->invoice?->payment_status,
            'payment_proof_present' => filled($booking->invoice?->payment_proof_path),
            'allocation_count' => $allocations->count(),
            'confirmed_count' => $allocations->where('allocation_status', 'confirmed')->count(),
            'released_count' => $allocations->where('allocation_status', 'released')->count(),
            'active_count' => $allocations->where('active_lock', 1)->count(),
            'released_lock_null_count' => $allocations
                ->where('allocation_status', 'released')
                ->whereNull('active_lock')
                ->count(),
            'attendance_release_reason_count' => $allocations
                ->where('release_reason', BookingAllocationLifecycleService::REASON_ORGANIZER_DAY_EXCEPTION)
                ->count(),
            'released_by_ids' => $allocations
                ->where('allocation_status', 'released')
                ->pluck('released_by')
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'exception_count' => BookingAttendanceException::where('booking_id', $booking->id)->count(),
            'audit_count' => BookingAuditLog::where('booking_id', $booking->id)
                ->where('action', 'organizer_applied_attendance_exception')
                ->count(),
        ]);
    }

    private function createWithdrawalBookingFixture(
        string $paymentStatus,
        string $message,
        bool $confirmAllocations,
        int $dayCount,
    ): int
    {
        $this->purge();
        $base = $this->buildBaseFixtures($dayCount);

        $payload = DB::transaction(function () use ($base, $paymentStatus, $confirmAllocations) {
            $vendor = User::where('email', self::VENDOR_EMAIL)->firstOrFail();
            $event = CarbootEvent::findOrFail($base['event_id']);
            $siteIds = array_slice($base['site_ids'], 0, 2);

            $booking = Booking::create([
                'user_id' => $vendor->id,
                'space_id' => $base['space_id'],
                'carboot_event_id' => $event->id,
                'booking_date' => $event->starts_at->toDateString(),
                'product_category' => 'Food & Beverages',
                'product_details' => self::MARKER . ' withdrawal reconciliation E2E booking',
                'approval_status' => 'Approved',
                'revision_comment' => null,
                'whatsapp_link' => 'https://chat.whatsapp.com/CMART_OFFICIAL_GROUP_INVITE',
            ]);

            $reservationService = app(BookingAllocationReservationService::class);
            $reservation = $reservationService->reserveForBookingInExistingTransaction($booking, $siteIds);

            $booking->update([
                'space_id' => $reservation->selectedSites->first()->space_id,
                'booking_date' => $reservation->activeEventDays->sortBy('operational_date')->first()->operational_date,
            ]);

            $invoice = Invoice::create([
                'booking_id' => $booking->id,
                'amount' => $reservation->amount,
                'payment_status' => $paymentStatus,
                'payment_proof_path' => self::MARKER . '/payment-proof-marker.jpg',
                'payment_submitted_at' => now(),
            ]);

            if ($confirmAllocations) {
                app(BookingAllocationLifecycleService::class)->confirmForBooking($booking->fresh());
            }

            $booking->refresh()->load(['invoice', 'bookingDayAllocations.eventSite']);

            return array_merge($base, [
                'booking_id' => $booking->id,
                'invoice_id' => $invoice->id,
                'invoice_amount' => (float) $invoice->amount,
                'payment_status' => $invoice->payment_status,
                'payment_proof_marker' => self::MARKER . '/payment-proof-marker.jpg',
                'allocation_status' => $confirmAllocations ? 'confirmed' : 'reserved',
                'site_labels' => $booking->bookingDayAllocations
                    ->pluck('eventSite.label')
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
            ]);
        });

        return $this->emitFixtureResult($message, $payload);
    }

    private function createFixtures(): int
    {
        $this->purge();
        $result = $this->buildBaseFixtures();

        return $this->emitFixtureResult('E2E site fixtures created.', $result);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBaseFixtures(int $dayCount = 2): array
    {
        return DB::transaction(function () use ($dayCount) {
            $space = Space::query()->firstOrCreate(
                ['space_size' => self::SPACE_SIZE],
                [
                    'location' => 'CMart Kompleks Changlun',
                    'price' => 30.00,
                    'status' => 'Available',
                ],
            );

            $vendor = User::create([
                'name' => 'E2E Site Fixture Vendor',
                'email' => self::VENDOR_EMAIL,
                'password' => bcrypt('password123'),
                'role' => 'community',
                'vendor_status' => 'approved',
            ]);

            $starts = now()->addDays(9)->setTime(8, 0, 0);
            $event = CarbootEvent::create([
                'title' => self::EVENT_TITLE,
                'description' => self::MARKER . ' temporary browser E2E fixture event',
                'starts_at' => $starts,
                'ends_at' => $starts->copy()->addDays($dayCount - 1)->setTime(17, 0, 0),
                'status' => 'Open',
                'max_slots' => 100,
                'day_generation_mode' => CarbootEvent::DAY_MODE_CALENDAR,
            ]);

            $dayIds = [];
            for ($d = 0; $d < $dayCount; $d++) {
                $dayStart = $starts->copy()->addDays($d);
                $day = EventDay::create([
                    'carboot_event_id' => $event->id,
                    'operational_date' => $dayStart->toDateString(),
                    'starts_at' => $dayStart,
                    'ends_at' => $dayStart->copy()->setTime(17, 0, 0),
                    'operational_status' => EventDay::STATUS_ACTIVE,
                    'display_order' => $d + 1,
                ]);
                $dayIds[] = $day->id;
            }

            $siteIds = [];
            $siteLabels = [];
            for ($p = 1; $p <= 3; $p++) {
                $label = sprintf('A%02d', $p);
                $site = EventSite::create([
                    'carboot_event_id' => $event->id,
                    'space_id' => $space->id,
                    'label' => $label,
                    'row_label' => 'A',
                    'position_number' => $p,
                    'grid_row' => 1,
                    'grid_column' => $p,
                    'display_order' => $p,
                    'operational_status' => EventSite::STATUS_ACTIVE,
                ]);
                $siteIds[] = $site->id;
                $siteLabels[] = $label;
            }

            return [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'vendor_email' => $vendor->id ? self::VENDOR_EMAIL : null,
                'vendor_password' => 'password123',
                'space_id' => $space->id,
                'space_name' => $space->space_size,
                'day_ids' => $dayIds,
                'site_ids' => $siteIds,
                'site_labels' => $siteLabels,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function emitFixtureResult(string $message, array $result): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info($message);
        $this->table(
            ['Field', 'Value'],
            collect($result)->map(fn ($value, $key) => [
                $key,
                is_array($value) ? implode(', ', $value) : $value,
            ])->values()->all(),
        );

        return self::SUCCESS;
    }

    private function cleanupFixtures(): int
    {
        $summary = $this->purge();

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('E2E site fixtures removed.');
        $this->table(
            ['Deleted', 'Count'],
            collect($summary)->map(fn ($v, $k) => [$k, $v])->values()->all(),
        );

        return self::SUCCESS;
    }

    /**
     * Foreign-key-safe, idempotent removal of every fixture carrying the marker.
     * The shared Space catalogue is intentionally preserved.
     *
     * @return array<string, int>
     */
    private function purge(): array
    {
        return DB::transaction(function () {
            $eventIds = CarbootEvent::query()
                ->where('title', 'like', self::MARKER . '%')
                ->pluck('id')
                ->all();

            $bookingIds = $eventIds === []
                ? []
                : Booking::query()->whereIn('carboot_event_id', $eventIds)->pluck('id')->all();

            $userIds = User::query()
                ->where('email', 'like', 'e2e-site-fix%')
                ->pluck('id')
                ->all();

            $bookingIds = array_values(array_unique(array_merge(
                $bookingIds,
                $userIds === []
                    ? []
                    : Booking::query()->whereIn('user_id', $userIds)->pluck('id')->all(),
            )));

            $allocations = 0;
            $invoices = 0;
            $bookings = 0;
            $auditLogs = 0;
            $exceptionDays = 0;
            $exceptions = 0;

            if ($bookingIds !== []) {
                $exceptionIds = BookingAttendanceException::whereIn('booking_id', $bookingIds)->pluck('id');
                $exceptionDays = BookingAttendanceExceptionDay::whereIn(
                    'booking_attendance_exception_id',
                    $exceptionIds,
                )->delete();
                $exceptions = BookingAttendanceException::whereIn('id', $exceptionIds)->delete();
                $allocations = BookingDayAllocation::whereIn('booking_id', $bookingIds)->delete();
                $invoices = Invoice::whereIn('booking_id', $bookingIds)->delete();
                $auditLogs = BookingAuditLog::whereIn('booking_id', $bookingIds)->delete();
                $bookings = Booking::whereIn('id', $bookingIds)->delete();
            }

            $sites = 0;
            $days = 0;
            $events = 0;

            if ($eventIds !== []) {
                // Defensive: remove any lingering allocations tied to fixture layout.
                BookingDayAllocation::whereIn(
                    'event_site_id',
                    EventSite::whereIn('carboot_event_id', $eventIds)->pluck('id'),
                )->delete();

                $sites = EventSite::whereIn('carboot_event_id', $eventIds)->delete();
                $days = EventDay::whereIn('carboot_event_id', $eventIds)->delete();
                $events = CarbootEvent::whereIn('id', $eventIds)->delete();
            }

            $users = 0;
            if ($userIds !== []) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', User::class)
                    ->whereIn('tokenable_id', $userIds)
                    ->delete();

                if (class_exists(\App\Models\UserBookingPreference::class)) {
                    \App\Models\UserBookingPreference::whereIn('user_id', $userIds)->delete();
                }

                if (class_exists(\App\Models\VendorBusinessProfile::class)) {
                    \App\Models\VendorBusinessProfile::whereIn('user_id', $userIds)->delete();
                }

                BookingAuditLog::whereIn('actor_user_id', $userIds)->delete();
                $users = User::whereIn('id', $userIds)->delete();
            }

            return [
                'booking_day_allocations' => (int) $allocations,
                'invoices' => (int) $invoices,
                'booking_audit_logs' => (int) $auditLogs,
                'booking_attendance_exception_days' => (int) $exceptionDays,
                'booking_attendance_exceptions' => (int) $exceptions,
                'bookings' => (int) $bookings,
                'event_sites' => (int) $sites,
                'event_days' => (int) $days,
                'carboot_events' => (int) $events,
                'users' => (int) $users,
            ];
        });
    }
}
