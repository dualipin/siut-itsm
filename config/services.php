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

    /*
    |--------------------------------------------------------------------------
    | Document AI (OpenAI-compatible chat completions endpoint)
    |--------------------------------------------------------------------------
    |
    | Works with Gemini's OpenAI-compatible endpoint, OpenRouter or OpenAI.
    | Switching provider only requires changing the DOCUMENT_AI_* variables.
    |
    */

    'document_ai' => [
        'enabled' => (bool) env('DOCUMENT_AI_ENABLED', false),
        'base_url' => env('DOCUMENT_AI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/openai'),
        'api_key' => env('DOCUMENT_AI_API_KEY'),
        'model' => env('DOCUMENT_AI_MODEL', 'gemini-3.8-flash'),
        'timeout' => (int) env('DOCUMENT_AI_TIMEOUT', 60),
        'sync_timeout' => (int) env('DOCUMENT_AI_SYNC_TIMEOUT', 25),
        'max_tokens' => (int) env('DOCUMENT_AI_MAX_TOKENS', 2048),
        'max_dimension' => (int) env('DOCUMENT_AI_MAX_DIMENSION', 1000),
        'jpeg_quality' => (int) env('DOCUMENT_AI_JPEG_QUALITY', 70),
        'store_max_dimension' => (int) env('DOCUMENT_AI_STORE_MAX_DIMENSION', 1600),
        'store_jpeg_quality' => (int) env('DOCUMENT_AI_STORE_JPEG_QUALITY', 80),
        'json_mode' => (bool) env('DOCUMENT_AI_JSON_MODE', true),
        'min_reject_confidence' => (float) env('DOCUMENT_AI_MIN_REJECT_CONFIDENCE', 0.7),
        'throttle_per_hour' => (int) env('DOCUMENT_AI_THROTTLE_PER_HOUR', 10),
        'daily_cap' => (int) env('DOCUMENT_AI_DAILY_CAP', 300),
        // Optional provider headers (OpenRouter attribution). Empty values are dropped.
        'headers' => array_filter([
            'HTTP-Referer' => env('DOCUMENT_AI_REFERER'),
            'X-Title' => env('DOCUMENT_AI_APP_TITLE'),
        ]),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
