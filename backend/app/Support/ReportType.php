<?php

namespace App\Support;

/**
 * Canonical generated-report type vocabulary (MVP: post-event summary only).
 */
final class ReportType
{
    public const POST_EVENT_SUMMARY = 'post_event_summary';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::POST_EVENT_SUMMARY,
        ];
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::POST_EVENT_SUMMARY => 'Post-Event Summary',
            default => $type,
        };
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }
}
