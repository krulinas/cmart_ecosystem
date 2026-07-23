<?php

namespace App\Support;

/**
 * Generated-report publication lifecycle.
 */
final class GeneratedReportStatus
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const SUPERSEDED = 'superseded';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::PUBLISHED,
            self::SUPERSEDED,
        ];
    }

    /**
     * Statuses visible to CMart Management consumers.
     *
     * @return list<string>
     */
    public static function cmartVisible(): array
    {
        return [
            self::PUBLISHED,
            self::SUPERSEDED,
        ];
    }
}
