<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserBookingPreference;
use Illuminate\Http\Request;

class UserBookingPreferenceController extends Controller
{
    public function show(Request $request)
    {
        $preference = UserBookingPreference::query()
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$preference || !$preference->remember_enabled) {
            return response()->json([
                'has_preference' => false,
                'preference' => null,
            ]);
        }

        return response()->json([
            'has_preference' => true,
            'preference' => $this->formatPreference($preference),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'product_category' => 'nullable|string|max:255',
            'specific_products' => 'nullable|string|max:1000',
            'tapak_count' => 'required|integer|min:1|max:10',
            'remember_enabled' => 'required|boolean',
        ]);

        $rememberEnabled = (bool) $validated['remember_enabled'];

        $preference = UserBookingPreference::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'name' => $validated['name'] ?? null,
                'product_category' => $validated['product_category'] ?? null,
                'specific_products' => $validated['specific_products'] ?? null,
                'tapak_count' => $validated['tapak_count'],
                'remember_enabled' => $rememberEnabled,
                'last_used_at' => $rememberEnabled ? now() : null,
            ],
        );

        return response()->json([
            'message' => 'Saved booking details updated successfully.',
            'preference' => $this->formatPreference($preference),
        ]);
    }

    public function destroy(Request $request)
    {
        UserBookingPreference::query()
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json([
            'message' => 'Saved booking details cleared.',
        ]);
    }

    private function formatPreference(UserBookingPreference $preference): array
    {
        return [
            'name' => $preference->name,
            'product_category' => $preference->product_category,
            'specific_products' => $preference->specific_products,
            'tapak_count' => $preference->tapak_count,
            'last_used_at' => $preference->last_used_at?->toIso8601String(),
        ];
    }
}
