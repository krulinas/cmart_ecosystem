<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAuditLog;

class BookingAuditPresenter
{
    private const ACTION_LABELS = [
        'vendor_submitted_booking' => 'Booking submitted',
        'organizer_requested_revision' => 'Organizer requested revision',
        'vendor_resubmitted' => 'Vendor resubmitted booking',
        'vendor_resubmitted_booking' => 'Vendor resubmitted booking',
        'organizer_approved_booking' => 'Booking approved',
        'organizer_verified_payment' => 'Payment verified',
        'organizer_applied_attendance_exception' => 'Organizer applied attendance exception',
        'vendor_withdraw' => 'Vendor withdrew booking',
        'organizer_rejected_booking' => 'Booking rejected',
        'vendor_cancel' => 'Booking cancelled',
        'vendor_request_change' => 'Vendor requested a change',
        'vendor_request_cancellation' => 'Vendor requested cancellation',
        'status_change' => 'Booking status changed',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function timeline(Booking $booking): array
    {
        $booking->loadMissing(['auditLogs.actor', 'attendanceExceptions']);

        return $booking->auditLogs
            ->sortBy(fn (BookingAuditLog $log) => [$log->created_at?->getTimestamp() ?? 0, $log->id])
            ->values()
            ->map(fn (BookingAuditLog $log) => [
                'id' => $log->id,
                'action' => array_key_exists($log->action, self::ACTION_LABELS) ? $log->action : 'other',
                'label' => self::ACTION_LABELS[$log->action] ?? 'Booking activity recorded',
                'previous_status' => $log->from_status,
                'new_status' => $log->to_status,
                'actor' => $log->actor ? [
                    'id' => $log->actor->id,
                    'name' => $log->actor->name,
                    'role' => $log->actor->role,
                ] : null,
                'occurred_at' => $log->created_at?->toIso8601String(),
                'summary' => self::summary($booking, $log),
            ])
            ->all();
    }

    private static function summary(Booking $booking, BookingAuditLog $log): string
    {
        if ($log->action === 'vendor_withdraw') {
            return match (VendorBookingPresenter::withdrawalPaymentState($booking)) {
                VendorBookingPresenter::PAYMENT_STATE_PAID =>
                    'Vendor withdrew after payment · No refund policy applied · Sites released',
                VendorBookingPresenter::PAYMENT_STATE_PAYMENT_SUBMITTED =>
                    'Vendor withdrew after submitting payment proof · No refund policy applied · Sites released',
                default => 'Vendor withdrew before payment · Sites released',
            };
        }

        if ($log->action === 'organizer_applied_attendance_exception') {
            preg_match('/Attendance exception #(\d+)/', (string) $log->revision_comment, $matches);
            $exception = isset($matches[1])
                ? $booking->attendanceExceptions->firstWhere('id', (int) $matches[1])
                : null;

            if (! $exception) {
                return 'Organizer reduced the retained EventDays';
            }

            $summary = "Attendance reduced to {$exception->retained_day_count} of {$exception->previous_retained_day_count} days";
            if ($exception->payment_state === VendorBookingPresenter::PAYMENT_STATE_PAYMENT_SUBMITTED) {
                $summary .= ' · Payment proof retained';
            }
            if (in_array($exception->payment_state, [
                VendorBookingPresenter::PAYMENT_STATE_PAID,
                VendorBookingPresenter::PAYMENT_STATE_PAYMENT_SUBMITTED,
            ], true)) {
                $summary .= ' · No refund';
            }

            return $summary . " · {$exception->released_day_count} day released";
        }

        return match ($log->action) {
            'vendor_submitted_booking' => 'Vendor submitted the booking for Organizer review',
            'organizer_requested_revision' => 'Organizer returned the booking for revision',
            'vendor_resubmitted', 'vendor_resubmitted_booking' => 'Vendor resubmitted the booking for review',
            'organizer_approved_booking' => 'Organizer approved the booking',
            'organizer_verified_payment' => 'Organizer verified the submitted payment',
            'organizer_rejected_booking' => 'Organizer rejected the booking',
            'vendor_cancel' => 'Vendor cancelled the booking',
            'vendor_request_change' => 'Vendor requested an operational change',
            'vendor_request_cancellation' => 'Vendor requested cancellation',
            'status_change' => 'Booking lifecycle status changed',
            default => 'Booking activity was recorded',
        };
    }
}
