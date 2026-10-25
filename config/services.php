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
        'key' => env('ZEPTOMAIL_API_KEY'),
        'from' => env('ZEPTOMAIL_FROM_EMAIL'),
        'name' => env('ZEPTOMAIL_FROM_NAME', 'SalesDock'),
        'review' => env('ZEPTOMAIL_REVIEW_EMAIL', 'thatmanfrancis@gmail.com'),
    ],

    'tawk' => [
        'property_id' => env('TAWK_PROPERTY_ID'),
        'widget_id' => env('TAWK_WIDGET_ID', 'default'),
    ],

    'google_analytics' => [
        // GA4 measurement ID, e.g. G-XXXXXXXXXX. Prefer Admin → Settings → Analytics.
        'measurement_id' => env('GOOGLE_ANALYTICS_ID'),
    ],

    /*
    | Zoho SalesIQ WhatsApp. Prefer Admin → Settings → WhatsApp; env is bootstrap only.
    */
    'zoho_whatsapp' => [
        'enabled' => (bool) env('ZOHO_WHATSAPP_ENABLED', false),
        'accounts_url' => env('ZOHO_ACCOUNTS_URL', 'https://accounts.zoho.com'),
        'api_base' => env('ZOHO_SALESIQ_URL', 'https://salesiq.zoho.com'),
        'client_id' => env('ZOHO_CLIENT_ID'),
        'client_secret' => env('ZOHO_CLIENT_SECRET'),
        'refresh_token' => env('ZOHO_REFRESH_TOKEN'),
        'screen_name' => env('ZOHO_SALESIQ_SCREEN_NAME'),
        'app_id' => env('ZOHO_SALESIQ_APP_ID'),
        'department_id' => env('ZOHO_SALESIQ_DEPARTMENT_ID'),
        'otp_template_id' => env('ZOHO_WA_OTP_TEMPLATE_ID'),
        'restock_template_id' => env('ZOHO_WA_RESTOCK_TEMPLATE_ID'),
        'purchase_order_template_id' => env('ZOHO_WA_PO_TEMPLATE_ID'),
        'salesiq_widget_code' => env('ZOHO_SALESIQ_WIDGET_CODE'),
    ],

    /*
    | Bootstrap defaults for payments. Prefer Admin → Settings → Payments in the app;
    | these env values are used only when nothing has been saved there yet.
    */
    'payments' => [
        'provider' => env('PAYMENT_PROVIDER', 'flutterwave'),
        'platform_split' => (float) env('PAYMENT_PLATFORM_SPLIT', env('FLUTTERWAVE_PLATFORM_SPLIT', 0.015)),
    ],

    'flutterwave' => [
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        // Value you choose in Flutterwave > Settings > Webhooks; sent back in the verif-hash header.
        'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
    ],

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
    ],

    /*
    | Fez Delivery. Merchants connect their own Fez Business account in
    | Settings → Delivery; these values only set platform defaults/endpoints.
    */
    'fez' => [
        'enabled' => (bool) env('FEZ_ENABLED', true),
        'sandbox_base_url' => env('FEZ_SANDBOX_BASE_URL', 'https://apisandbox.fezdelivery.co/v1'),
        'production_base_url' => env('FEZ_PRODUCTION_BASE_URL', ''),
        'portal_url' => env('FEZ_PORTAL_URL', ''),
        'support_url' => env('FEZ_SUPPORT_URL', ''),
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET'),
        // Comma-separated frontend hostnames siteverify must report, e.g. "salesdock.ng,www.salesdock.ng". Never include localhost in production.
        'hostnames' => env('TURNSTILE_HOSTNAMES'),
    ],

];
