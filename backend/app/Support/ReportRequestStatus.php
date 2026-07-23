<?php

namespace App\Support;

/**
 * Report-request lifecycle statuses and actor-scoped transitions.
 *
 * Actors: cmart (CMart Management), organizer, system (publication fulfillment).
 */
final class ReportRequestStatus
{
    public const REQUESTED = 'requested';
    public const ACKNOWLEDGED = 'acknowledged';
    public const IN_PROGRESS = 'in_progress';
    public const FULFILLED = 'fulfilled';
    public const DECLINED = 'declined';
    public const CANCELLED = 'cancelled';

    /** Statuses that block a duplicate request for the same event + type. */
    public const ACTIVE = [
        self::REQUESTED,
        self::ACKNOWLEDGED,
        self::IN_PROGRESS,
    ];

    public const ACTOR_CMART = 'cmart';
    public const ACTOR_ORGANIZER = 'organizer';
    public const ACTOR_SYSTEM = 'system';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::REQUESTED,
            self::ACKNOWLEDGED,
            self::IN_PROGRESS,
            self::FULFILLED,
            self::DECLINED,
            self::CANCELLED,
        ];
    }

    public static function isActive(string $status): bool
    {
        return in_array($status, self::ACTIVE, true);
    }

    /**
     * Whether $actor may move a request from $from to $to.
     *
     * @param  'cmart'|'organizer'|'system'  $actor
     */
    public static function canTransition(string $from, string $to, string $actor): bool
    {
        $allowed = self::transitionsFor($actor);

        return in_array($to, $allowed[$from] ?? [], true);
    }

    /**
     * @param  'cmart'|'organizer'|'system'  $actor
     * @return array<string, list<string>>
     */
    private static function transitionsFor(string $actor): array
    {
        return match ($actor) {
            self::ACTOR_CMART => [
                self::REQUESTED => [self::CANCELLED],
            ],
            self::ACTOR_ORGANIZER => [
                self::REQUESTED => [self::ACKNOWLEDGED, self::IN_PROGRESS, self::DECLINED],
                self::ACKNOWLEDGED => [self::IN_PROGRESS, self::DECLINED],
            ],
            self::ACTOR_SYSTEM => [
                self::IN_PROGRESS => [self::FULFILLED],
                self::ACKNOWLEDGED => [self::FULFILLED],
                self::REQUESTED => [self::FULFILLED],
            ],
            default => [],
        };
    }
}
