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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'payos_payment_rachgia' => [
        'client_id' => env('PAYOS_RACHGIA_CLIENT_ID'),
        'api_key' => env('PAYOS_RACHGIA_API_KEY'),
        'checksum_key' => env('PAYOS_RACHGIA_CHECKSUM_KEY'),
        'endpoint' => env('PAYOS_RACHGIA_BASE_URL', 'https://api-merchant.payos.vn'),
    ],

    'payos_payment_others' => [
        'client_id' => env('PAYOS_OTHERS_CLIENT_ID'),
        'api_key' => env('PAYOS_OTHERS_API_KEY'),
        'checksum_key' => env('PAYOS_OTHERS_CHECKSUM_KEY'),
        'endpoint' => env('PAYOS_OTHERS_BASE_URL', 'https://api-merchant.payos.vn'),
    ],

    'payos_payment' => [ // Fallback/Default về Rạch Giá
        'client_id' => env('PAYOS_RACHGIA_CLIENT_ID'),
        'api_key' => env('PAYOS_RACHGIA_API_KEY'),
        'checksum_key' => env('PAYOS_RACHGIA_CHECKSUM_KEY'),
        'endpoint' => env('PAYOS_RACHGIA_BASE_URL', 'https://api-merchant.payos.vn'),
    ],

    'payos_payout' => [
        'client_id' => env('PAYOS_PAYOUT_CLIENT_ID'),
        'api_key' => env('PAYOS_PAYOUT_API_KEY'),
        'checksum_key' => env('PAYOS_PAYOUT_CHECKSUM_KEY'),
        'endpoint' => env('PAYOS_PAYOUT_BASE_URL', 'https://api-merchant.payos.vn'),
    ],

    'google' => [
        'maps_key' => env('GOOGLE_MAPS_JS_KEY'),
    ],
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],
    'firebase' => [
        'database_url'         => env('FIREBASE_DATABASE_URL'),
        'api_key'              => env('FIREBASE_API_KEY'),
        'auth_domain'          => env('FIREBASE_AUTH_DOMAIN'),
        'project_id'           => env('FIREBASE_PROJECT_ID'),
        'storage_bucket'       => env('FIREBASE_STORAGE_BUCKET'),
        'messaging_sender_id'  => env('FIREBASE_MESSAGING_SENDER_ID'),
        'app_id'               => env('FIREBASE_APP_ID'),
        'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH') ?: storage_path('app/firebase-service-account.json'),
    ],
];
