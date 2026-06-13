<?php

namespace App\Events;

use App\Models\CarbootEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when Cmart staff cancels an event — either by:
 *   - setting status to "Closed", or
 *   - deleting the event entirely.
 *
 * We capture registeredUserIds at dispatch time because pivot rows may be
 * deleted immediately after a hard delete.
 */
class CarbootEventCancelled
{
    use Dispatchable, SerializesModels;

    /**
     * @param  CarbootEvent  $event  The event being cancelled (may be deleted soon).
     * @param  array<int>  $registeredUserIds  Users who must receive a cancellation email.
     */
    public function __construct(
        public CarbootEvent $event,
        public array $registeredUserIds,
    ) {}
}
