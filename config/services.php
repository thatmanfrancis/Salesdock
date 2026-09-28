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

    'zeptomail' => [
        'key'    => env('ZEPTOMAIL_API_KEY'),
        'from'   => env('ZEPTOMAIL_FROM_EMAIL'),
        'name'   => env('ZEPTOMAIL_FROM_NAME', 'SalesDock'),
        'review' => env('ZEPTOMAIL_REVIEW_EMAIL', 'thatmanfrancis@gmail.com'),
    ],

    'tawk' => [
        'property_id' => env('TAWK_PROPERTY_ID'),
        'widget_id'   => env('TAWK_WIDGET_ID', 'default'),
    ],

];
