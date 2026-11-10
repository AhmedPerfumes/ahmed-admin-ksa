<?php

/*
|--------------------------------------------------------------------------
| Direct Apple Pay (PayTabs Direct Payment API)
|--------------------------------------------------------------------------
|
| Apple Pay is only offered to authenticated customers whose ID is listed
| in APPLE_PAY_TEST_CUSTOMER_IDS (comma separated, e.g. "12345,67890").
| An empty list means nobody is eligible.
|
| Certificate files must live outside the web root, e.g. storage/app/applepay/
| (already git-ignored). Paths may be absolute or relative to base_path().
|
*/

$ids = array_filter(array_map('trim', explode(',', (string) env('APPLE_PAY_TEST_CUSTOMER_IDS', ''))), 'strlen');

return [
    'enabled' => (bool) env('APPLE_PAY_ENABLED', false),

    'allowed_customer_ids' => array_values($ids),

    'merchant_id' => env('APPLE_PAY_MERCHANT_ID', 'merchant.ksa.ahmedalmaghribi.com'),
    'display_name' => env('APPLE_PAY_DISPLAY_NAME', 'Ahmed Al Maghribi'),
    'domain' => env('APPLE_PAY_DOMAIN', 'ksa.ahmedalmaghribi.com'),

    'country_code' => 'SA',
    'currency_code' => 'SAR',
    'supported_networks' => ['mada', 'visa', 'masterCard'],
    'merchant_capabilities' => ['supports3DS', 'supportsDebit', 'supportsCredit'],

    // Apple Pay Merchant Identity Certificate (PEM) + private key, used for merchant validation
    'merchant_cert_path' => env('APPLE_PAY_MERCHANT_CERT_PATH', 'storage/app/applepay/merchant-cert.crt'),
    'merchant_key_path' => env('APPLE_PAY_MERCHANT_KEY_PATH', 'storage/app/applepay/merchant-cert.key'),
    'merchant_key_passphrase' => env('APPLE_PAY_MERCHANT_KEY_PASSPHRASE'),

    // Max age of an unpaid order that may still be charged with Apple Pay
    'order_max_age_minutes' => (int) env('APPLE_PAY_ORDER_MAX_AGE_MINUTES', 30),

    'paytabs' => [
        'profile_id' => env('PAYTABS_PROFILE_ID'),
        'server_key' => env('PAYTABS_SERVER_KEY'),
        'base_url' => rtrim(env('PAYTABS_BASE_URL', 'https://secure.paytabs.sa'), '/'),
        'timeout' => (int) env('PAYTABS_TIMEOUT', 40),
    ],
];
