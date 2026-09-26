<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'gemini' => [
        'base_url' => env('GEMINI_API_BASE_URL', 'https://generativelanguage.googleapis.com'),
        'seed_api_key' => env('GEMINI_SEED_API_KEY'),
        /*
         * Preferred model IDs for new Gemini connections (first available wins).
         * Keep in sync with Google's current recommendations for new API users.
         */
        'preferred_models' => [
            'gemini-3.6-flash',
            'gemini-3.5-flash',
            'gemini-3.1-flash-lite',
            'gemini-2.0-flash',
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/oauth/google/callback'),
        'scopes' => [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/webmasters.readonly',
        ],
        'metrics_backfill_days' => (int) env('GOOGLE_METRICS_BACKFILL_DAYS', 28),
        'gsc_top_queries' => (int) env('GOOGLE_GSC_TOP_QUERIES', 50),
        'gsc_top_pages' => (int) env('GOOGLE_GSC_TOP_PAGES', 20),
        'gsc_top_countries' => (int) env('GOOGLE_GSC_TOP_COUNTRIES', 10),
        'gsc_url_inspections' => (int) env('GOOGLE_GSC_URL_INSPECTIONS', 10),
    ],

    'pagespeed' => [
        'api_key' => env('PAGESPEED_API_KEY'),
        'psi_base_url' => env('PAGESPEED_PSI_BASE_URL', 'https://www.googleapis.com'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', env('APP_URL').'/oauth/github/callback'),
        'scopes' => [
            'read:user',
            'user:email',
            'repo',
        ],
        'api_base_url' => env('GITHUB_API_BASE_URL', 'https://api.github.com'),
    ],

];
