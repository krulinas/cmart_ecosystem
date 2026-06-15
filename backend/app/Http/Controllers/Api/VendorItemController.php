<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VendorItemController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()
            ->vendorItems()
            ->latest();

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
            'items' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateItem($request);

        $item = $request->user()->vendorItems()->create([
            ...$validated,
            'image_path' => $this->storeImage($request),
        ]);

        return response()->json([
            'message' => '201 Created: Reuse item created successfully.',
            'item' => $item,
        ], 201);
    }

    public function show(Request $request, VendorItem $vendor_item)
    {
        if ($denied = $this->authorizeOwner($request, $vendor_item)) {
            return $denied;
        }

        return response()->json(['item' => $vendor_item]);
    }

    public function update(Request $request, VendorItem $vendor_item)
    {
        if ($denied = $this->authorizeOwner($request, $vendor_item)) {
            return $denied;
        }

        $validated = $this->validateItem($request, true);
        $imagePath = $this->resolveImagePath($request, $vendor_item);

        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            $validated['image_path'] = $imagePath;
        }

        $vendor_item->update($validated);

        return response()->json([
            'message' => '200 OK: Reuse item updated successfully.',
            'item' => $vendor_item->fresh(),
        ]);
    }

    public function destroy(Request $request, VendorItem $vendor_item)
    {
        if ($denied = $this->authorizeOwner($request, $vendor_item)) {
            return $denied;
        }

        $this->deleteImageFile($vendor_item->image_path);
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
            'remove_image' => 'nullable|boolean',
        ]);

        unset($validated['image'], $validated['remove_image']);

        if (($validated['pricing_type'] ?? $request->input('pricing_type')) === 'fixed') {
            $request->validate(['price' => ($partial ? 'sometimes|' : '') . 'required|numeric|min:0|max:999999.99']);
            $validated['price'] = $request->input('price');
        } else {
            $validated['price'] = null;
        }

        return $validated;
    }

    private function storeImage(Request $request): ?string
    {
        if (!$request->hasFile('image')) {
            return null;
        }

        return $request->file('image')->store('images/vendor-items', 'public');
    }

    private function resolveImagePath(Request $request, VendorItem $item): ?string
    {
        if ($request->boolean('remove_image')) {
            $this->deleteImageFile($item->image_path);

            return null;
        }

        if ($request->hasFile('image')) {
            $this->deleteImageFile($item->image_path);

            return $request->file('image')->store('images/vendor-items', 'public');
        }

        return $item->image_path;
    }

    private function deleteImageFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
