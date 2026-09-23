<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Locale-aware Asia/Kuala_Lumpur formatting for official report surfaces.
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
                    self::formatDate($start),
                    self::formatTime($start),
                    self::formatTime($end),
                );
            }

            return sprintf(
                '%s, %s – %s, %s',
                self::formatDate($start),
                self::formatTime($start),
                self::formatDate($end),
                self::formatTime($end),
            );
        }

        $only = $start ?? $end;

        return self::formatDateTime($only);
    }

    public static function datetime(?string $value): ?string
    {
        $parsed = self::parse($value);

        return $parsed ? self::formatDateTime($parsed) : null;
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

            return $carbon->timezone(self::TIMEZONE)->locale(app()->getLocale());
        } catch (\Throwable) {
            return null;
        }
    }

    private static function formatDate(Carbon $carbon): string
    {
        return $carbon->locale(app()->getLocale())->translatedFormat('j F Y');
    }

    private static function formatTime(Carbon $carbon): string
    {
        return $carbon->locale(app()->getLocale())->translatedFormat('g:i A');
    }

    private static function formatDateTime(Carbon $carbon): string
    {
        return $carbon->locale(app()->getLocale())->translatedFormat('j F Y, g:i A');
    }
}
