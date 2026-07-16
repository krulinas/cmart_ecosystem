<?php

namespace App\Services;

use App\Models\EventLayoutAuditLog;
use App\Models\User;

/**
 * Phase 3.5 — safe Organizer layout mutation audit writer.
 */
class EventLayoutAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        int $eventId,
        User $actor,
        string $action,
        ?array $before = null,
        ?array $after = null,
        ?int $rowId = null,
        ?int $siteId = null,
        ?array $metadata = null,
        ?string $reason = null,
    ): EventLayoutAuditLog {
        return EventLayoutAuditLog::create([
            'carboot_event_id' => $eventId,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'event_layout_row_id' => $rowId,
            'event_site_id' => $siteId,
            'before_snapshot' => $before,
            'after_snapshot' => $after,
            'metadata' => $metadata,
            'reason' => $reason,
        ]);
    }
}
