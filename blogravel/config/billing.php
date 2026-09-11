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
            'backup_retention_days' => env('BACKUP_FREE_RETENTION_DAYS', 30),
            'backup_max_size_mb' => env('BACKUP_FREE_MAX_SIZE_MB', 500),
        ],
        'pro' => [
            'posts' => null,
            'max_image_size_mb' => 10,
            'users' => 10,
            'backup_retention_days' => env('BACKUP_PRO_RETENTION_DAYS', 120),
            'backup_max_size_mb' => env('BACKUP_PRO_MAX_SIZE_MB', 2000),
        ],
        'business' => [
            'posts' => null,
            'max_image_size_mb' => 25,
            'users' => null,
            'backup_retention_days' => env('BACKUP_BUSINESS_RETENTION_DAYS', 120),
            'backup_max_size_mb' => env('BACKUP_BUSINESS_MAX_SIZE_MB', 5000),
        ],
    ],

];
