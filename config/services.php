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
    'microsoft' => [
        'client_id' => '4ebb9d1a-110c-4120-ae73-651c63056f8f',
        'client_secret' => 'Kxs8Q~qrCWWtTIvry7WuqwLsXLRbfyQoJKeLebpG',
        'authorize_url' => 'https://login.microsoftonline.com/e14e73eb-5251-4388-8d67-8f9f2e2d5a46/oauth2/v2.0/authorize',
        'token_url' => 'https://login.microsoftonline.com/e14e73eb-5251-4388-8d67-8f9f2e2d5a46/oauth2/v2.0/token',
    ],
    
    

];
