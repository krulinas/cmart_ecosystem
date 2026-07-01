<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\Feedback;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;

class StaffOperationsController extends Controller
{
    /**
     * Operational counts for Tier 1 staff — no revenue, audit, or strategic analytics.
     */
    public function operationsSummary(): JsonResponse
    {
        $pendingStaffReview = Booking::query()
            ->where('approval_status', 'Pending_Staff')
            ->count();

        $needsRevision = Booking::query()
            ->where('approval_status', 'Needs_Revision')
            ->count();

        $paymentProofsToCheck = Invoice::query()
            ->where('payment_status', 'Pending Verification')
            ->whereHas('booking', fn ($query) => $query->where('approval_status', 'Approved'))
            ->count();

        $upcomingEvents = CarbootEvent::query()
            ->where('status', '!=', 'Closed')
            ->where('ends_at', '>=', now())
            ->count();

        $feedbackToReview = Feedback::query()
            ->where('is_hidden', false)
            ->whereNull('reviewed_at')
            ->count();

        return response()->json([
            'pending_staff_review' => $pendingStaffReview,
            'needs_revision' => $needsRevision,
            'payment_proofs_to_check' => $paymentProofsToCheck,
            'upcoming_events' => $upcomingEvents,
            'feedback_to_review' => $feedbackToReview,
        ]);
    }
}
