<?php

namespace App\Listeners;

use App\Events\CarbootEventUpdated;
use App\Jobs\SendEventAlertEmailsJob;

/**
 * Reacts to CarbootEventUpdated and pushes email work onto the queue.
 *
 * Implements ShouldQueue so even the listener itself runs asynchronously
 * when QUEUE_CONNECTION is not "sync".
 */
class DispatchEventUpdateNotifications implements \Illuminate\Contracts\Queue\ShouldQueue
{
    /**
     * Build a lightweight snapshot of the event for the email job, then dispatch.
     */
    public function handle(CarbootEventUpdated $domainEvent): void
    {
        if (empty($domainEvent->registeredUserIds)) {
            // No registered users — nothing to notify.
            return;
        }

        SendEventAlertEmailsJob::dispatch(
            alertType: 'updated',
            eventSnapshot: $this->buildSnapshot($domainEvent->event),
            userIds: $domainEvent->registeredUserIds,
            changes: $domainEvent->changes,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(\App\Models\CarbootEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'starts_at' => $event->starts_at?->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'status' => $event->status,
            'description' => $event->description,
        ];
    }
}
