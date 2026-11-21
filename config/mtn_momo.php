<?php

/*
| MTN Mobile Money (Collection API) settings. Owned by Developer 4.
| All secrets come from .env and must never be committed.
| Sandbox first: base_url https://sandbox.momodeveloper.mtn.com, environment "sandbox",
| currency "EUR" (the sandbox only accepts EUR). Production Ghana: environment "mtnghana", currency "GHS".
*/

return [
    'base_url' => rtrim(env('MTN_MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'), '/'),
    'environment' => env('MTN_MOMO_ENVIRONMENT', 'sandbox'),
    'subscription_key' => env('MTN_MOMO_COLLECTION_SUBSCRIPTION_KEY'),
    'api_user' => env('MTN_MOMO_API_USER'),
    'api_key' => env('MTN_MOMO_API_KEY'),

    // Currency sent to MTN. Our own records always stay in GHS.
    'currency' => env('MTN_MOMO_CURRENCY', 'EUR'),

    // Public HTTPS host MTN calls back to, and a secret placed in the callback path.
    'callback_host' => env('MTN_MOMO_CALLBACK_HOST'),
    'callback_token' => env('MTN_MOMO_CALLBACK_TOKEN'),

    'timeout' => (int) env('MTN_MOMO_TIMEOUT', 10),
];
