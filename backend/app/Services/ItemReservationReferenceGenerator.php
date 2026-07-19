<?php

namespace App\Services;

class ItemReservationReferenceGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function generate(): string
    {
        $random = '';
        $maximumIndex = strlen(self::ALPHABET) - 1;

        for ($position = 0; $position < 8; $position++) {
            $random .= self::ALPHABET[random_int(0, $maximumIndex)];
        }

        return 'RSV-'.$random;
    }
}
