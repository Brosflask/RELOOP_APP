<?php

/*
| Which payment method the pay page uses. Owned by Developer 4.
|
| mtn_momo: MTN MoMo Collection API (see config/mtn_momo.php).
| manual:   buyer pays the seller outside the platform and the seller (or an
|           admin) confirms receipt. Nothing is ever marked paid automatically.
|
| Switch with PAYMENTS_DRIVER in .env.
*/

return [
    'driver' => env('PAYMENTS_DRIVER', 'mtn_momo'),

    'manual' => [
        'instructions' => env(
            'PAYMENTS_MANUAL_INSTRUCTIONS',
            'Pay the seller directly (for example by MoMo transfer to the number they share in your conversation), then enter the transaction ID from your confirmation SMS. Your order is marked paid once the seller confirms they received the money.'
        ),
    ],
];
