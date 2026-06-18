<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\Invoice;
use App\Models\Space;
use App\Models\User;
use App\Support\ManagementRole;
use App\Services\BookingAuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    /**
     * 2-Tier Corporate Approval Pipeline state machine.
     *
     * Strict permitted transitions per role. Any attempt outside this matrix
     * is rejected with HTTP 422 Unprocessable Entity.
     */
    private const STATE_TRANSITIONS = [
        'staff' => [
            'Pending_Staff' => ['Pending_Boss', 'Needs_Revision', 'Rejected'],
        ],
        'manager' => [
            'Pending_Boss' => ['Approved', 'Needs_Revision', 'Rejected'],
        ],
    ];

    /**
     * FR1 & FR2: Submit a new vendor booking. Initial status is Pending_Staff.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tapak_quantity' => 'required|integer|min:1',
            'total_price' => 'required|numeric|min:20',
            'booking_date' => 'required|date',
            'product_category' => [
                'required',
                'string',
                Rule::in([
                    'Pre-loved / Thrift',
                    'Food & Beverages',
                    'Clothing & Apparel',
                    'Handicrafts & Art',
                    'Electronics & Gadgets',
                    'Others',
                ]),
            ],
            'product_details' => 'required|string|max:5000',
        ]);

        $expectedTotal = $validated['tapak_quantity'] * 20;
        if ((float) $validated['total_price'] !== (float) $expectedTotal) {
            return response()->json([
                'message' => '422 Unprocessable Entity: Total price must equal tapak quantity × RM 20.',
            ], 422);
        }

        $space = Space::query()
            ->where('space_size', 'Standard (1 Parking Lot)')
            ->firstOrFail();

        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'space_id' => $space->id,
            'booking_date' => $validated['booking_date'],
            'product_category' => $validated['product_category'],
            'product_details' => $validated['product_details'],
            'approval_status' => 'Pending_Staff',
            'revision_comment' => null,
            'whatsapp_link' => 'https://chat.whatsapp.com/CMART_OFFICIAL_GROUP_INVITE',
        ]);

        $invoice = Invoice::create([
            'booking_id' => $booking->id,
            'amount' => $validated['total_price'],
            'payment_status' => 'Unpaid',
        ]);

        return response()->json([
            'message' => '201 Created: Booking submitted successfully. Awaiting Tier 1 staff review.',
            'booking' => $booking,
            'invoice' => $invoice,
        ], 201);
    }

    /**
     * Tier-aware status update. Enforces the corporate approval pipeline.
     *
     * Permitted transitions:
     *   staff:   Pending_Staff -> Pending_Boss | Needs_Revision | Rejected
     *   manager: Pending_Boss  -> Approved     | Needs_Revision | Rejected
     */
    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'approval_status' => 'required|in:Pending_Boss,Needs_Revision,Approved,Rejected',
            'revision_comment' => 'required_if:approval_status,Needs_Revision|nullable|string|max:2000',
        ]);

        $user = $request->user();
        $current = $booking->approval_status;
        $target = $validated['approval_status'];

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

        $booking->update([
            'approval_status' => $target,
            'revision_comment' => $target === 'Needs_Revision'
                ? $validated['revision_comment']
                : null,
        ]);

        BookingAuditLogger::log(
            $booking,
            $user,
            $current,
            $target,
            $target === 'Needs_Revision' ? ($validated['revision_comment'] ?? null) : null,
            $request,
        );

        return response()->json([
            'message' => '200 OK: Booking status updated to ' . $target . '.',
            'booking' => $booking->fresh(['user', 'space', 'invoice']),
        ]);
    }

    /**
     * FR4: Custom Profitability Logic (Admin Tool).
     */
    public function checkProfitability(Request $request)
    {
        $validated = $request->validate([
            'space_id' => 'required|exists:spaces,id',
            'parking_lots_used' => 'required|numeric',
            'regular_parking_rate' => 'required|numeric',
            'hours_occupied' => 'required|numeric',
        ]);

        $space = Space::findOrFail($validated['space_id']);

        $eventRevenue = $space->price;
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

        $validated = $request->validate([
            'space_id' => 'sometimes|required|exists:spaces,id',
            'booking_date' => 'sometimes|required|date',
        ]);

        $booking->update(array_merge($validated, [
            'approval_status' => 'Pending_Staff',
            'revision_comment' => null,
        ]));

        return response()->json([
            'message' => '200 OK: Booking resubmitted successfully. Awaiting Tier 1 staff review.',
            'booking' => $booking->fresh(['space', 'invoice']),
        ]);
    }

    public function index(Request $request)
    {
        return $this->paginatedBookingListResponse($request, 'full');
    }

    /**
     * Staff-safe read-only booking registry and queue data source.
     * Tier 1 staff use this endpoint instead of manager-only actions.
     */
    public function staffRegistry(Request $request)
    {
        if (!ManagementRole::isCmartWorker($request->user()->role)) {
            return response()->json([
                'message' => '403 Forbidden: The authenticated user does not have permission to access this resource.',
            ], 403);
        }

        $access = ManagementRole::isStaffRole($request->user()->role) ? 'read_only' : 'full';

        return $this->paginatedBookingListResponse($request, $access);
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
            ->with(['user.businessProfile', 'space', 'invoice'])
            ->paginate($filters['per_page']);

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
            'status' => 'nullable|string|in:Pending_Staff,Pending_Boss,Needs_Revision,Approved,Rejected,Cancelled',
            'payment_status' => 'nullable|string|in:Paid,Unpaid',
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

        if ($filters['event_id']) {
            $event = CarbootEvent::query()->find($filters['event_id']);
            if ($event) {
                $query->whereDate('booking_date', '>=', $event->starts_at->toDateString())
                    ->whereDate('booking_date', '<=', $event->ends_at->toDateString());
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
                SUM(CASE WHEN approval_status = 'Pending_Staff' THEN 1 ELSE 0 END) as pending_staff,
                SUM(CASE WHEN approval_status = 'Pending_Boss' THEN 1 ELSE 0 END) as pending_boss,
                SUM(CASE WHEN approval_status = 'Needs_Revision' THEN 1 ELSE 0 END) as needs_revision,
                SUM(CASE WHEN approval_status = 'Approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN approval_status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN approval_status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
            ")
            ->first();

        return [
            'pending_staff' => (int) ($counts->pending_staff ?? 0),
            'pending_boss' => (int) ($counts->pending_boss ?? 0),
            'needs_revision' => (int) ($counts->needs_revision ?? 0),
            'approved' => (int) ($counts->approved ?? 0),
            'rejected' => (int) ($counts->rejected ?? 0),
            'cancelled' => (int) ($counts->cancelled ?? 0),
        ];
    }

    private function queueStatusForUser($user): string
    {
        return ManagementRole::workflowRoleKey($user->role) === ManagementRole::MANAGER
            ? 'Pending_Boss'
            : 'Pending_Staff';
    }

    private function fetchQueueBookings($user)
    {
        return Booking::query()
            ->with(['user.businessProfile', 'space', 'invoice'])
            ->where('booking_date', '>', '1970-01-01')
            ->where('approval_status', $this->queueStatusForUser($user))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    public function mine(Request $request)
    {
        return $request->user()
            ->bookings()
            ->withValidBookingDate()
            ->with(['space', 'invoice'])
            ->latest()
            ->get();
    }

    public function vendorShow(Request $request, Booking $booking)
    {
        if ($denied = $this->authorizeVendorBooking($request, $booking)) {
            return $denied;
        }

        if (!$this->hasValidBookingDate($booking)) {
            return response()->json(['message' => '404 Not Found: Booking record is unavailable.'], 404);
        }

        return response()->json(
            $booking->load(['space', 'invoice', 'auditLogs.actor'])
        );
    }

    public function vendorUpdate(Request $request, Booking $booking)
    {
        if ($denied = $this->authorizeVendorBooking($request, $booking)) {
            return $denied;
        }

        if (!in_array($booking->approval_status, ['Pending_Staff', 'Needs_Revision'], true)) {
            return response()->json([
                'message' => '422 Unprocessable Entity: Only pending bookings can be edited by vendors.',
                'current_status' => $booking->approval_status,
            ], 422);
        }

        $validated = $request->validate([
            'booking_date' => 'sometimes|required|date|after_or_equal:today',
            'product_category' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'Pre-loved / Thrift',
                    'Food & Beverages',
                    'Clothing & Apparel',
                    'Handicrafts & Art',
                    'Electronics & Gadgets',
                    'Others',
                ]),
            ],
            'product_details' => 'sometimes|required|string|max:5000',
        ]);

        $booking->update($validated);

        return response()->json([
            'message' => '200 OK: Booking updated successfully.',
            'booking' => $booking->fresh(['space', 'invoice']),
        ]);
    }

    public function vendorCancel(Request $request, Booking $booking)
    {
        if ($denied = $this->authorizeVendorBooking($request, $booking)) {
            return $denied;
        }

        $cancellable = ['Pending_Staff', 'Pending_Boss', 'Needs_Revision'];
        if (!in_array($booking->approval_status, $cancellable, true)) {
            return response()->json([
                'message' => '422 Unprocessable Entity: Only pending bookings can be withdrawn by vendors.',
                'current_status' => $booking->approval_status,
            ], 422);
        }

        $previous = $booking->approval_status;
        $booking->update([
            'approval_status' => 'Cancelled',
            'revision_comment' => null,
        ]);

        BookingAuditLogger::log(
            $booking,
            $request->user(),
            $previous,
            'Cancelled',
            'Withdrawn by vendor.',
            $request,
            'vendor_cancel',
        );

        return response()->json([
            'message' => '200 OK: Booking withdrawn successfully.',
            'booking' => $booking->fresh(['space', 'invoice']),
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
            'message' => '200 OK: Change request submitted. CMart staff will review your request.',
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
            'message' => '200 OK: Cancellation request submitted. CMart staff will review your request.',
            'booking' => $booking->fresh(['space', 'invoice']),
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
        return $booking->load(['user', 'space', 'invoice']);
    }

    public function destroy(Booking $booking)
    {
        $user = request()->user();

        if (!$user || !ManagementRole::canAccessManagerRoutes($user->role)) {
            return response()->json([
                'message' => '403 Forbidden: Manager access required.',
            ], 403);
        }

        $booking->delete();

        return response()->json([
            'message' => '200 OK: Booking deleted successfully.',
        ]);
    }
}
