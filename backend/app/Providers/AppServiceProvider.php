<?php

namespace App\Providers;

use App\Models\CarbootEvent;
use App\Observers\CarbootEventObserver;
use App\Support\E2EDatabaseGuard;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        E2EDatabaseGuard::assertSafeFromApplication($this->app);

        // Attach observer so staff update/delete actions trigger notification events.
        CarbootEvent::observe(CarbootEventObserver::class);
    }
}
