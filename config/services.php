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

    'mailgun'  => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses'      => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'goong'    => [
        'api_key' => env('GOONG_API_KEY'),
    ],

    'momo'     => [
        'endpoint'     => env('MOMO_ENDPOINT'),
        'partner_code' => env('MOMO_PARTNER_CODE'),
        'access_key'   => env('MOMO_ACCESS_KEY'),
        'secret_key'   => env('MOMO_SECRET_KEY'),
        'store_id'     => env('MOMO_STORE_ID'),
    ],

    'vnpay'    => [
        'url'         => env('VNPAY_URL'),
        'tmn_code'    => env('VNPAY_TMN_CODE'),
        'hash_secret' => env('VNPAY_HASH_SECRET'),
    ],

    'zalopay'  => [
        'app_id'   => env('ZALOPAY_APP_ID'),
        'key1'     => env('ZALOPAY_KEY1'),
        'key2'     => env('ZALOPAY_KEY2'),
        'endpoint' => env('ZALOPAY_ENDPOINT'),
    ],

];
