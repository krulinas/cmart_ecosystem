<?php

namespace Tests;

use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * The testing database guard runs immediately after kernel bootstrap and
     * before any test setUp(), RefreshDatabase, or fixture cleanup can mutate data.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        TestingDatabaseGuard::assertSafeFromApplication($app);

        return $app;
    }
}
