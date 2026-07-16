<?php

/**
 * Phase 3.3 — PHPUnit / artisan --env=testing database safety contract.
 *
 * Credentials are inherited from .env / .env.testing / phpunit.xml — never commit secrets.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Approved disposable test database
    |--------------------------------------------------------------------------
    |
    | PHPUnit and database-aware tests may only run against this exact database
    | name unless CI sets TESTING_APPROVED_DATABASE to another non-blocked name.
    |
    */
    'approved_database' => env('TESTING_APPROVED_DATABASE', 'cmart_test'),

    /*
    |--------------------------------------------------------------------------
    | Known persistent development database
    |--------------------------------------------------------------------------
    |
    | Tests must never resolve to this database while APP_ENV=testing.
    |
    */
    'development_database' => env('TESTING_DEVELOPMENT_DATABASE', 'cmart_db'),

    /*
    |--------------------------------------------------------------------------
    | Blocked database names (case-insensitive)
    |--------------------------------------------------------------------------
    */
    'blocked_databases' => [
        'cmart_db',
        'cmart',
        'production',
        'prod',
        'staging',
        'main',
        'development',
        'dev',
        'laravel',
        'forge',
    ],

    /*
    |--------------------------------------------------------------------------
    | Approved connection drivers for integration tests
    |--------------------------------------------------------------------------
    */
    'approved_connections' => [
        'mysql',
    ],

];
