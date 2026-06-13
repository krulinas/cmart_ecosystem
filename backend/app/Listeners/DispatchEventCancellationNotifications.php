<?php

namespace App\Listeners;

use App\Events\CarbootEventCancelled;
use App\Jobs\SendEventAlertEmailsJob;

/**
 * Reacts to CarbootEventCancelled and queues cancellation emails for every
 * user listed in the event_user pivot at the moment of cancellation.
 */
class DispatchEventCancellationNotifications implements \Illuminate\Contracts\Queue\ShouldQueue
{
    public function handle(CarbootEventCancelled $domainEvent): void
    {
        if (empty($domainEvent->registeredUserIds)) {
            return;
        }

        SendEventAlertEmailsJob::dispatch(
            alertType: 'cancelled',
            eventSnapshot: [
                'id' => $domainEvent->event->id,
                'title' => $domainEvent->event->title,
                'starts_at' => $domainEvent->event->starts_at?->toIso8601String(),
                'ends_at' => $domainEvent->event->ends_at?->toIso8601String(),
                'status' => $domainEvent->event->status,
                'description' => $domainEvent->event->description,
            ],
            userIds: $domainEvent->registeredUserIds,
        );
    }
}
