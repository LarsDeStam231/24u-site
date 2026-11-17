<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mollie API key
    |--------------------------------------------------------------------------
    |
    | Use a "test_" key locally and a "live_" key only on the production
    | server. Without a valid key the donation form stays hidden and the
    | site keeps linking to the official donation page of the foundation.
    |
    */

    'key' => env('MOLLIE_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Retries
    |--------------------------------------------------------------------------
    |
    | Donors wait for the checkout link, so a failing connection is retried
    | briefly instead of the package default of five slow attempts.
    |
    */

    'retry' => [
        'strategy' => 'linear',
        'max_retries' => 2,
        'delay_ms' => 500,
    ],

];
