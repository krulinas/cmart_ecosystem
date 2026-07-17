<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AllocationValidationException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VendorBusinessProfile;
use App\Services\UserAuthPresenter;
use App\Services\VendorCategoryResolver;
use App\Services\VendorProfilePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VendorProfileController extends Controller
{
    public function show(Request $request)
    {
        /** @var User $user */
        $user = $request->user()->load('businessProfile.vendorCategory');

        $profile = $this->ensureBusinessProfile($user);

        return response()->json([
            'profile' => VendorProfilePresenter::fromUser($user->fresh('businessProfile.vendorCategory'), $profile),
        ]);
    }

    public function update(Request $request, VendorCategoryResolver $categoryResolver)
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:30',
            'business_name' => 'required|string|max:255',
            'business_phone' => 'nullable|string|max:30',
            'vendor_category_id' => 'nullable|integer|exists:vendor_categories,id',
            'business_category' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'logo' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
            'remove_logo' => 'nullable|boolean',
        ]);

        $user->update([
            'name' => $validated['name'],
            'phone_number' => $validated['phone_number'] ?? null,
        ]);

        $profile = $this->ensureBusinessProfile($user);

        $categoryFields = [];
        $hasCategoryInput = array_key_exists('vendor_category_id', $validated)
            || array_key_exists('business_category', $validated);

        if ($hasCategoryInput) {
            $id = $validated['vendor_category_id'] ?? null;
            $label = $validated['business_category'] ?? null;

            if ($id === null && ($label === null || trim((string) $label) === '')) {
                $categoryFields = [
                    'vendor_category_id' => null,
                    'business_category' => null,
                ];
            } else {
                try {
                    $category = $categoryResolver->resolveForOperationalUse(
                        $id !== null ? (int) $id : null,
                        is_string($label) ? $label : null,
                    );
                } catch (AllocationValidationException $exception) {
                    return response()->json([
                        'message' => $exception->getMessage(),
                        'error' => $exception->error,
                    ], 422);
                }

                $categoryFields = [
                    'vendor_category_id' => $category->id,
                    'business_category' => $category->label,
                ];
            }
        }

        $profile->update(array_merge([
            'business_name' => $validated['business_name'],
            'business_phone' => $validated['business_phone'] ?? null,
            'description' => $validated['description'] ?? null,
        ], $categoryFields));

        if ($request->boolean('remove_logo')) {
            $this->deleteLogoFile($profile->logo_path);
            $profile->update(['logo_path' => null]);
        } elseif ($request->hasFile('logo')) {
            $this->deleteLogoFile($profile->logo_path);
            $path = $request->file('logo')->store('images/vendor-logos', 'public');
            $profile->update(['logo_path' => $path]);
        }

        $user = $user->fresh('businessProfile.vendorCategory');
        $profile = $user->businessProfile;

        return response()->json([
            'message' => '200 OK: Vendor profile updated successfully.',
            'profile' => VendorProfilePresenter::fromUser($user, $profile),
            'user' => UserAuthPresenter::present($user),
        ]);
    }

    private function ensureBusinessProfile(User $user): VendorBusinessProfile
    {
        return VendorBusinessProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'business_name' => $user->name,
                'business_phone' => $user->phone_number,
            ],
        );
    }

    private function deleteLogoFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
