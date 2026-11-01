<?php

return [
    'currency' => 'VND',
    'money_scale' => 0,
    'cost_scale' => 4,
    'business_timezone' => env('STORE_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'warehouse_count' => 1,
    'shipments_per_order' => 1,
    'palette' => [
        'red' => ['label' => 'Red', 'hex' => '#ef4444'],
        'blue' => ['label' => 'Blue', 'hex' => '#3b82f6'],
        'green' => ['label' => 'Green', 'hex' => '#22c55e'],
        'yellow' => ['label' => 'Yellow', 'hex' => '#eab308'],
        'black' => ['label' => 'Black', 'hex' => '#000000'],
        'white' => ['label' => 'White', 'hex' => '#ffffff'],
        'gray' => ['label' => 'Gray', 'hex' => '#6b7280'],
        'pink' => ['label' => 'Pink', 'hex' => '#ec4899'],
        'purple' => ['label' => 'Purple', 'hex' => '#a855f7'],
        'orange' => ['label' => 'Orange', 'hex' => '#f97316'],
        'brown' => ['label' => 'Brown', 'hex' => '#78350f'],
        'lime' => ['label' => 'Lime', 'hex' => '#a3e635'],
    ],
    'shipping' => [
        'free_threshold' => 2_000_000,
        'hcm_fee' => 30_000,
        'other_fee' => 40_000,
        'hcm_province_code' => env('STORE_HCM_PROVINCE_CODE'),
        'dataset_version' => env('STORE_LOCATION_DATASET_VERSION'),
    ],
    'demo' => [
        'enabled' => env('STORE_DEMO_USERS', false),
        'password' => env('STORE_DEMO_PASSWORD'),
        'accounts' => [
            'admin' => ['email' => 'admin@demo.cena.test', 'full_name' => 'Cena Demo Admin'],
            'staff' => ['email' => 'staff@demo.cena.test', 'full_name' => 'Cena Demo Staff'],
            'customer' => ['email' => 'customer@demo.cena.test', 'full_name' => 'Cena Demo Customer'],
        ],
    ],
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
