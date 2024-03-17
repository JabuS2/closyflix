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
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => '',
        'secret' => '',
        'region' => '',
    ],

    'suitpay' => [
        'enabled' => env('SUITPAY_ENABLED', false),
        'client_id' => env('SUITPAY_CLIENT_ID'),
        'client_secret' => env('SUITPAY_CLIENT_SECRET'),
        'split' => [
            'username' => env('SUITPAY_SPLIT_USERNAME', 'gsoftware'),
            'percentage' => env('SUITPAY_SPLIT_PERCENTAGE', 1.0),
        ],
    ],

];
