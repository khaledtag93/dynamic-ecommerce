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
   'paymob' => [
    'api_key'        => env('PAYMOB_API_KEY'),
    'hmac_secret'    => env('PAYMOB_HMAC_SECRET'),
    'integration_id' => env('PAYMOB_INTEGRATION_ID'),
    'iframe_id'      => env('PAYMOB_IFRAME_ID'),
    'base_url'       => env('PAYMOB_BASE_URL', 'https://accept.paymob.com/api'),
    'currency'       => env('PAYMOB_CURRENCY', 'EGP'),
    'verify_ssl'     => filter_var(env('PAYMOB_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
],

];
