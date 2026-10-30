<?php

return [

    'organisation' => env('TFC_ORGANISATION', 'Tanzania Fertilizer Company'),

    'hotline' => env('TFC_HOTLINE', '+255 22 260 1561'),

    /*
     * Subsidy rules.
     */
    'max_subsidised_acres' => (float) env('TFC_MAX_SUBSIDISED_ACRES', 10),
    'voucher_validity_days' => (int) env('TFC_VOUCHER_VALIDITY_DAYS', 30),
    'reservation_hours' => (int) env('TFC_RESERVATION_HOURS', 48),

    /*
     * Logistics.
     */
    'average_truck_speed_kmh' => (int) env('TFC_TRUCK_SPEED_KMH', 45),
    'driving_hours_per_day' => (int) env('TFC_DRIVING_HOURS_PER_DAY', 10),
    'silent_truck_hours' => (int) env('TFC_SILENT_TRUCK_HOURS', 6),
    'expiry_warning_days' => (int) env('TFC_EXPIRY_WARNING_DAYS', 120),

    /*
     * SMS gateway: "log" stores messages only; "beem" sends through Beem Africa.
     */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'sender_id' => env('SMS_SENDER_ID', 'TFC-MBOLEA'),
        'beem' => [
            'api_key' => env('BEEM_API_KEY'),
            'secret' => env('BEEM_SECRET'),
            'url' => env('BEEM_URL', 'https://apisms.beem.africa/v1/send'),
        ],
    ],

    /*
     * Mobile money / GePG. "sandbox" auto-generates references; swap for the real aggregator.
     */
    'payments' => [
        'driver' => env('PAYMENT_DRIVER', 'sandbox'),
        'callback_secret' => env('PAYMENT_CALLBACK_SECRET', 'change-me'),
    ],

    'ussd_code' => env('TFC_USSD_CODE', '*152*00#'),
];
