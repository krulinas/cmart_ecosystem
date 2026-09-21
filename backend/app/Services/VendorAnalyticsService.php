<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\User;
use App\Models\VendorBusinessProfile;
use App\Models\VendorItem;
use App\Models\VendorItemSale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class VendorAnalyticsService
{
    public function buildForUser(User $user): array
    {
        $today = now()->toDateString();

        $bookings = $user->bookings()
            ->withValidBookingDate()
            ->with('invoice')
            ->get();

        $items = $user->vendorItems()->get();

        $profile = VendorBusinessProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'business_name' => $user->name,
                'business_phone' => $user->phone_number,
            ],
        );

        $currentBooking = $this->resolveCurrentBooking($bookings, $today);
        $profileCompletion = $this->profileCompletion($profile);

        $paidInvoices = Invoice::query()
            ->where('payment_status', 'Paid')
            ->whereHas('booking', fn ($q) => $q->where('user_id', $user->id))
            ->with('booking')
            ->get();

        $invoicesWithBooking = Invoice::query()
            ->whereHas('booking', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        $totalPaidAmount = (float) $paidInvoices->sum('amount');
        $activeItems = $items->where('status', 'active')->count();
        $inactiveItems = $items->where('status', 'inactive')->count();

        $bookingStatusDistribution = $bookings
            ->groupBy('approval_status')
            ->map(fn (Collection $group) => $group->count())
            ->toArray();

        return [
            'summary' => [
                'total_bookings' => $bookings->count(),
                'upcoming_bookings' => $bookings->filter(
                    fn (Booking $b) => $b->booking_date->toDateString() >= $today
                        && !in_array($b->approval_status, ['Rejected', 'Cancelled', 'Withdrawn'], true),
                )->count(),
                'completed_bookings' => $bookings->filter(
                    fn (Booking $b) => $b->booking_date->toDateString() < $today
                        && $b->approval_status === 'Approved',
                )->count(),
                'cancelled_bookings' => $bookings->whereIn('approval_status', ['Cancelled', 'Withdrawn'])->count(),
                'rejected_bookings' => $bookings->where('approval_status', 'Rejected')->count(),
                'total_receipts' => $invoicesWithBooking,
                'total_paid_amount' => round($totalPaidAmount, 2),
                'active_reuse_listings' => $activeItems,
                'inactive_reuse_listings' => $inactiveItems,
                'total_reuse_listings' => $items->count(),
                'profile_completion_percent' => $profileCompletion['percent'],
                'profile_missing_fields' => $profileCompletion['missing_fields'],
            ],
            'booth' => [
                'items_reused' => $activeItems,
                'estimated_sales' => round($totalPaidAmount, 2),
                'booth_status' => $this->resolveBoothStatus($currentBooking),
                'current_event' => $currentBooking
                    ? VendorBookingPresenter::eventLabel($currentBooking)
                    : null,
                'booth_number' => $currentBooking
                    ? VendorBookingPresenter::boothNumber($currentBooking)
                    : null,
            ],
            'trends' => [
                'monthly_bookings' => $this->monthlyBookingTrend($bookings),
                'monthly_payments' => $this->monthlyPaymentTrend($paidInvoices),
            ],
            'distributions' => [
                'booking_status' => $bookingStatusDistribution,
                'reuse_listing_status' => [
                    'active' => $activeItems,
                    'inactive' => $inactiveItems,
                ],
            ],
            'item_sales' => $this->itemSalesInsights($user),
            'recent_activity' => $this->recentActivity($bookings, $paidInvoices, $items, $profile),
            'latest' => [
                'booking' => $this->formatLatestBooking($bookings),
                'receipt' => $this->formatLatestReceipt($paidInvoices),
                'reuse_item' => $this->formatLatestItem($items),
            ],
        ];
    }

    /**
     * Vendor-reported CMart item sales only (not booth fees / profit).
     *
     * @return array{
     *   available: bool,
     *   clarification_key: string,
     *   items_sold: int|null,
     *   recorded_sales_total: float|null,
     *   currency: string,
     *   reserved_sales_count: int|null,
     *   reserved_sales_total: float|null,
     *   walk_in_sales_count: int|null,
     *   walk_in_sales_total: float|null,
     *   sales_by_event: list<array<string, mixed>>|null
     * }
     */
    private function itemSalesInsights(User $user): array
    {
        $base = [
            'available' => false,
            'clarification_key' => 'vendor_item_sales_cmart_only',
            'items_sold' => null,
            'recorded_sales_total' => null,
            'currency' => 'MYR',
            'reserved_sales_count' => null,
            'reserved_sales_total' => null,
            'walk_in_sales_count' => null,
            'walk_in_sales_total' => null,
            'sales_by_event' => null,
        ];

        if (! Schema::hasTable('vendor_item_sales')) {
            return $base;
        }

        $sales = VendorItemSale::query()
            ->with('carbootEvent:id,title,starts_at')
            ->where('vendor_user_id', $user->id)
            ->orderByDesc('sold_at')
            ->get();

        $reserved = $sales->where('sale_source', VendorItemSale::SOURCE_RESERVED);
        $walkIn = $sales->where('sale_source', VendorItemSale::SOURCE_WALK_IN);

        $byEvent = $sales
            ->groupBy('carboot_event_id')
            ->map(function (Collection $group) {
                /** @var VendorItemSale $first */
                $first = $group->first();
                $event = $first->carbootEvent;

                return [
                    'carboot_event_id' => (int) $first->carboot_event_id,
                    'event_title' => $event?->title,
                    'event_starts_at' => $event?->starts_at?->toIso8601String(),
                    'items_sold' => $group->count(),
                    'recorded_sales_total' => round((float) $group->sum('final_sale_price'), 2),
                    'reserved_count' => $group->where('sale_source', VendorItemSale::SOURCE_RESERVED)->count(),
                    'walk_in_count' => $group->where('sale_source', VendorItemSale::SOURCE_WALK_IN)->count(),
                ];
            })
            ->sortByDesc('event_starts_at')
            ->values()
            ->all();

        return [
            'available' => true,
            'clarification_key' => 'vendor_item_sales_cmart_only',
            'items_sold' => $sales->count(),
            'recorded_sales_total' => round((float) $sales->sum('final_sale_price'), 2),
            'currency' => 'MYR',
            'reserved_sales_count' => $reserved->count(),
            'reserved_sales_total' => round((float) $reserved->sum('final_sale_price'), 2),
            'walk_in_sales_count' => $walkIn->count(),
            'walk_in_sales_total' => round((float) $walkIn->sum('final_sale_price'), 2),
            'sales_by_event' => $byEvent,
        ];
    }

    public function buildReportForUser(User $user): array
    {
        $analytics = $this->buildForUser($user);

        $profile = VendorBusinessProfile::query()
            ->where('user_id', $user->id)
            ->first();

        return [
            'generated_at' => now()->toIso8601String(),
            'vendor' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'business_profile' => $profile ? [
                'business_name' => $profile->business_name,
                'business_phone' => $profile->business_phone,
                'business_category' => $profile->business_category,
                'description' => $profile->description,
                'logo_url' => $profile->logo_url,
                'completion_percent' => $analytics['summary']['profile_completion_percent'],
            ] : null,
            'booking_summary' => [
                'total_bookings' => $analytics['summary']['total_bookings'],
                'upcoming_bookings' => $analytics['summary']['upcoming_bookings'],
                'completed_bookings' => $analytics['summary']['completed_bookings'],
                'cancelled_bookings' => $analytics['summary']['cancelled_bookings'],
                'rejected_bookings' => $analytics['summary']['rejected_bookings'],
                'status_distribution' => $analytics['distributions']['booking_status'],
                'monthly_trend' => $analytics['trends']['monthly_bookings'],
            ],
            'payment_summary' => [
                'total_receipts' => $analytics['summary']['total_receipts'],
                'total_paid_amount' => $analytics['summary']['total_paid_amount'],
                'monthly_trend' => $analytics['trends']['monthly_payments'],
            ],
            'reuse_listing_summary' => [
                'total_listings' => $analytics['summary']['total_reuse_listings'],
                'active_listings' => $analytics['summary']['active_reuse_listings'],
                'inactive_listings' => $analytics['summary']['inactive_reuse_listings'],
                'status_distribution' => $analytics['distributions']['reuse_listing_status'],
            ],
            'item_sales_summary' => $analytics['item_sales'],
            'recent_activity' => $analytics['recent_activity'],
        ];
    }

    private function resolveCurrentBooking(Collection $bookings, string $today): ?Booking
    {
        return $bookings
            ->filter(
                fn (Booking $b) => $b->booking_date->toDateString() >= $today
                    && !in_array($b->approval_status, ['Rejected', 'Cancelled', 'Withdrawn'], true),
            )
            ->sortBy([
                fn (Booking $b) => $b->booking_date->timestamp,
                fn (Booking $b) => $b->id,
            ])
            ->first();
    }

    private function resolveBoothStatus(?Booking $booking): string
    {
        if (!$booking) {
            return 'No Active Booking';
        }

        if ($booking->approval_status === 'Approved') {
            return 'Approved';
        }

        if (in_array($booking->approval_status, ['Pending_Organizer', 'Needs_Revision'], true)) {
            return 'Pending';
        }

        return 'No Active Booking';
    }

    private function profileCompletion(VendorBusinessProfile $profile): array
    {
        $fields = [
            'business_name' => filled($profile->business_name),
            'business_phone' => filled($profile->business_phone),
            'business_category' => filled($profile->business_category),
            'description' => filled($profile->description),
            'logo_path' => filled($profile->logo_path),
        ];

        $completed = count(array_filter($fields));
        $total = count($fields);
        $missing = array_keys(array_filter($fields, fn ($filled) => !$filled));

        return [
            'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'missing_fields' => $missing,
        ];
    }

    private function monthlyBookingTrend(Collection $bookings): array
    {
        $months = $this->lastSixMonthBuckets();

        foreach ($bookings as $booking) {
            $key = $booking->booking_date->format('Y-m');
            if (isset($months[$key])) {
                $months[$key]['count']++;
            }
        }

        return array_values($months);
    }

    private function monthlyPaymentTrend(Collection $paidInvoices): array
    {
        $months = $this->lastSixMonthBuckets();

        foreach ($paidInvoices as $invoice) {
            $key = $invoice->updated_at->format('Y-m');
            if (isset($months[$key])) {
                $months[$key]['count']++;
                $months[$key]['amount'] = round($months[$key]['amount'] + (float) $invoice->amount, 2);
            }
        }

        return array_values($months);
    }

    private function lastSixMonthBuckets(): array
    {
        $months = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $key = $date->format('Y-m');
            $months[$key] = [
                'month' => $key,
                'label' => $date->format('M Y'),
                'count' => 0,
                'amount' => 0.0,
            ];
        }

        return $months;
    }

    private function recentActivity(
        Collection $bookings,
        Collection $paidInvoices,
        Collection $items,
        VendorBusinessProfile $profile,
    ): array {
        $activities = collect();

        foreach ($bookings as $booking) {
            $activities->push([
                'type' => 'booking',
                'title' => sprintf('Booking #%d · %s', $booking->id, VendorBookingPresenter::eventLabel($booking)),
                'status' => $booking->approval_status,
                'amount' => $booking->invoice ? round((float) $booking->invoice->amount, 2) : null,
                'occurred_at' => $booking->created_at?->toIso8601String(),
            ]);
        }

        foreach ($paidInvoices as $invoice) {
            $booking = $invoice->booking;
            if (!$booking) {
                continue;
            }

            $activities->push([
                'type' => 'payment',
                'title' => sprintf('Payment received · Booking #%d', $booking->id),
                'status' => 'Paid',
                'amount' => round((float) $invoice->amount, 2),
                'occurred_at' => $invoice->updated_at?->toIso8601String(),
            ]);
        }

        foreach ($items as $item) {
            $activities->push([
                'type' => 'reuse_item',
                'title' => $item->name,
                'status' => $item->status,
                'amount' => $item->pricing_type === 'fixed' ? round((float) $item->price, 2) : null,
                'occurred_at' => $item->updated_at?->toIso8601String(),
            ]);
        }

        if ($profile->updated_at && $profile->created_at && $profile->updated_at->gt($profile->created_at)) {
            $activities->push([
                'type' => 'profile',
                'title' => 'Business profile updated',
                'status' => 'Updated',
                'amount' => null,
                'occurred_at' => $profile->updated_at->toIso8601String(),
            ]);
        }

        return $activities
            ->filter(fn (array $row) => !empty($row['occurred_at']))
            ->sortByDesc('occurred_at')
            ->take(10)
            ->values()
            ->all();
    }

    private function formatLatestBooking(Collection $bookings): ?array
    {
        $latest = $bookings->sortByDesc('created_at')->first();
        if (!$latest) {
            return null;
        }

        return [
            'id' => $latest->id,
            'event' => VendorBookingPresenter::eventLabel($latest),
            'status' => $latest->approval_status,
            'date' => $latest->booking_date->format('Y-m-d'),
            'occurred_at' => $latest->created_at?->toIso8601String(),
        ];
    }

    private function formatLatestReceipt(Collection $paidInvoices): ?array
    {
        $latest = $paidInvoices->sortByDesc('updated_at')->first();
        if (!$latest || !$latest->booking) {
            return null;
        }

        return [
            'booking_id' => $latest->booking->id,
            'amount' => round((float) $latest->amount, 2),
            'status' => 'Paid',
            'occurred_at' => $latest->updated_at?->toIso8601String(),
        ];
    }

    private function formatLatestItem(Collection $items): ?array
    {
        $latest = $items->sortByDesc('updated_at')->first();
        if (!$latest) {
            return null;
        }

        return [
            'id' => $latest->id,
            'name' => $latest->name,
            'status' => $latest->status,
            'occurred_at' => $latest->updated_at?->toIso8601String(),
        ];
    }
}
