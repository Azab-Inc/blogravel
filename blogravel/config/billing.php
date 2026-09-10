<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing Enabled
    |--------------------------------------------------------------------------
    |
    | Set BILLING_ENABLED=true in .env to enable Stripe billing.
    | When false, all plan limits are ignored (self-hosted mode).
    |
    */

    'enabled' => env('BILLING_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration
    |--------------------------------------------------------------------------
    */

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plan Limits
    |--------------------------------------------------------------------------
    |
    | Limits per plan tier. Set to null for unlimited.
    |
    */

    'plans' => [
        'free' => [
            'posts' => 50,
            'max_image_size_mb' => 2,
            'users' => 3,
        ],
        'pro' => [
            'posts' => null,
            'max_image_size_mb' => 10,
            'users' => 10,
        ],
        'business' => [
            'posts' => null,
            'max_image_size_mb' => 25,
            'users' => null,
        ],
    ],

];
