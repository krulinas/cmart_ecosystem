<?php

namespace App\Observers;

use App\Events\CarbootEventCancelled;
use App\Events\CarbootEventUpdated;
use App\Models\CarbootEvent;

/**
 * Watches CarbootEvent model lifecycle hooks for staff "Update" and "Cancel" actions.
 *
 * Registered in AppServiceProvider::boot().
 *
 * Flow:
 *   Staff clicks Save  → updated()  → CarbootEventUpdated  → Listener → Queue Job → Email
 *   Staff sets Closed  → updated()  → CarbootEventCancelled → Listener → Queue Job → Email
 *   Staff clicks Delete → deleting() → CarbootEventCancelled → Listener → Queue Job → Email
 */
class CarbootEventObserver
{
    /**
     * Called after an existing event row is saved with changes.
     */
    public function updated(CarbootEvent $event): void
    {
        // wasChanged() returns false when staff submitted the form with no real edits.
        if (! $event->wasChanged()) {
            return;
        }

        $registeredUserIds = $event->registeredUsers()->pluck('users.id')->all();

        /*
         * Treat a status change to "Closed" as a cancellation (not a generic update)
         * so users receive the correct email template.
         */
        if ($event->wasChanged('status') && $event->status === 'Closed') {
            event(new CarbootEventCancelled($event, $registeredUserIds));

            return;
        }

        event(new CarbootEventUpdated($event, $event->getChanges(), $registeredUserIds));
    }

    /**
     * Called BEFORE the row is deleted so we can still read the pivot table.
     */
    public function deleting(CarbootEvent $event): void
    {
        $registeredUserIds = $event->registeredUsers()->pluck('users.id')->all();

        event(new CarbootEventCancelled($event, $registeredUserIds));
    }
}
