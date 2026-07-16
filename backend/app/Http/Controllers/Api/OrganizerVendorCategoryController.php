<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BookingDayAllocation;
use App\Models\EventLayoutRow;
use App\Models\VendorCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3.5 — Organizer canonical category lookup for row assignment.
 */
class OrganizerVendorCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = VendorCategory::query()->ordered()->get();

        $rowCounts = EventLayoutRow::query()
            ->select('vendor_category_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('vendor_category_id')
            ->groupBy('vendor_category_id')
            ->pluck('total', 'vendor_category_id');

        $activeRowCounts = EventLayoutRow::query()
            ->select('vendor_category_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('vendor_category_id')
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->groupBy('vendor_category_id')
            ->pluck('total', 'vendor_category_id');

        $bookingCounts = DB::table('bookings')
            ->select('vendor_category_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('vendor_category_id')
            ->groupBy('vendor_category_id')
            ->pluck('total', 'vendor_category_id');

        $activeAllocationCounts = DB::table('booking_day_allocations as a')
            ->join('bookings as b', 'b.id', '=', 'a.booking_id')
            ->select('b.vendor_category_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('b.vendor_category_id')
            ->where('a.active_lock', 1)
            ->whereIn('a.allocation_status', BookingDayAllocation::OCCUPYING_STATUSES)
            ->groupBy('b.vendor_category_id')
            ->pluck('total', 'vendor_category_id');

        $payload = $categories->map(function (VendorCategory $category) use (
            $rowCounts,
            $activeRowCounts,
            $bookingCounts,
            $activeAllocationCounts,
        ) {
            $selectable = $category->is_active && $category->archived_at === null;

            return [
                'id' => $category->id,
                'slug' => $category->slug,
                'label' => $category->label,
                'description' => $category->description,
                'display_order' => $category->display_order,
                'is_active' => $category->is_active,
                'is_public' => $category->is_public,
                'archived_at' => optional($category->archived_at)?->toIso8601String(),
                'usage' => [
                    'layout_rows' => (int) ($rowCounts[$category->id] ?? 0),
                    'active_layout_rows' => (int) ($activeRowCounts[$category->id] ?? 0),
                    'bookings' => (int) ($bookingCounts[$category->id] ?? 0),
                    'active_allocations' => (int) ($activeAllocationCounts[$category->id] ?? 0),
                ],
                'selectable_for_new_row' => $selectable,
            ];
        })->values();

        return response()->json([
            'categories' => $payload,
        ]);
    }
}
