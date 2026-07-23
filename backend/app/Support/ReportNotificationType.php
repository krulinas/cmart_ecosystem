<?php

namespace App\Support;

/**
 * Canonical in-app notification `data.type` values for the report workflow.
 */
final class ReportNotificationType
{
    public const REQUEST_CREATED = 'report_request_created';
    public const REQUEST_ACKNOWLEDGED = 'report_request_acknowledged';
    public const REQUEST_DECLINED = 'report_request_declined';
    public const PUBLISHED = 'report_published';
    public const REVISED = 'report_revised';

    /**
     * Unread types that increment the Organizer Report Centre badge.
     *
     * @return list<string>
     */
    public static function organizerBadgeTypes(): array
    {
        return [
            self::REQUEST_CREATED,
        ];
    }

    /**
     * Unread types that increment the CMart Reports badge.
     *
     * @return list<string>
     */
    public static function cmartBadgeTypes(): array
    {
        return [
            self::REQUEST_ACKNOWLEDGED,
            self::REQUEST_DECLINED,
            self::PUBLISHED,
            self::REVISED,
        ];
    }

    /**
     * All report-workflow notification types.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::REQUEST_CREATED,
            self::REQUEST_ACKNOWLEDGED,
            self::REQUEST_DECLINED,
            self::PUBLISHED,
            self::REVISED,
        ];
    }
}
