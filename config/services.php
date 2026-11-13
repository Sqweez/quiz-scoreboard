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

    'quizplease' => [
        'url' => env('QUIZPLEASE_API_URL', 'https://api.quizplease.com'),
        'rating_url' => env('QUIZPLEASE_RATING_API_URL', 'https://rating-api.quizplease.ru'),
        'client_id' => env('QUIZPLEASE_CLIENT_ID'),
        'client_secret' => env('QUIZPLEASE_CLIENT_SECRET'),
        'rating_synchronize_key' => env('QUIZPLEASE_RATING_SYNCHRONIZE_KEY'),
        'timeout' => 10,
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
