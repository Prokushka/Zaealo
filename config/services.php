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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'telegram' => [
        'client_id' => env('TELEGRAM_CLIENT_ID'),
        'client_secret' => env('TELEGRAM_CLIENT_SECRET'),
        'redirect' => env('TELEGRAM_REDIRECT_URI'),
    ],

    'yandex' => [
        'client_id' => env('YANDEX_CLIENT_ID'),
        'client_secret' => env('YANDEX_CLIENT_SECRET'),
        'redirect' => env('YANDEX_REDIRECT_URI'),
    ],
    'zenrows' => [
        'key' => env('ZENROWS_API_KEY'),
        'base_url' => env('ZENROWS_BASE_URL', 'https://api.zenrows.com/v1/'),
    ],
    'wildberries' => [
        'search_url' => env('WILDBERRIES_SEARCH_URL', 'https://search.wb.ru/exactmatch/ru/common/v4/search'),
        'categories_api_key' => env('WILDBERRIES_CATEGORIES_API_KEY'),
    ],
    'ozon' => [
        'categories_client_id' => env('OZON_CATEGORIES_CLIENT_ID'),
        'categories_api_key' => env('OZON_CATEGORIES_API_KEY'),
    ],
    'aitunnel' => [
        'key' => env('AITUNNEL_API_KEY'),
        'base_url' => env('AITUNNEL_BASE_URL', 'https://api.aitunnel.ru'),
        'model' => env('AITUNNEL_MODEL', 'auto'),
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

];
