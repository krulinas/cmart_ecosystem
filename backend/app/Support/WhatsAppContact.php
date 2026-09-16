<?php

namespace App\Support;

use App\Models\VendorBusinessProfile;

class WhatsAppContact
{
    public const OPT_IN_PHONE_REQUIRED_MESSAGE =
        'Add a valid business phone number before enabling WhatsApp contact.';

    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $hasPlus = str_starts_with($trimmed, '+');
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        if ($digits === '') {
            return null;
        }

        if (! $hasPlus && preg_match('/^0[1-9]\d{7,9}$/', $digits) === 1) {
            $digits = '60'.substr($digits, 1);
        }

        if (preg_match('/^[1-9]\d{7,14}$/', $digits) !== 1) {
            return null;
        }

        return $digits;
    }

    public static function url(string $digits, string $message): string
    {
        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }

    public static function optInError(?string $phone, bool $enabled): ?string
    {
        if (! $enabled) {
            return null;
        }

        return self::normalize($phone) ? null : self::OPT_IN_PHONE_REQUIRED_MESSAGE;
    }

    public static function publicContact(
        ?VendorBusinessProfile $profile,
        string $message,
    ): ?array {
        if (! $profile?->marketplace_whatsapp_enabled) {
            return null;
        }

        $digits = self::normalize($profile->business_phone);
        if (! $digits) {
            return null;
        }

        return [
            'available' => true,
            'url' => self::url($digits, $message),
        ];
    }

    public static function marketplaceMessage(
        string $vendorName,
        string $itemName,
        ?string $eventLabel = null,
    ): string {
        $eventBit = $eventLabel ? ' for the '.$eventLabel.' event' : '';

        return 'Hi '.$vendorName.', I found “'.$itemName.'” on CMart Carboot Preview'.$eventBit.'. Is this item still available?';
    }

    public static function reservationMessage(
        string $vendorName,
        string $itemName,
        string $reference,
    ): string {
        return 'Hi '.$vendorName.', I’m contacting you about my CMart reservation '.$reference.' for “'.$itemName.'”.';
    }
}
