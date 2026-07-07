<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorItem;
use App\Services\MarketplaceItemPresenter;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    /**
     * Public vendor-item listing is intentionally disabled until event-day publishing exists.
     *
     * Future public visibility should require ALL of:
     * - vendor booking Approved
     * - invoice payment Paid
     * - event day has arrived
     * - vendor checked in / attendance confirmed
     * - item marked available for that event
     *
     * Vendors may still manage items privately via /api/vendor/items.
     */
    private function publicListingsEnabled(): bool
    {
        return false;
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:120',
            'category' => 'nullable|string|max:100',
            'condition' => 'nullable|in:New,Like New,Good,Fair,For Parts',
            'pricing_type' => 'nullable|in:fixed,free,donation',
            'sort' => 'nullable|in:newest,oldest,price_asc,price_desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:48',
        ]);

        $perPage = min((int) ($validated['per_page'] ?? 12), 48);

        if (!$this->publicListingsEnabled()) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                ],
                'public_listing_enabled' => false,
                'message' => 'Specific vendor item previews are not publicly listed yet.',
            ]);
        }

        $query = VendorItem::query()
            ->active()
            ->with(['user.businessProfile', 'images']);

        if ($search = trim((string) ($validated['search'] ?? ''))) {
            $needle = mb_strtolower($search);
            $query->where(function ($builder) use ($needle) {
                $builder
                    ->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(category) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$needle}%"])
                    ->orWhereHas('user.businessProfile', function ($profileQuery) use ($needle) {
                        $profileQuery->whereRaw('LOWER(business_name) LIKE ?', ["%{$needle}%"]);
                    })
                    ->orWhereHas('user', function ($userQuery) use ($needle) {
                        $userQuery->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"]);
                    });
            });
        }

        if (!empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (!empty($validated['condition'])) {
            $query->where('condition', $validated['condition']);
        }

        if (!empty($validated['pricing_type'])) {
            $query->where('pricing_type', $validated['pricing_type']);
        }

        $this->applySort($query, $validated['sort'] ?? 'newest');

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (VendorItem $item) => MarketplaceItemPresenter::fromItem($item))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'public_listing_enabled' => true,
        ]);
    }

    public function show(VendorItem $vendor_item)
    {
        if (!$this->publicListingsEnabled()) {
            return response()->json([
                'message' => '404 Not Found: Public item previews are not available yet.',
            ], 404);
        }

        if ($vendor_item->status !== 'active') {
            return response()->json([
                'message' => '404 Not Found: Marketplace listing is unavailable.',
            ], 404);
        }

        $vendor_item->load(['user.businessProfile', 'images']);

        return response()->json([
            'item' => MarketplaceItemPresenter::fromItem($vendor_item, detailed: true),
        ]);
    }

    private function applySort($query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'price_asc' => $query->orderByRaw("CASE WHEN pricing_type = 'fixed' THEN price ELSE 999999999 END ASC")
                ->orderByDesc('created_at'),
            'price_desc' => $query->orderByRaw("CASE WHEN pricing_type = 'fixed' THEN price ELSE -1 END DESC")
                ->orderByDesc('created_at'),
            default => $query->latest(),
        };
    }
}
