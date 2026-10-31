<?php

return [
    'currency' => 'VND',
    'money_scale' => 0,
    'cost_scale' => 4,
    'business_timezone' => env('STORE_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'warehouse_count' => 1,
    'shipments_per_order' => 1,
    'limits' => [
        'order_total' => 1_000_000_000,
        'variant_quantity' => 10,
        'cart_lines' => 50,
        'otp_attempts' => 5,
    ],
    'deadlines' => [
        'otp_minutes' => 15,
        'otp_resend_seconds' => 60,
        'online_payment_minutes' => 30,
        'cod_confirmation_hours' => 24,
        'rma_receive_days' => 7,
    ],
];
