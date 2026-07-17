<?php

return [
    'approved_database' => env('E2E_APPROVED_DATABASE', 'cmart_e2e_db'),
    'blocked_databases' => [
        'cmart',
        'cmart_db',
        'production',
        'prod',
        'staging',
        'main',
        'development',
        'dev',
        'laravel',
        'forge',
    ],
];
