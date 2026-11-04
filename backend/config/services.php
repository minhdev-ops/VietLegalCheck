<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Dịch vụ nội bộ Python (SRS 6.4, docs/INTERNAL-SERVICE-CONTRACT.md)
    'ai' => [
        'url' => env('AI_SERVICE_URL', 'http://ai:8100'),
        'key' => env('AI_SERVICE_KEY', 'dev-internal-key-change-me'),
        'provider_url' => env('AI_PROVIDER_URL', ''),
        'provider_key' => env('AI_PROVIDER_KEY', ''),
        'provider_model' => env('AI_PROVIDER_MODEL', 'gpt-4o-mini'),
        'scan_max_pages' => env('AI_SCAN_MAX_PAGES', 15),
        'scan_max_bytes' => env('AI_SCAN_MAX_BYTES', 3000000),
        'scan_timeout' => env('AI_SCAN_TIMEOUT', 20),
        'clamav_enabled' => env('CLAMAV_ENABLED', false),
    ],
];