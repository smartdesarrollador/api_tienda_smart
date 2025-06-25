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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'izipay' => [
        'username' => env('IZIPAY_USERNAME'),
        'password' => env('IZIPAY_PASSWORD'),
        'public_key' => env('IZIPAY_PUBLIC_KEY'),
        'sha256_key' => env('IZIPAY_SHA256_KEY'),
        'api_url' => env('IZIPAY_API_URL', 'https://api.micuentaweb.pe/api-payment/V4/Charge/CreatePayment'),
        'endpoint' => env('IZIPAY_ENDPOINT', 'https://api.micuentaweb.pe'),
        'disable_ssl_verify' => env('IZIPAY_DISABLE_SSL_VERIFY', false),
        'timeout' => env('IZIPAY_TIMEOUT', 60),
        'connect_timeout' => env('IZIPAY_CONNECT_TIMEOUT', 30),
        'retry_attempts' => env('IZIPAY_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('IZIPAY_RETRY_DELAY', 2000),
        'debug_mode' => env('IZIPAY_DEBUG_MODE', false),
    ],

];
