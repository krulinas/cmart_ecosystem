<?php

namespace App\Services;

use App\Models\User;
use App\Models\VendorBusinessProfile;

class VendorProfilePresenter
{
    public static function fromUser(User $user, ?VendorBusinessProfile $profile = null): array
    {
        $profile ??= $user->businessProfile;

        $logoPath = $profile?->logo_path;
        $logoUrl = $logoPath ? asset('storage/' . ltrim(str_replace('\\', '/', $logoPath), '/')) : null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'role' => $user->role,
            'vendor_status' => $user->vendor_status,
            'business_name' => $profile?->business_name,
            'business_phone' => $profile?->business_phone,
            'business_category' => $profile?->business_category,
            'description' => $profile?->description,
            'logo_path' => $logoPath,
            'logo_url' => $logoUrl,
        ];
    }
}
