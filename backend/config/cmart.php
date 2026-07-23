<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default host venue label
    |--------------------------------------------------------------------------
    |
    | Used when a Carboot event has no venue/location field. Resolved once into
    | generated-report snapshots so HTML/PDF never recalculate independently.
    |
    */
    'default_venue_name' => env('CMART_DEFAULT_VENUE_NAME', 'CMart Changlun'),
];
