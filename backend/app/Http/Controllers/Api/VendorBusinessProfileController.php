<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VendorBusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VendorBusinessProfileController extends Controller
{
    public function show(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $profile = VendorBusinessProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'business_name' => $user->name,
                'business_phone' => $user->phone_number,
            ],
        );

        return response()->json([
            'profile' => $profile->fresh(),
            'account' => [
                'email' => $user->email,
                'name' => $user->name,
                'phone_number' => $user->phone_number,
            ],
        ]);
    }

    public function update(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'business_phone' => 'nullable|string|max:30',
            'business_category' => [
                'nullable',
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
            'description' => 'nullable|string|max:5000',
        ]);

        $profile = VendorBusinessProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['business_name' => $user->name],
        );

        $profile->update($validated);

        if (array_key_exists('business_phone', $validated)) {
            $user->update(['phone_number' => $validated['business_phone']]);
        }

        if ($validated['business_name'] !== $user->name) {
            $user->update(['name' => $validated['business_name']]);
        }

        return response()->json([
            'message' => '200 OK: Business profile updated successfully.',
            'profile' => $profile->fresh(),
            'account' => [
                'email' => $user->fresh()->email,
                'name' => $user->fresh()->name,
                'phone_number' => $user->fresh()->phone_number,
            ],
        ]);
    }

    public function uploadLogo(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'logo' => 'required|file|mimes:jpeg,jpg,png,webp|max:5120',
            'remove_logo' => 'nullable|boolean',
        ]);

        $profile = VendorBusinessProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['business_name' => $user->name],
        );

        if ($request->boolean('remove_logo')) {
            $this->deleteLogoFile($profile->logo_path);
            $profile->update(['logo_path' => null]);

            return response()->json([
                'message' => '200 OK: Business logo removed successfully.',
                'profile' => $profile->fresh(),
            ]);
        }

        if ($request->hasFile('logo')) {
            $this->deleteLogoFile($profile->logo_path);
            $path = $request->file('logo')->store('images/vendor-logos', 'public');
            $profile->update(['logo_path' => $path]);
        }

        return response()->json([
            'message' => '200 OK: Business logo updated successfully.',
            'profile' => $profile->fresh(),
        ]);
    }

    private function deleteLogoFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
