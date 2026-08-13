<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\AllocationValidationException;
use App\Exceptions\DomainConflictException;
use App\Models\Booking;
use App\Models\BookingAttendanceException;
use App\Models\BookingAttendanceExceptionDay;
use App\Models\BookingDayAllocation;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Models\EventSite;
use App\Models\Invoice;
use App\Models\User;
use App\Support\ManagementRole;
use App\Services\BookingAllocationLifecycleService;
use App\Services\BookingAllocationReservationService;
use App\Services\BookingAuditLogger;
use App\Services\BookingSiteCategoryValidator;
use App\Services\VendorBookingPresenter;
use App\Services\VendorCategoryResolver;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    /**
     * Direct Organizer review workflow (Phase 1.3C PR2).
     *
     * Vendor submissions enter Pending_Organizer. Only organizer and super_admin
     * may approve, reject, or request revision. No staff/manager pipeline stages.
     */
    private const STATE_TRANSITIONS = [
        'organizer' => [
            'Pending_Organizer' => ['Approved', 'Needs_Revision', 'Rejected'],
        ],
    ];

    private const PENDING_ORGANIZER = 'Pending_Organizer';

    /**
     * Submit a new vendor booking. Initial status is Pending_Organizer.
     */
    public function store(
        Request $request,
        BookingAllocationReservationService $reservationService,
        VendorCategoryResolver $categoryResolver,
        BookingSiteCategoryValidator $categoryValidator,
    ) {
        $validated = $request->validate([
            'event_id' => 'required|integer|exists:carboot_events,id',
            'event_site_ids' => 'required|array|min:1',
            'event_site_ids.*' => 'required|integer|distinct',
            'vendor_category_id' => 'nullable|integer|exists:vendor_categories,id',
            'product_category' => 'nullable|string|max:255',
            'product_details' => 'required|string|max:5000',
            'event_day_ids' => 'prohibited',
            'booking_day_ids' => 'prohibited',
            'selected_days' => 'prohibited',
            'attendance_days' => 'prohibited',
            'excluded_day_ids' => 'prohibited',
            'day_exception' => 'prohibited',
            'amount' => 'prohibited',
            'total' => 'prohibited',
            'invoice_amount' => 'prohibited',
            'unit_site_price' => 'prohibited',
            'site_quantity' => 'prohibited',
        ]);

        try {
            [$booking, $invoice] = DB::transaction(function () use (
                $request,
                $validated,
                $reservationService,
                $categoryResolver,
                $categoryValidator,
            ) {
                $event = CarbootEvent::query()
                    ->whereKey($validated['event_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$this->isEventBookable($event)) {
                    throw new AllocationValidationException(
                        'This event is no longer available for booking. Please choose another event.',
                        'event_not_bookable',
                    );
                }

                $category = $categoryResolver->resolveForOperationalUse(
                    isset($validated['vendor_category_id']) ? (int) $validated['vendor_category_id'] : null,
                    $validated['product_category'] ?? null,
                );

                $categoryValidator->assertEventOperationallyLayoutReady($event);

                $placeholderSpaceId = EventSite::query()
                    ->whereIn('id', $validated['event_site_ids'])
                    ->orderBy('id')
                    ->value('space_id');

                if (!$placeholderSpaceId) {
                    throw new AllocationValidationException(
                        'One or more event sites do not exist.',
                        'missing_event_site',
                    );
                }

                $canonicalLabel = $category->label;

                $booking = Booking::create([
                    'user_id' => $request->user()->id,
                    'space_id' => $placeholderSpaceId,
                    'carboot_event_id' => $event->id,
                    'booking_date' => $event->starts_at->toDateString(),
                    'vendor_category_id' => $category->id,
                    'category_label_snapshot' => $canonicalLabel,
                    'product_category' => $canonicalLabel,
                    'product_details' => $validated['product_details'],
                    'approval_status' => self::PENDING_ORGANIZER,
                    'revision_comment' => null,
                    'whatsapp_link' => 'https://chat.whatsapp.com/CMART_OFFICIAL_GROUP_INVITE',
                ]);

                $reservation = $reservationService->reserveForBookingInExistingTransaction(
                    $booking,
                    $validated['event_site_ids'],
                );

                $firstOperationalDay = $reservation->activeEventDays
                    ->sortBy('operational_date')
                    ->first();

                $booking->update([
                    'space_id' => $reservation->selectedSites->first()->space_id,
                    'booking_date' => $firstOperationalDay->operational_date,
                ]);

                $invoice = Invoice::create([
                    'booking_id' => $booking->id,
                    'amount' => $reservation->amount,
                    'payment_status' => 'Unpaid',
                ]);

                $siteLabels = $reservation->selectedSites->pluck('label')->implode(',');
                $rowLabels = $reservation->selectedSites
                    ->map(function (EventSite $site) {
                        $site->loadMissing('eventLayoutRow');

                        return $site->eventLayoutRow?->label ?? $site->row_label;
                    })
                    ->filter()
                    ->unique()
                    ->implode(',');

                BookingAuditLogger::log(
                    $booking,
                    $request->user(),
                    'New',
                    self::PENDING_ORGANIZER,
                    sprintf(
                        'vendor_category_id=%d; category_label_snapshot=%s; sites=%s; rows=%s',
                        $category->id,
                        $canonicalLabel,
                        $siteLabels,
                        $rowLabels,
                    ),
                    $request,
                    'vendor_submitted_booking',
                );

                return [
                    $booking->fresh(['space', 'invoice', 'carbootEvent', 'vendorCategory', 'bookingDayAllocations.eventSite.space', 'bookingDayAllocations.eventDay']),
                    $invoice,
                ];
            });
        } catch (AllocationValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 422);
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        }

        return response()->json([
            'message' => '201 Created: Booking submitted successfully. Awaiting Organizer review.',
            'booking' => VendorBookingPresenter::presentForVendor($booking, $request->user()->id),
            'invoice' => $invoice,
        ], 201);
    }

    public function applyAttendanceException(
        Request $request,
        Booking $booking,
        BookingAllocationLifecycleService $allocationLifecycle,
    ) {
        if (! ManagementRole::isOrganizerEquivalent($request->user()->role)) {
            return response()->json([
                'message' => '403 Forbidden: Organizer access required for attendance exceptions.',
            ], 403);
        }

        $validated = $request->validate([
            'retained_event_day_ids' => 'required|array|min:1',
            'retained_event_day_ids.*' => 'required|integer|distinct',
            'reason' => 'required|string|min:10|max:1000',
            'acknowledge_no_refund' => 'nullable|boolean',
        ]);

        try {
            $result = DB::transaction(function () use (
                $request,
                $booking,
                $allocationLifecycle,
                $validated,
            ) {
                $lockedBooking = Booking::query()
                    ->whereKey($booking->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($lockedBooking->approval_status, [
                    'Pending_Organizer',
                    'Needs_Revision',
                    'Approved',
                ], true)) {
                    throw new DomainConflictException(
                        'Attendance exceptions are not available for this booking status.',
                        'booking_not_eligible_for_attendance_exception',
                    );
                }

                $event = CarbootEvent::query()
                    ->whereKey($lockedBooking->carboot_event_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($event->day_generation_mode !== CarbootEvent::DAY_MODE_CALENDAR) {
                    throw new DomainConflictException(
                        'Attendance exceptions are available only for calendar-day events.',
                        'attendance_exception_requires_calendar_days',
                    );
                }

                $eventDays = EventDay::query()
                    ->forEvent((int) $event->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($eventDays->where('operational_status', EventDay::STATUS_ACTIVE)->count() < 2) {
                    throw new DomainConflictException(
                        'Attendance exceptions require at least two active EventDays.',
                        'attendance_exception_requires_multiple_days',
                    );
                }

                $allocations = BookingDayAllocation::query()
                    ->forBooking((int) $lockedBooking->id)
                    ->with(['eventDay', 'eventSite.space'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $activeAllocations = $allocations
                    ->filter(fn (BookingDayAllocation $row) => $row->occupiesSite());
                $activeDayIds = $activeAllocations
                    ->pluck('event_day_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->sort()
                    ->values();

                if ($activeDayIds->isEmpty()) {
                    throw new DomainConflictException(
                        'This booking has no active allocations to reduce.',
                        'attendance_exception_requires_active_allocations',
                    );
                }

                $retainedDayIds = collect($validated['retained_event_day_ids'])
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values();
                $eventDayIds = $eventDays->pluck('id')->map(fn ($id) => (int) $id);

                if ($retainedDayIds->diff($eventDayIds)->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'retained_event_day_ids' => [
                            'Every retained EventDay must belong to this booking event.',
                        ],
                    ]);
                }

                $notCurrentlyRetained = $retainedDayIds->diff($activeDayIds);
                if ($notCurrentlyRetained->isNotEmpty()) {
                    $historicalDayIds = $allocations
                        ->pluck('event_day_id')
                        ->map(fn ($id) => (int) $id)
                        ->unique();

                    if ($notCurrentlyRetained->intersect($historicalDayIds)->isNotEmpty()) {
                        throw new DomainConflictException(
                            'Previously released EventDays cannot be re-added.',
                            'released_event_days_cannot_be_readded',
                        );
                    }

                    throw ValidationException::withMessages([
                        'retained_event_day_ids' => [
                            'Only EventDays currently retained by this booking may be selected.',
                        ],
                    ]);
                }

                $excludedDayIds = $activeDayIds->diff($retainedDayIds)->sort()->values();
                if ($excludedDayIds->isEmpty()) {
                    return [
                        'booking' => $lockedBooking,
                        'changed' => false,
                    ];
                }

                if ($activeDayIds->count() < 2) {
                    throw new DomainConflictException(
                        'At least one active EventDay must remain.',
                        'attendance_exception_requires_retained_day',
                    );
                }

                $excludedDays = $eventDays->whereIn('id', $excludedDayIds->all());
                $startedDay = $excludedDays->first(
                    fn (EventDay $day) => $day->starts_at === null || $day->starts_at->lte(now()),
                );

                if ($startedDay) {
                    throw new DomainConflictException(
                        'Started or completed EventDays cannot be released.',
                        'event_day_already_started',
                    );
                }

                $paymentState = VendorBookingPresenter::withdrawalPaymentState($lockedBooking);
                $requiresAcknowledgement = in_array($paymentState, [
                    VendorBookingPresenter::PAYMENT_STATE_PAID,
                    VendorBookingPresenter::PAYMENT_STATE_PAYMENT_SUBMITTED,
                ], true);

                if ($requiresAcknowledgement && ! $request->boolean('acknowledge_no_refund')) {
                    throw ValidationException::withMessages([
                        'acknowledge_no_refund' => [
                            'You must acknowledge that the booking amount remains unchanged and no refund will be issued.',
                        ],
                    ]);
                }

                $appliedAt = now();
                $exception = BookingAttendanceException::create([
                    'booking_id' => $lockedBooking->id,
                    'applied_by' => $request->user()->id,
                    'applied_by_name' => $request->user()->name,
                    'reason' => $validated['reason'],
                    'payment_state' => $paymentState,
                    'no_refund_acknowledged' => $requiresAcknowledgement,
                    'previous_retained_day_count' => $activeDayIds->count(),
                    'retained_day_count' => $retainedDayIds->count(),
                    'released_day_count' => $excludedDayIds->count(),
                    'applied_at' => $appliedAt,
                ]);

                foreach ($activeDayIds as $eventDayId) {
                    BookingAttendanceExceptionDay::create([
                        'booking_attendance_exception_id' => $exception->id,
                        'event_day_id' => $eventDayId,
                        'disposition' => $retainedDayIds->contains($eventDayId)
                            ? BookingAttendanceExceptionDay::DISPOSITION_RETAINED
                            : BookingAttendanceExceptionDay::DISPOSITION_RELEASED,
                    ]);
                }

                $allocationLifecycle->releaseForBookingDays(
                    $lockedBooking,
                    $excludedDayIds->all(),
                    $request->user(),
                );

                BookingAuditLogger::log(
                    $lockedBooking,
                    $request->user(),
                    $lockedBooking->approval_status,
                    $lockedBooking->approval_status,
                    "Attendance exception #{$exception->id}",
                    $request,
                    'organizer_applied_attendance_exception',
                );

                return [
                    'booking' => $lockedBooking,
                    'changed' => true,
                ];
            });
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        }

        $result['booking']->refresh()->load([
            'user.businessProfile',
            'space',
            'invoice',
            'carbootEvent',
            'withdrawnBy',
            'auditLogs.actor',
            'attendanceExceptions.appliedBy',
            'attendanceExceptions.days.eventDay',
            'bookingDayAllocations.eventSite.space',
            'bookingDayAllocations.eventDay',
        ]);

        return response()->json([
            'message' => $result['changed']
                ? 'Attendance exception applied successfully.'
                : 'Attendance coverage is already unchanged.',
            'booking' => VendorBookingPresenter::presentForOrganizer($result['booking'], true),
        ]);
    }

    /**
     * Organizer review status update. Direct Pending_Organizer transitions only.
     */
    public function update(
        Request $request,
        Booking $booking,
        BookingAllocationLifecycleService $allocationLifecycle,
    ) {
        if (!ManagementRole::isOrganizerEquivalent($request->user()->role)) {
            return response()->json([
                'message' => '403 Forbidden: Organizer access required for booking review.',
            ], 403);
        }

        $validated = $request->validate([
            'approval_status' => 'required|in:Needs_Revision,Approved,Rejected',
            'revision_comment' => 'required_if:approval_status,Needs_Revision|nullable|string|max:2000',
        ]);

        $user = $request->user();
        $current = $booking->approval_status;
        $target = $validated['approval_status'];

        if (in_array($current, ['Withdrawn', 'Cancelled', 'Rejected'], true)) {
            return response()->json([
                'message' => '422 Unprocessable Entity: This booking is no longer active in the approval pipeline.',
                'current_status' => $current,
            ], 422);
        }

        $workflowRole = ManagementRole::workflowRoleKey($user->role);
        $allowedTargets = self::STATE_TRANSITIONS[$workflowRole][$current] ?? [];

        if (!in_array($target, $allowedTargets, true)) {
            return response()->json([
                'message' => sprintf(
                    '422 Unprocessable Entity: The transition from %s to %s is not permitted for role %s.',
                    $current,
                    $target,
                    $user->role
                ),
                'current_status' => $current,
                'attempted_status' => $target,
                'allowed_targets_for_role' => $allowedTargets,
            ], 422);
        }

        try {
            DB::transaction(function () use (
                $booking,
                $user,
                $current,
                $target,
                $validated,
                $request,
                $allocationLifecycle,
            ) {
                $booking->update([
                    'approval_status' => $target,
                    'revision_comment' => $target === 'Needs_Revision'
                        ? $validated['revision_comment']
                        : null,
                ]);

                if ($target === 'Rejected') {
                    $allocationLifecycle->releaseForBooking(
                        $booking,
                        $user,
                        BookingAllocationLifecycleService::REASON_BOOKING_REJECTED,
                    );
                }

                BookingAuditLogger::log(
                    $booking,
                    $user,
                    $current,
                    $target,
                    $target === 'Needs_Revision' ? ($validated['revision_comment'] ?? null) : null,
                    $request,
                    match ($target) {
                        'Approved' => 'organizer_approved_booking',
                        'Rejected' => 'organizer_rejected_booking',
                        'Needs_Revision' => 'organizer_requested_revision',
                        default => 'status_change',
                    },
                );
            });
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        }

        $booking->load(['user', 'space', 'invoice', 'bookingDayAllocations.eventSite.space', 'bookingDayAllocations.eventDay']);

        return response()->json([
            'message' => '200 OK: Booking status updated to ' . $target . '.',
            'booking' => VendorBookingPresenter::presentForOrganizer($booking),
        ]);
    }

    /**
     * FR4: Custom Profitability Logic (Admin Tool).
     */
    public function checkProfitability(Request $request)
    {
        $validated = $request->validate([
            'site_price' => 'required|numeric',
            'space_id' => 'sometimes|nullable|exists:spaces,id',
            'parking_lots_used' => 'required|numeric',
            'regular_parking_rate' => 'required|numeric',
            'hours_occupied' => 'required|numeric',
        ]);

        $eventRevenue = (float) $validated['site_price'];
        $parkingRevenue = $validated['parking_lots_used'] * $validated['regular_parking_rate'] * $validated['hours_occupied'];

        $isProfitable = $eventRevenue > $parkingRevenue;
        $profitMargin = $eventRevenue - $parkingRevenue;

        return response()->json([
            'event_revenue' => $eventRevenue,
            'lost_parking_revenue' => $parkingRevenue,
            'is_profitable' => $isProfitable,
            'net_profit' => $profitMargin,
            'message' => $isProfitable
                ? '200 OK: Event revenue exceeds parking revenue.'
                : '200 OK: Parking revenue exceeds event revenue.',
        ]);
    }

    /**
     * Generate a streaming PDF booking summary / invoice.
     *
     * Authorization: the booking owner (approved vendor) may download their own
     * record; CMart staff and admin may download any record. All other callers
     * receive 403 Forbidden.
     */
    public function generatePdf(Request $request, Booking $booking)
    {
        $user = $request->user();

        $isOwner = $user->id === $booking->user_id
            && $user->role === 'community'
            && $user->vendor_status === 'approved';

        $isCmartWorker = ManagementRole::isCmartWorker($user->role);

        if (!$isOwner && !$isCmartWorker) {
            return response()->json([
                'message' => '403 Forbidden: The authenticated user does not have permission to access this booking document.',
            ], 403);
        }

        $booking->load(['user', 'space', 'invoice']);

        $filename = sprintf('carboot-cmart-booking-%06d.pdf', $booking->id);

        $pdf = Pdf::loadView('invoices.booking', [
            'booking' => $booking,
            'generatedAt' => now(),
        ])->setPaper('a4');

        return $pdf->stream($filename);
    }

    /**
     * Vendor resubmission after a formal revision request.
     */
    public function resubmit(Request $request, Booking $booking)
    {
        if ($denied = $this->authorizeVendorBooking($request, $booking)) {
            return $denied;
        }

        if ($booking->approval_status !== 'Needs_Revision') {
            return response()->json([
                'message' => '422 Unprocessable Entity: Only bookings with Needs_Revision status can be resubmitted.',
                'current_status' => $booking->approval_status,
            ], 422);
        }

        if ($request->has('event_site_ids')) {
            return response()->json([
                'message' => '422 Unprocessable Entity: Event site selection cannot be changed during revision resubmission.',
                'error' => 'event_site_ids_not_allowed_on_resubmit',
            ], 422);
        }

        $validated = $request->validate([
            'space_id' => 'sometimes|required|exists:spaces,id',
            'booking_date' => 'sometimes|required|date',
        ]);

        $booking->update(array_merge($validated, [
            'approval_status' => self::PENDING_ORGANIZER,
            'revision_comment' => null,
        ]));

        $booking->load(['space', 'invoice', 'bookingDayAllocations.eventSite.space', 'bookingDayAllocations.eventDay']);

        return response()->json([
            'message' => '200 OK: Booking resubmitted successfully. Awaiting Organizer review.',
            'booking' => VendorBookingPresenter::presentForVendor($booking, $request->user()->id),
        ]);
    }

    public function index(Request $request)
    {
        return $this->paginatedBookingListResponse($request, 'full');
    }

    /**
     * @deprecated Legacy URL — same registry as /bookings for Organizer/Super Admin.
     */
    public function staffRegistry(Request $request)
    {
        if (!ManagementRole::isOrganizerEquivalent($request->user()->role)) {
            return response()->json([
                'message' => '403 Forbidden: Organizer access required.',
            ], 403);
        }

        return $this->paginatedBookingListResponse($request, 'full');
    }

    /**
     * Paginated registry with optional search, filters, and sort.
     * Always includes lightweight summary counts and the role-specific approval queue.
     */
    private function paginatedBookingListResponse(Request $request, string $access)
    {
        $filters = $this->validateBookingListRequest($request);
        $user = $request->user();

        $paginator = $this->applyBookingListFilters(
            Booking::query(),
            $filters,
        )
            ->with([
                'user.businessProfile',
                'space',
                'invoice',
                'carbootEvent',
                'withdrawnBy',
                'attendanceExceptions.appliedBy',
                'attendanceExceptions.days.eventDay',
                'bookingDayAllocations.eventSite.space',
                'bookingDayAllocations.eventDay',
            ])
            ->paginate($filters['per_page']);

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(fn (Booking $booking) => VendorBookingPresenter::presentForOrganizer($booking)),
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'summary' => $this->bookingSummaryCounts(),
            'queue' => $this->fetchQueueBookings($user),
            'access' => $access,
        ]);
    }

    private function validateBookingListRequest(Request $request): array
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:200',
            'status' => 'nullable|string|in:Pending_Organizer,Needs_Revision,Approved,Rejected,Cancelled,Withdrawn',
            'payment_status' => 'nullable|string|in:Paid,Unpaid,Pending Verification',
            'no_refund_applied' => 'nullable|boolean',
            'event_id' => 'nullable|integer|exists:carboot_events,id',
            'event' => 'nullable|integer|exists:carboot_events,id',
            'sort' => 'nullable|string|in:newest,oldest,status,event,vendor,amount',
            'direction' => 'nullable|string|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $perPage = min(max($perPage, 5), 100);

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'status' => $validated['status'] ?? null,
            'payment_status' => $validated['payment_status'] ?? null,
            'no_refund_applied' => array_key_exists('no_refund_applied', $validated)
                ? (bool) $validated['no_refund_applied']
                : null,
            'event_id' => $validated['event_id'] ?? $validated['event'] ?? null,
            'sort' => $validated['sort'] ?? 'newest',
            'direction' => $validated['direction'] ?? null,
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => $perPage,
        ];
    }

    private function applyBookingListFilters($query, array $filters)
    {
        $query = $query->where('booking_date', '>', '1970-01-01');

        if ($filters['search'] !== '') {
            $needle = '%' . mb_strtolower($filters['search']) . '%';
            $search = $filters['search'];

            $query->where(function ($builder) use ($needle, $search) {
                $builder->where('bookings.id', 'like', '%' . $search . '%')
                    ->orWhereRaw('LOWER(bookings.approval_status) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(bookings.product_category) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(bookings.product_details) LIKE ?', [$needle])
                    ->orWhereHas('user', function ($userQuery) use ($needle) {
                        $userQuery->whereRaw('LOWER(name) LIKE ?', [$needle]);
                    })
                    ->orWhereHas('user.businessProfile', function ($profileQuery) use ($needle) {
                        $profileQuery->whereRaw('LOWER(business_name) LIKE ?', [$needle]);
                    })
                    ->orWhereHas('space', function ($spaceQuery) use ($needle) {
                        $spaceQuery->whereRaw('LOWER(space_size) LIKE ?', [$needle]);
                    })
                    ->orWhereHas('invoice', function ($invoiceQuery) use ($needle) {
                        $invoiceQuery->whereRaw('LOWER(payment_status) LIKE ?', [$needle]);
                    });
            });
        }

        if ($filters['status']) {
            $query->where('approval_status', $filters['status']);
        }

        if ($filters['payment_status']) {
            $query->whereHas('invoice', function ($invoiceQuery) use ($filters) {
                $invoiceQuery->where('payment_status', $filters['payment_status']);
            });
        }

        if ($filters['no_refund_applied'] !== null) {
            $query->where('approval_status', 'Withdrawn');
            if ($filters['no_refund_applied']) {
                $query->whereHas('invoice', function ($invoiceQuery) {
                    $invoiceQuery->whereIn('payment_status', ['Paid', 'Pending Verification']);
                });
            } else {
                $query->where(function ($builder) {
                    $builder
                        ->whereDoesntHave('invoice')
                        ->orWhereHas('invoice', function ($invoiceQuery) {
                            $invoiceQuery->where('payment_status', 'Unpaid');
                        });
                });
            }
        }

        if ($filters['event_id']) {
            $event = CarbootEvent::query()->find($filters['event_id']);
            if ($event) {
                $query->where(function ($builder) use ($filters, $event) {
                    $builder->where('carboot_event_id', $filters['event_id'])
                        ->orWhere(function ($legacy) use ($event) {
                            $legacy->whereNull('carboot_event_id')
                                ->whereDate('booking_date', '>=', $event->starts_at->toDateString())
                                ->whereDate('booking_date', '<=', $event->ends_at->toDateString());
                        });
                });
            }
        }

        $direction = $filters['direction'];
        switch ($filters['sort']) {
            case 'oldest':
                $query->orderBy('bookings.created_at', 'asc');
                break;
            case 'status':
                $query->orderBy('bookings.approval_status', $direction ?? 'asc');
                break;
            case 'event':
                $query->orderBy('bookings.booking_date', $direction ?? 'desc');
                break;
            case 'vendor':
                $query->orderBy(
                    User::select('name')
                        ->whereColumn('users.id', 'bookings.user_id')
                        ->limit(1),
                    $direction ?? 'asc'
                );
                break;
            case 'amount':
                $query->orderBy(
                    Invoice::select('amount')
                        ->whereColumn('invoices.booking_id', 'bookings.id')
                        ->limit(1),
                    $direction ?? 'desc'
                );
                break;
            case 'newest':
            default:
                $query->orderBy('bookings.created_at', $direction ?? 'desc');
                break;
        }

        return $query;
    }

    private function bookingSummaryCounts(): array
    {
        $counts = Booking::query()
            ->where('booking_date', '>', '1970-01-01')
            ->selectRaw("
                SUM(CASE WHEN approval_status = 'Pending_Organizer' THEN 1 ELSE 0 END) as pending_organizer,
                SUM(CASE WHEN approval_status = 'Needs_Revision' THEN 1 ELSE 0 END) as needs_revision,
                SUM(CASE WHEN approval_status = 'Approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN approval_status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN approval_status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN approval_status = 'Withdrawn' THEN 1 ELSE 0 END) as withdrawn
            ")
            ->first();

        $pendingOrganizer = (int) ($counts->pending_organizer ?? 0);

        return [
            'pending_organizer' => $pendingOrganizer,
            'needs_revision' => (int) ($counts->needs_revision ?? 0),
            'approved' => (int) ($counts->approved ?? 0),
            'rejected' => (int) ($counts->rejected ?? 0),
            'cancelled' => (int) ($counts->cancelled ?? 0),
            'withdrawn' => (int) ($counts->withdrawn ?? 0),
        ];
    }

    private function queueStatusForUser($user): string
    {
        return self::PENDING_ORGANIZER;
    }

    private function fetchQueueBookings($user)
    {
        return Booking::query()
            ->with([
                'user.businessProfile',
                'space',
                'invoice',
                'carbootEvent',
                'withdrawnBy',
                'attendanceExceptions.appliedBy',
                'attendanceExceptions.days.eventDay',
                'bookingDayAllocations.eventSite.space',
                'bookingDayAllocations.eventDay',
            ])
            ->where('booking_date', '>', '1970-01-01')
            ->where('approval_status', $this->queueStatusForUser($user))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Booking $booking) => VendorBookingPresenter::presentForOrganizer($booking));
    }

    public function mine(Request $request)
    {
        $userId = $request->user()->id;

        return $request->user()
            ->bookings()
            ->withValidBookingDate()
            ->with([
                'space',
                'invoice',
                'carbootEvent',
                'attendanceExceptions.appliedBy',
                'attendanceExceptions.days.eventDay',
                'bookingDayAllocations.eventSite.space',
                'bookingDayAllocations.eventDay',
            ])
            ->latest()
            ->get()
            ->map(fn (Booking $booking) => VendorBookingPresenter::presentForVendor($booking, $userId))
            ->values();
    }

    public function vendorShow(Request $request, Booking $booking)
    {
        if ($denied = $this->authorizeVendorBooking($request, $booking)) {
            return $denied;
        }

        if (!$this->hasValidBookingDate($booking)) {
            return response()->json(['message' => '404 Not Found: Booking record is unavailable.'], 404);
        }

        $booking->load([
            'space',
            'invoice',
            'auditLogs.actor',
            'carbootEvent',
            'attendanceExceptions.appliedBy',
            'attendanceExceptions.days.eventDay',
            'bookingDayAllocations.eventSite.space',
            'bookingDayAllocations.eventDay',
        ]);

        return response()->json(
            VendorBookingPresenter::presentForVendor($booking, $request->user()->id),
        );
    }

    public function vendorUpdate(
        Request $request,
        Booking $booking,
        VendorCategoryResolver $categoryResolver,
        BookingSiteCategoryValidator $categoryValidator,
    ) {
        if ($denied = $this->authorizeVendorBooking($request, $booking)) {
            return $denied;
        }

        $validated = $request->validate([
            'booking_date' => 'prohibited',
            'event_day_ids' => 'prohibited',
            'booking_day_ids' => 'prohibited',
            'selected_days' => 'prohibited',
            'attendance_days' => 'prohibited',
            'excluded_day_ids' => 'prohibited',
            'day_exception' => 'prohibited',
            'event_site_ids' => 'prohibited',
            'vendor_category_id' => 'nullable|integer|exists:vendor_categories,id',
            'product_category' => 'nullable|string|max:255',
            'product_details' => 'sometimes|required|string|max:5000',
        ]);

        if (!in_array($booking->approval_status, [self::PENDING_ORGANIZER, 'Needs_Revision'], true)) {
            return response()->json([
                'message' => '422 Unprocessable Entity: Only pending bookings can be edited by vendors.',
                'current_status' => $booking->approval_status,
            ], 422);
        }

        $wantsCategoryChange = array_key_exists('vendor_category_id', $validated)
            || array_key_exists('product_category', $validated);

        try {
            DB::transaction(function () use (
                $request,
                $booking,
                $validated,
                $wantsCategoryChange,
                $categoryResolver,
                $categoryValidator,
            ) {
                $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

                $updates = [];
                if (array_key_exists('product_details', $validated)) {
                    $updates['product_details'] = $validated['product_details'];
                }

                if ($wantsCategoryChange) {
                    $category = $categoryResolver->resolveForOperationalUse(
                        isset($validated['vendor_category_id']) ? (int) $validated['vendor_category_id'] : null,
                        $validated['product_category'] ?? null,
                    );

                    $siteIds = BookingDayAllocation::query()
                        ->forBooking((int) $booking->id)
                        ->distinct()
                        ->pluck('event_site_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    if ($siteIds !== []) {
                        $categoryValidator->assertSiteIdsCompatibleWithCategory(
                            $siteIds,
                            $category,
                            (int) $booking->carboot_event_id,
                        );
                    }

                    $previousId = $booking->vendor_category_id;
                    $previousSnapshot = $booking->category_label_snapshot ?? $booking->product_category;

                    $updates['vendor_category_id'] = $category->id;
                    $updates['category_label_snapshot'] = $category->label;
                    $updates['product_category'] = $category->label;

                    $booking->update($updates);

                    BookingAuditLogger::log(
                        $booking,
                        $request->user(),
                        $booking->approval_status,
                        $booking->approval_status,
                        sprintf(
                            'category_change; previous_id=%s; previous_snapshot=%s; new_id=%d; new_snapshot=%s',
                            $previousId === null ? 'null' : (string) $previousId,
                            $previousSnapshot ?? 'null',
                            $category->id,
                            $category->label,
                        ),
                        $request,
                        'vendor_category_change',
                    );

                    return;
                }

                if ($updates !== []) {
                    $booking->update($updates);
                }
            });
        } catch (AllocationValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 422);
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        }

        return response()->json([
            'message' => '200 OK: Booking updated successfully.',
            'booking' => VendorBookingPresenter::presentForVendor(
                $booking->fresh(['space', 'invoice', 'carbootEvent', 'vendorCategory', 'bookingDayAllocations.eventSite.space', 'bookingDayAllocations.eventDay']),
                $request->user()->id,
            ),
        ]);
    }

    public function vendorCancel(
        Request $request,
        Booking $booking,
        BookingAllocationLifecycleService $allocationLifecycle,
    ) {
        if ($denied = $this->authorizeVendorBooking($request, $booking)) {
            return $denied;
        }

        $cancellable = [self::PENDING_ORGANIZER, 'Needs_Revision'];
        if (!in_array($booking->approval_status, $cancellable, true)) {
            return response()->json([
                'message' => '422 Unprocessable Entity: Only pending bookings can be withdrawn by vendors.',
                'current_status' => $booking->approval_status,
            ], 422);
        }

        $previous = $booking->approval_status;

        try {
            DB::transaction(function () use ($booking, $request, $previous, $allocationLifecycle) {
                $booking->update([
                    'approval_status' => 'Cancelled',
                    'revision_comment' => null,
                ]);

                $allocationLifecycle->releaseForBooking(
                    $booking,
                    $request->user(),
                    BookingAllocationLifecycleService::REASON_BOOKING_CANCELLED,
                );

                BookingAuditLogger::log(
                    $booking,
                    $request->user(),
                    $previous,
                    'Cancelled',
                    'Withdrawn by vendor.',
                    $request,
                    'vendor_cancel',
                );
            });
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        }

        $booking->load(['space', 'invoice', 'bookingDayAllocations.eventSite.space', 'bookingDayAllocations.eventDay']);

        return response()->json([
            'message' => '200 OK: Booking withdrawn successfully.',
            'booking' => VendorBookingPresenter::presentForVendor($booking, $request->user()->id),
        ]);
    }

    public function withdraw(
        Request $request,
        Booking $booking,
        BookingAllocationLifecycleService $allocationLifecycle,
    ) {
        if ($denied = $this->authorizeVendorBooking($request, $booking)) {
            return $denied;
        }

        if (in_array($booking->approval_status, ['Rejected', 'Cancelled'], true)) {
            return response()->json([
                'message' => 'This booking can no longer be withdrawn.',
                'current_status' => $booking->approval_status,
            ], 409);
        }

        if ($booking->approval_status === 'Withdrawn') {
            $booking->load(['space', 'invoice', 'auditLogs.actor', 'bookingDayAllocations.eventSite.space', 'bookingDayAllocations.eventDay']);

            return response()->json([
                'message' => 'Booking withdrawn successfully.',
                'booking' => VendorBookingPresenter::presentForVendor($booking, $request->user()->id),
            ]);
        }

        if (!VendorBookingPresenter::canVendorWithdraw($booking, $request->user()->id)) {
            return response()->json([
                'message' => 'This booking can no longer be withdrawn.',
                'current_status' => $booking->approval_status,
            ], 409);
        }

        $paymentState = VendorBookingPresenter::withdrawalPaymentState($booking);
        $requiresAcknowledgement = in_array(
            $paymentState,
            [VendorBookingPresenter::PAYMENT_STATE_PAID, VendorBookingPresenter::PAYMENT_STATE_PAYMENT_SUBMITTED],
            true,
        );

        $rules = [
            'withdrawal_reason' => 'nullable|string|max:500',
        ];

        if ($requiresAcknowledgement) {
            $rules['acknowledge_no_refund'] = 'required|accepted';
        }

        $validated = $request->validate($rules);

        $previous = $booking->approval_status;
        $reason = trim((string) ($validated['withdrawal_reason'] ?? ''));

        $auditNote = $reason !== '' ? $reason : 'Withdrawn by vendor.';
        if ($requiresAcknowledgement) {
            $auditNote .= ' [no-refund policy applied; payment_state=' . $paymentState . ']';
        }

        try {
            DB::transaction(function () use ($booking, $request, $previous, $reason, $auditNote, $allocationLifecycle) {
                $lockedBooking = Booking::query()
                    ->whereKey($booking->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (in_array($lockedBooking->approval_status, ['Rejected', 'Cancelled', 'Withdrawn'], true)) {
                    throw new DomainConflictException(
                        'This booking can no longer be withdrawn.',
                        'booking_not_withdrawable',
                    );
                }

                $lockedBooking->update([
                    'approval_status' => 'Withdrawn',
                    'withdrawn_at' => now(),
                    'withdrawal_reason' => $reason !== '' ? $reason : null,
                    'withdrawn_by' => $request->user()->id,
                    'revision_comment' => null,
                    'vendor_request_type' => null,
                    'vendor_request_note' => null,
                ]);

                $allocationLifecycle->releaseForBooking(
                    $lockedBooking,
                    $request->user(),
                    BookingAllocationLifecycleService::REASON_BOOKING_WITHDRAWN,
                );

                BookingAuditLogger::log(
                    $lockedBooking,
                    $request->user(),
                    $previous,
                    'Withdrawn',
                    $auditNote,
                    $request,
                    'vendor_withdraw',
                );
            });
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        }

        $booking->refresh()->load(['space', 'invoice', 'auditLogs.actor', 'bookingDayAllocations.eventSite.space', 'bookingDayAllocations.eventDay']);

        return response()->json([
            'message' => 'Booking withdrawn successfully.',
            'booking' => VendorBookingPresenter::presentForVendor($booking, $request->user()->id),
        ]);
    }

    public function vendorRequestChange(Request $request, Booking $booking)
    {
        if ($denied = $this->authorizeVendorBooking($request, $booking)) {
            return $denied;
        }

        if ($booking->approval_status !== 'Approved') {
            return response()->json([
                'message' => '422 Unprocessable Entity: Change requests are only available for approved bookings.',
            ], 422);
        }

        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $booking->update([
            'vendor_request_type' => 'change',
            'vendor_request_note' => $validated['note'],
        ]);

        BookingAuditLogger::log(
            $booking,
            $request->user(),
            'Approved',
            'Approved',
            $validated['note'],
            $request,
            'vendor_request_change',
        );

        return response()->json([
            'message' => '200 OK: Change request submitted. The Carboot Organizer will review your request.',
            'booking' => $booking->fresh(['space', 'invoice']),
        ]);
    }

    public function vendorRequestCancellation(Request $request, Booking $booking)
    {
        if ($denied = $this->authorizeVendorBooking($request, $booking)) {
            return $denied;
        }

        if ($booking->approval_status !== 'Approved') {
            return response()->json([
                'message' => '422 Unprocessable Entity: Cancellation requests are only available for approved bookings.',
            ], 422);
        }

        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $booking->update([
            'vendor_request_type' => 'cancellation',
            'vendor_request_note' => $validated['note'],
        ]);

        BookingAuditLogger::log(
            $booking,
            $request->user(),
            'Approved',
            'Approved',
            $validated['note'],
            $request,
            'vendor_request_cancellation',
        );

        return response()->json([
            'message' => '200 OK: Cancellation request submitted. The Carboot Organizer will review your request.',
            'booking' => $booking->fresh(['space', 'invoice']),
        ]);
    }

    public function vendorSubmitPayment(Request $request, Booking $booking)
    {
        if ($denied = $this->authorizeVendorBooking($request, $booking)) {
            return $denied;
        }

        if (in_array($booking->approval_status, ['Withdrawn', 'Rejected', 'Cancelled'], true)) {
            return response()->json([
                'message' => '422 Unprocessable Entity: Payment cannot be submitted for withdrawn, rejected, or cancelled bookings.',
                'current_status' => $booking->approval_status,
            ], 422);
        }

        if ($booking->approval_status !== 'Approved') {
            return response()->json([
                'message' => '422 Unprocessable Entity: Payment can only be submitted for approved bookings.',
                'current_status' => $booking->approval_status,
            ], 422);
        }

        $invoice = $booking->invoice;
        if (!$invoice) {
            return response()->json([
                'message' => '422 Unprocessable Entity: No invoice is available for this booking yet.',
            ], 422);
        }

        if ($invoice->payment_status !== 'Unpaid') {
            return response()->json([
                'message' => '422 Unprocessable Entity: Payment has already been submitted or completed for this invoice.',
                'current_payment_status' => $invoice->payment_status,
            ], 422);
        }

        $validated = $request->validate([
            'payment_proof' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $path = $validated['payment_proof']->store('payment-proofs', 'public');

        $invoice->update([
            'payment_proof_path' => $path,
            'payment_status' => 'Pending Verification',
            'payment_submitted_at' => now(),
        ]);

        $booking->load(['space', 'invoice']);

        return response()->json([
            'message' => 'Payment proof submitted successfully. Awaiting CMart verification.',
            'booking' => VendorBookingPresenter::presentForVendor($booking, $request->user()->id),
            'invoice' => $invoice->fresh(),
        ]);
    }

    public function vendorDemoPayment(
        Request $request,
        Booking $booking,
        BookingAllocationLifecycleService $allocationLifecycle,
    ) {
        if (! app()->environment(['local', 'testing', 'e2e'])) {
            return response()->json([
                'message' => 'Demo payment is not available in this environment.',
                'code' => 'demo_payment_disabled',
            ], 403);
        }

        if ($denied = $this->authorizeVendorBooking($request, $booking)) {
            return $denied;
        }

        if (in_array($booking->approval_status, ['Withdrawn', 'Rejected', 'Cancelled'], true)) {
            return response()->json([
                'message' => '422 Unprocessable Entity: Payment cannot be completed for withdrawn, rejected, or cancelled bookings.',
                'current_status' => $booking->approval_status,
            ], 422);
        }

        if ($booking->approval_status !== 'Approved') {
            return response()->json([
                'message' => '422 Unprocessable Entity: Payment can only be completed for approved bookings.',
                'current_status' => $booking->approval_status,
            ], 422);
        }

        $invoice = $booking->invoice;
        if (!$invoice) {
            return response()->json([
                'message' => '422 Unprocessable Entity: No invoice is available for this booking yet.',
            ], 422);
        }

        if ($invoice->payment_status === 'Paid') {
            return response()->json([
                'message' => '422 Unprocessable Entity: This booking has already been paid.',
                'current_payment_status' => $invoice->payment_status,
            ], 422);
        }

        if ($invoice->payment_status !== 'Unpaid') {
            return response()->json([
                'message' => '422 Unprocessable Entity: Demo payment is only available for unpaid invoices awaiting payment.',
                'current_payment_status' => $invoice->payment_status,
            ], 422);
        }

        $validated = $request->validate([
            'payment_method' => 'required|string|in:demo_fpx,demo_ewallet,demo_card,demo_manual_transfer',
        ]);

        try {
            DB::transaction(function () use ($booking, $validated, $invoice, $allocationLifecycle) {
                $invoice->update([
                    'payment_status' => 'Paid',
                    'payment_submitted_at' => now(),
                    'payment_proof_path' => 'demo-gateway/' . $validated['payment_method'],
                ]);

                $allocationLifecycle->confirmForBooking($booking);
            });
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        }

        $booking->load(['space', 'invoice', 'carbootEvent', 'bookingDayAllocations.eventSite.space', 'bookingDayAllocations.eventDay']);

        return response()->json([
            'message' => 'Payment successful. Your vendor pass is now unlocked.',
            'booking' => VendorBookingPresenter::presentForVendor($booking, $request->user()->id),
            'invoice' => $invoice->fresh(),
        ]);
    }

    public function verifyBookingPayment(
        Request $request,
        Booking $booking,
        BookingAllocationLifecycleService $allocationLifecycle,
    ) {
        if (!ManagementRole::isOrganizerEquivalent($request->user()->role)) {
            return response()->json([
                'message' => '403 Forbidden: Organizer access required for payment verification.',
            ], 403);
        }

        $invoice = $booking->invoice;
        if (!$invoice) {
            return response()->json([
                'message' => '422 Unprocessable Entity: No invoice is available for this booking.',
            ], 422);
        }

        if ($booking->approval_status !== 'Approved') {
            return response()->json([
                'message' => '422 Unprocessable Entity: Payment can only be verified for approved bookings.',
                'current_status' => $booking->approval_status,
            ], 422);
        }

        if ($invoice->payment_status === 'Paid') {
            return response()->json([
                'message' => '422 Unprocessable Entity: This payment has already been verified as paid.',
                'current_payment_status' => $invoice->payment_status,
            ], 422);
        }

        if ($invoice->payment_status !== 'Pending Verification') {
            return response()->json([
                'message' => '422 Unprocessable Entity: Only submitted payments awaiting verification can be marked as paid.',
                'current_payment_status' => $invoice->payment_status,
            ], 422);
        }

        try {
            DB::transaction(function () use ($booking, $invoice, $request, $allocationLifecycle) {
                $invoice->update([
                    'payment_status' => 'Paid',
                ]);

                $allocationLifecycle->confirmForBooking($booking);

                BookingAuditLogger::log(
                    $booking,
                    $request->user(),
                    'Approved',
                    'Approved',
                    'Payment verified as Paid.',
                    $request,
                    'organizer_verified_payment',
                );
            });
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        }

        $booking->load(['space', 'invoice', 'user.businessProfile', 'bookingDayAllocations.eventSite.space', 'bookingDayAllocations.eventDay']);

        return response()->json([
            'message' => 'Payment verified successfully. Vendor receipt and event pass are now available.',
            'booking' => VendorBookingPresenter::presentForOrganizer($booking),
            'invoice' => $invoice->fresh(),
        ]);
    }

    /**
     * Stream an uploaded payment-proof image for Organizer visual review.
     *
     * Authorization mirrors verifyBookingPayment (organizer-equivalent only).
     * Paths are resolved only from the booking invoice — never from request input.
     */
    public function paymentProof(Request $request, Booking $booking)
    {
        if (! ManagementRole::isOrganizerEquivalent($request->user()->role)) {
            return response()->json([
                'message' => '403 Forbidden: Organizer access required to view payment proofs.',
            ], 403);
        }

        $booking->loadMissing('invoice');
        $invoice = $booking->invoice;
        if (! $invoice) {
            return response()->json([
                'message' => '422 Unprocessable Entity: No invoice is available for this booking.',
            ], 422);
        }

        $rawPath = $invoice->payment_proof_path;
        if (! filled($rawPath)) {
            return response()->json([
                'message' => '404 Not Found: No payment proof has been submitted for this booking.',
            ], 404);
        }

        $path = str_replace('\\', '/', ltrim((string) $rawPath, '/'));

        if (str_starts_with($path, 'demo-gateway/')) {
            return response()->json([
                'message' => '422 Unprocessable Entity: This booking uses a demo payment marker and has no uploaded proof image.',
            ], 422);
        }

        if (! str_starts_with($path, 'payment-proofs/') || str_contains($path, '..')) {
            return response()->json([
                'message' => '422 Unprocessable Entity: The stored payment proof path is not valid.',
            ], 422);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (! in_array($extension, $allowedExtensions, true)) {
            return response()->json([
                'message' => '422 Unprocessable Entity: The payment proof file type is not supported.',
            ], 422);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return response()->json([
                'message' => '404 Not Found: The payment proof file could not be found.',
            ], 404);
        }

        $mime = $disk->mimeType($path) ?: null;
        $allowedMimes = [
            'image/jpeg' => true,
            'image/png' => true,
            'image/webp' => true,
        ];
        if ($mime === null || ! isset($allowedMimes[$mime])) {
            // Fall back to extension-mapped type when the driver cannot detect MIME.
            $mime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => null,
            };
        }
        if ($mime === null || ! isset($allowedMimes[$mime])) {
            return response()->json([
                'message' => '422 Unprocessable Entity: The payment proof file type is not supported.',
            ], 422);
        }

        return $disk->response($path, basename($path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeVendorBooking(Request $request, Booking $booking): ?\Illuminate\Http\JsonResponse
    {
        if ($booking->user_id !== $request->user()->id) {
            return response()->json([
                'message' => '403 Forbidden: The authenticated user does not have permission to access this booking.',
            ], 403);
        }

        return null;
    }

    private function hasValidBookingDate(Booking $booking): bool
    {
        if (!$booking->booking_date) {
            return false;
        }

        return $booking->booking_date->format('Y-m-d') > '1970-01-01';
    }

    public function show(Booking $booking)
    {
        $booking->load([
            'user.businessProfile',
            'space',
            'invoice',
            'carbootEvent',
            'withdrawnBy',
            'vendorCategory',
            'activeCategoryOverride.appliedBy',
            'categoryOverrides',
            'auditLogs.actor',
            'attendanceExceptions.appliedBy',
            'attendanceExceptions.days.eventDay',
            'bookingDayAllocations.eventSite.space',
            'bookingDayAllocations.eventDay',
        ]);

        return response()->json(
            VendorBookingPresenter::presentForOrganizer($booking, true),
        );
    }

    public function destroy(Booking $booking)
    {
        $user = request()->user();

        if (!$user || !ManagementRole::canAccessOrganizerRoutes($user->role)) {
            return response()->json([
                'message' => '403 Forbidden: Organizer access required.',
            ], 403);
        }

        if ($booking->bookingDayAllocations()->exists()) {
            return response()->json([
                'message' => '409 Conflict: This booking has allocation history and cannot be hard-deleted.',
                'error' => 'booking_has_allocation_history',
            ], 409);
        }

        $booking->delete();

        return response()->json([
            'message' => '200 OK: Booking deleted successfully.',
        ]);
    }

    private function isEventBookable(CarbootEvent $event): bool
    {
        if ($event->status === 'Closed') {
            return false;
        }

        return $event->ends_at !== null && $event->ends_at->gte(now());
    }
}
