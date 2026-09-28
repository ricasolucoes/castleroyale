<?php

declare(strict_types=1);

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

    'google' => [
        'client_ids' => array_values(array_filter([
            env('GOOGLE_SIGN_IN_CLIENT_ID_ANDROID'),
            env('GOOGLE_SIGN_IN_CLIENT_ID_IOS'),
            env('GOOGLE_SIGN_IN_CLIENT_ID_WEB'),
            env('FIREBASE_PROJECT_ID'),
        ])),
        'issuers' => array_values(array_filter([
            'https://accounts.google.com',
            'accounts.google.com',
            env('FIREBASE_PROJECT_ID') ? 'https://securetoken.google.com/'.env('FIREBASE_PROJECT_ID') : null,
        ])),
        'jwks_url' => 'https://www.googleapis.com/oauth2/v3/certs',
        'firebase_jwks_url' => 'https://www.googleapis.com/service_accounts/v1/jwk/securetoken@system.gserviceaccount.com',
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'client_ids' => array_values(array_filter([env('FIREBASE_PROJECT_ID')])),
        'issuers' => array_values(array_filter([
            env('FIREBASE_PROJECT_ID') ? 'https://securetoken.google.com/'.env('FIREBASE_PROJECT_ID') : null,
        ])),
        'jwks_url' => 'https://www.googleapis.com/service_accounts/v1/jwk/securetoken@system.gserviceaccount.com',
    ],

    'ricagames' => [
        'url' => env('RICA_GAMES_URL', 'https://games.ricasolucoes.com.br/api/v1'),
        'api_key' => env('RICA_GAMES_API_KEY'),
        'game_code' => env('GAME_CODE', 'castleroyale'),
    ],

    'apple' => [
        'client_ids' => array_values(array_filter([env('APPLE_SIGN_IN_CLIENT_ID')])),
        'issuers' => ['https://appleid.apple.com'],
        'jwks_url' => 'https://appleid.apple.com/auth/keys',
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
