<?php

namespace App\Support;

/**
 * Resolves the host venue label for report snapshots (single source of truth).
 */
final class CmartVenue
{
    public static function resolve(?object $event = null): string
    {
        foreach (['venue', 'venue_name', 'location', 'location_name'] as $field) {
            $value = is_object($event) ? ($event->{$field} ?? null) : null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        $configured = config('cmart.default_venue_name');
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        return 'CMart Changlun';
    }
}
