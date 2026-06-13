<?php

namespace App\Events;

use App\Models\CarbootEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when Cmart staff updates an existing carboot event (any field change).
 *
 * CarbootEventObserver dispatches this so listeners can queue email alerts
 * without slowing down the staff HTTP response.
 */
class CarbootEventUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  CarbootEvent  $event  The event model after the update was saved.
     * @param  array<string, mixed>  $changes  Only the attributes that changed.
     * @param  array<int>  $registeredUserIds  Users to notify (from event_user pivot).
     */
    public function __construct(
        public CarbootEvent $event,
        public array $changes,
        public array $registeredUserIds,
    ) {}
}
