<?php

return [
    'access_code'          => env('ACCESS_CODE'),
    'merchant_identifier'  => env('MERCHANT_IDENTIFIER'),
    'sha_string'           => env('SHA_STRING'),
    'redirect_url'         => env('REDIRECT_URL'),
    'tabby_profile_id'     => env('TABBY_PROFILE_ID'),
    'tabby_public_key'     => env('TABBY_PUBLIC_KEY'),
    'tabby_secret_key'     => env('TABBY_SECRET_KEY'),
    'tabby_base_url'       => env('TABBY_BASE_URL', 'https://api.tabby.ai/api/v2/'),
];
