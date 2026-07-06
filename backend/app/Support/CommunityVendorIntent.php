<?php

namespace App\Support;

use App\Models\User;

class CommunityVendorIntent
{
    private const MEANINGFUL_VENDOR_STATUSES = ['pending', 'approved', 'suspended'];

    /**
     * @return array{is_vendor_user: bool, community_mode: string|null, vendor_signals: list<string>}
     */
    public static function resolve(User $user): array
    {
        if ($user->role !== 'community') {
            return [
                'is_vendor_user' => false,
                'community_mode' => null,
                'vendor_signals' => [],
            ];
        }

        $signals = self::collectSignals($user);
        $isVendorUser = $signals !== [];

        return [
            'is_vendor_user' => $isVendorUser,
            'community_mode' => $isVendorUser ? 'vendor' : 'visitor',
            'vendor_signals' => array_keys($signals),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private static function collectSignals(User $user): array
    {
        $signals = [];

        if (self::hasMeaningfulVendorStatus($user->vendor_status)) {
            $signals['vendor_status'] = true;
        }

        if ($user->businessProfile()->exists()) {
            $signals['business_profile'] = true;
        }

        if ($user->bookings()->exists()) {
            $signals['bookings'] = true;
        }

        if ($user->vendorItems()->exists()) {
            $signals['vendor_items'] = true;
        }

        return $signals;
    }

    private static function hasMeaningfulVendorStatus(?string $status): bool
    {
        if ($status === null || $status === '') {
            return false;
        }

        return in_array($status, self::MEANINGFUL_VENDOR_STATUSES, true);
    }
}
