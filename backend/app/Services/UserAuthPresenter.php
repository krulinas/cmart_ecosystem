<?php

namespace App\Services;

use App\Models\ManagementProfile;
use App\Models\User;
use App\Models\VendorBusinessProfile;
use App\Support\CommunityVendorIntent;
use App\Support\ManagementRole;

class UserAuthPresenter
{
    public static function present(User $user): array
    {
        $user->loadMissing(['managementProfile', 'businessProfile']);

        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'role' => ManagementRole::normalize($user->role) ?? $user->role,
            'vendor_status' => $user->vendor_status,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        if (ManagementRole::isCmartWorker($user->role)) {
            $payload['management_profile'] = $user->managementProfile
                ? self::presentManagementProfile($user->managementProfile)
                : null;
        }

        if ($user->role === 'community') {
            $payload['vendor_business_profile'] = $user->businessProfile
                ? self::presentVendorBusinessProfile($user->businessProfile)
                : null;
        }

        return array_merge($payload, CommunityVendorIntent::resolve($user));
    }

    private static function presentManagementProfile(ManagementProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'staff_code' => $profile->staff_code,
            'tier' => $profile->tier,
            'position_title' => $profile->position_title,
            'department' => $profile->department,
            'branch_name' => $profile->branch_name,
            'is_active' => $profile->is_active,
        ];
    }

    private static function presentVendorBusinessProfile(VendorBusinessProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'business_name' => $profile->business_name,
            'business_phone' => $profile->business_phone,
            'business_category' => $profile->business_category,
            'description' => $profile->description,
            'logo_path' => $profile->logo_path,
            'logo_url' => $profile->logo_url,
        ];
    }
}
