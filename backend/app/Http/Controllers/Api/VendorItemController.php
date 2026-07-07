<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorItem;
use App\Services\VendorItemPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VendorItemController extends Controller
{
    /**
     * Private vendor item preparation — not public marketplace listing.
     * Any authenticated community user may manage their own items without
     * booking approval, payment, or event-day check-in.
     *
     * Public exposure remains disabled in MarketplaceController until a future
     * event-day publishing flow exists.
     */
    private const MAX_IMAGES = 5;

    public function index(Request $request)
    {
        $query = $request->user()
            ->vendorItems()
            ->latest();

        if (Schema::hasTable('reuse_item_images')) {
            $query->with('images');
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $needle = mb_strtolower($search);
            $query->where(function ($builder) use ($needle) {
                $builder
                    ->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(category) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(status) LIKE ?', ["%{$needle}%"]);
            });
        }

        if ($status = $request->query('status')) {
            if (in_array($status, ['active', 'inactive'], true)) {
                $query->where('status', $status);
            }
        }

        return response()->json([
            'items' => $query->get()->map(fn (VendorItem $item) => VendorItemPresenter::fromModel($item))->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateItem($request);

        $item = $request->user()->vendorItems()->create($validated);
        $this->attachUploadedImages($request, $item);

        return response()->json([
            'message' => '201 Created: Reuse item created successfully.',
            'item' => VendorItemPresenter::fromModel($item->fresh('images')),
        ], 201);
    }

    public function show(Request $request, VendorItem $vendor_item)
    {
        if ($denied = $this->authorizeOwner($request, $vendor_item)) {
            return $denied;
        }

        $vendor_item->load('images');

        return response()->json([
            'item' => VendorItemPresenter::fromModel($vendor_item),
        ]);
    }

    public function update(Request $request, VendorItem $vendor_item)
    {
        if ($denied = $this->authorizeOwner($request, $vendor_item)) {
            return $denied;
        }

        $validated = $this->validateItem($request, true);
        $vendor_item->update($validated);

        if ($request->boolean('remove_image')) {
            $this->removeAllImages($vendor_item);
        }

        if ($request->filled('remove_image_ids')) {
            $this->removeImagesById($vendor_item, (array) $request->input('remove_image_ids'));
        }

        $this->attachUploadedImages($request, $vendor_item);

        return response()->json([
            'message' => '200 OK: Reuse item updated successfully.',
            'item' => VendorItemPresenter::fromModel($vendor_item->fresh('images')),
        ]);
    }

    public function destroy(Request $request, VendorItem $vendor_item)
    {
        if ($denied = $this->authorizeOwner($request, $vendor_item)) {
            return $denied;
        }

        $vendor_item->delete();

        return response()->json([
            'message' => '200 OK: Reuse item deleted successfully.',
        ]);
    }

    private function authorizeOwner(Request $request, VendorItem $item): ?\Illuminate\Http\JsonResponse
    {
        if ($item->user_id !== $request->user()->id) {
            return response()->json([
                'message' => '403 Forbidden: You do not have permission to access this item.',
            ], 403);
        }

        return null;
    }

    private function validateItem(Request $request, bool $partial = false): array
    {
        $prefix = $partial ? 'sometimes|' : '';

        $validated = $request->validate([
            'name' => $prefix . 'required|string|max:255',
            'category' => [
                $partial ? 'sometimes' : 'required',
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
            'condition' => $prefix . 'required|in:New,Like New,Good,Fair,For Parts',
            'pricing_type' => $prefix . 'required|in:fixed,free,donation',
            'price' => 'nullable|numeric|min:0|max:999999.99',
            'description' => 'nullable|string|max:5000',
            'status' => $prefix . 'required|in:active,inactive',
            'image' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
            'images' => 'nullable|array|max:' . self::MAX_IMAGES,
            'images.*' => 'file|mimes:jpeg,jpg,png,webp|max:5120',
            'remove_image' => 'nullable|boolean',
            'remove_image_ids' => 'nullable|array',
            'remove_image_ids.*' => 'integer',
        ]);

        unset($validated['image'], $validated['images'], $validated['remove_image'], $validated['remove_image_ids']);

        if (($validated['pricing_type'] ?? $request->input('pricing_type')) === 'fixed') {
            $request->validate(['price' => ($partial ? 'sometimes|' : '') . 'required|numeric|min:0|max:999999.99']);
            $validated['price'] = $request->input('price');
        } else {
            $validated['price'] = null;
        }

        return $validated;
    }

    private function collectUploadFiles(Request $request): array
    {
        $files = [];

        if ($request->hasFile('image')) {
            $files[] = $request->file('image');
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                if ($file) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    private function attachUploadedImages(Request $request, VendorItem $item): void
    {
        $files = $this->collectUploadFiles($request);
        if ($files === []) {
            $this->syncPrimaryImagePath($item);

            return;
        }

        $existingCount = $item->images()->count();
        $availableSlots = self::MAX_IMAGES - $existingCount;

        if ($availableSlots <= 0) {
            return;
        }

        $hasPrimary = $item->images()->where('is_primary', true)->exists();

        foreach (array_slice($files, 0, $availableSlots) as $offset => $file) {
            $path = $file->store('reuse-items', 'public');

            $item->images()->create([
                'image_path' => $path,
                'sort_order' => $existingCount + $offset,
                'is_primary' => !$hasPrimary && $offset === 0,
            ]);

            if ($offset === 0 && !$hasPrimary) {
                $hasPrimary = true;
            }
        }

        $this->syncPrimaryImagePath($item->fresh('images'));
    }

    private function removeImagesById(VendorItem $item, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        $images = $item->images()->whereIn('id', $ids)->get();
        foreach ($images as $image) {
            $image->delete();
        }

        $this->reassignPrimaryIfNeeded($item);
        $this->syncPrimaryImagePath($item->fresh('images'));
    }

    private function removeAllImages(VendorItem $item): void
    {
        $item->load('images');

        foreach ($item->images as $image) {
            $image->delete();
        }

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        $item->updateQuietly(['image_path' => null]);
    }

    private function reassignPrimaryIfNeeded(VendorItem $item): void
    {
        if ($item->images()->where('is_primary', true)->exists()) {
            return;
        }

        $first = $item->images()->orderBy('sort_order')->orderBy('id')->first();
        if ($first) {
            $first->update(['is_primary' => true]);
        }
    }

    private function syncPrimaryImagePath(VendorItem $item): void
    {
        $item->loadMissing('images');

        $primary = $item->images->firstWhere('is_primary', true)
            ?? $item->images->sortBy('sort_order')->first();

        $item->updateQuietly(['image_path' => $primary?->image_path]);
    }
}
