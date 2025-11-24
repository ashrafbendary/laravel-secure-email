<?php
// src/Config/secure-email.php

return [
    /*
    |--------------------------------------------------------------------------
    | ZeroBounce API Configuration
    |--------------------------------------------------------------------------
    */
    'zerobounce' => [
        'api_key' => env('ZEROBOUNCE_API_KEY'),
        'enabled' => env('ZEROBOUNCE_ENABLED', true),
        'cache_ttl' => env('ZEROBOUNCE_CACHE_TTL', 2592000), // 30 days
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS SES Configuration
    |--------------------------------------------------------------------------
    */
    'ses' => [
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'max_bounce_count' => env('SES_MAX_BOUNCE_COUNT', 3),
        'max_complaint_count' => env('SES_MAX_COMPLAINT_COUNT', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'prefix' => env('SES_WEBHOOK_PREFIX', 'webhooks/ses'),
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Blacklist Configuration
    |--------------------------------------------------------------------------
    */
    'blacklist' => [
        'auto_blacklist_hard_bounces' => true,
        'auto_blacklist_complaints' => true,
        'soft_bounce_threshold' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'blacklist' => 'email_blacklist',
        'notifications' => 'ses_notifications',
    ],
];