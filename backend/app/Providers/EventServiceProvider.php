<?php

namespace App\Providers;

use App\Events\CarbootEventCancelled;
use App\Events\CarbootEventUpdated;
use App\Listeners\DispatchEventCancellationNotifications;
use App\Listeners\DispatchEventUpdateNotifications;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // Staff updates an event → queue emails to every registered user.
        CarbootEventUpdated::class => [
            DispatchEventUpdateNotifications::class,
        ],

        // Staff cancels / closes / deletes an event → queue cancellation emails.
        CarbootEventCancelled::class => [
            DispatchEventCancellationNotifications::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
