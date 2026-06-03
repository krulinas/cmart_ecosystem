<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CarbootEvent;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class BossAnalyticsController extends Controller
{
    public function revenue()
    {
        $invoices = Invoice::query()
            ->whereHas('booking', fn ($q) => $q->where('approval_status', 'Approved'))
            ->get();

        $totalRevenue = $invoices->sum('amount');
        $paidRevenue = $invoices->where('payment_status', 'Paid')->sum('amount');
        $unpaidRevenue = $invoices->where('payment_status', 'Unpaid')->sum('amount');

        $categoryCounts = Booking::query()
            ->select('product_category', DB::raw('count(*) as count'))
            ->where('approval_status', 'Approved')
            ->groupBy('product_category')
            ->pluck('count', 'product_category');

        $fbApproved = (int) ($categoryCounts['Food & Beverages'] ?? 0);
        $totalApproved = (int) $categoryCounts->sum();

        $spaceBreakdown = Booking::query()
            ->join('spaces', 'bookings.space_id', '=', 'spaces.id')
            ->where('bookings.approval_status', 'Approved')
            ->select('spaces.space_size', DB::raw('count(*) as count'), DB::raw('sum(spaces.price) as revenue'))
            ->groupBy('spaces.id', 'spaces.space_size')
            ->get();

        $upcomingEvent = CarbootEvent::query()
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->first();

        $maxSlots = $upcomingEvent?->max_slots ?? 0;
        $utilizationPercent = $maxSlots > 0
            ? round(($totalApproved / $maxSlots) * 100, 1)
            : null;

        $paymentBreakdown = Invoice::query()
            ->whereHas('booking', fn ($q) => $q->where('approval_status', 'Approved'))
            ->select('payment_status', DB::raw('count(*) as count'), DB::raw('sum(amount) as total'))
            ->groupBy('payment_status')
            ->get();

        return response()->json([
            'summary' => [
                'total_revenue' => round((float) $totalRevenue, 2),
                'paid_revenue' => round((float) $paidRevenue, 2),
                'unpaid_revenue' => round((float) $unpaidRevenue, 2),
                'approved_bookings' => $totalApproved,
                'fb_approved_count' => $fbApproved,
                'fb_share_percent' => $totalApproved > 0
                    ? round(($fbApproved / $totalApproved) * 100, 1)
                    : 0,
                'max_slots_reference' => $maxSlots,
                'utilization_percent' => $utilizationPercent,
                'reference_event' => $upcomingEvent?->title,
            ],
            'by_category' => $categoryCounts,
            'by_space' => $spaceBreakdown,
            'by_payment_status' => $paymentBreakdown,
        ]);
    }
}
