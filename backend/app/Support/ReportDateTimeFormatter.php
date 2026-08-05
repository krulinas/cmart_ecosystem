<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * English, Asia/Kuala_Lumpur formatting for official report surfaces.
 */
final class ReportDateTimeFormatter
{
    public const TIMEZONE = 'Asia/Kuala_Lumpur';

    public static function range(?string $startsAt, ?string $endsAt): ?string
    {
        $start = self::parse($startsAt);
        $end = self::parse($endsAt);

        if (! $start && ! $end) {
            return null;
        }

        if ($start && $end) {
            if ($start->isSameDay($end)) {
                return sprintf(
                    '%s, %s – %s',
                    $start->format('j F Y'),
                    $start->format('g:i A'),
                    $end->format('g:i A'),
                );
            }

            return sprintf(
                '%s, %s – %s, %s',
                $start->format('j F Y'),
                $start->format('g:i A'),
                $end->format('j F Y'),
                $end->format('g:i A'),
            );
        }

        $only = $start ?? $end;

        return $only->format('j F Y, g:i A');
    }

    public static function datetime(?string $value): ?string
    {
        $parsed = self::parse($value);

        return $parsed?->format('j F Y, g:i A');
    }

    public static function parse(null|string|CarbonInterface $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $carbon = $value instanceof CarbonInterface
                ? Carbon::instance($value)
                : Carbon::parse($value);

            return $carbon->timezone(self::TIMEZONE);
        } catch (\Throwable) {
            return null;
        }
    }
}
