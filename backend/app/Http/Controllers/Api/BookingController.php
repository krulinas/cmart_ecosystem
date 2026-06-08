<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Space;
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
        'cmart_staff' => [
            'Pending_Staff' => ['Pending_Boss', 'Needs_Revision', 'Rejected'],
        ],
        'cmart_admin' => [
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
     *   cmart_staff:  Pending_Staff -> Pending_Boss | Needs_Revision
     *   cmart_admin:  Pending_Boss  -> Approved     | Needs_Revision
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

        $allowedTargets = self::STATE_TRANSITIONS[$user->role][$current] ?? [];

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

        $isCmartWorker = in_array($user->role, ['cmart_staff', 'cmart_admin'], true);

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
        $user = $request->user();

        if ($booking->user_id !== $user->id) {
            return response()->json([
                'message' => '403 Forbidden: The authenticated user does not have permission to modify this booking.',
            ], 403);
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

    public function index()
    {
        return Booking::with(['user', 'space', 'invoice'])->latest()->get();
    }

    public function mine(Request $request)
    {
        return $request->user()
            ->bookings()
            ->with(['space', 'invoice'])
            ->latest()
            ->get();
    }

    public function show(Booking $booking)
    {
        return $booking->load(['user', 'space', 'invoice']);
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return response()->json([
            'message' => '200 OK: Booking deleted successfully.',
        ]);
    }
}
