<?php

return [
    'enabled' => env('WHATSAPP_ENABLED', false),

    'default_provider' => env('WHATSAPP_DEFAULT_PROVIDER', 'meta'),

    'queue' => [
        'enabled' => env('WHATSAPP_QUEUE_ENABLED', false),
        'connection' => env('WHATSAPP_QUEUE_CONNECTION'),
        'queue' => env('WHATSAPP_QUEUE_NAME', 'default'),
        'tries' => (int) env('WHATSAPP_QUEUE_TRIES', 3),
        'backoff_seconds' => (int) env('WHATSAPP_QUEUE_BACKOFF_SECONDS', 30),
        'backoff_strategy' => env('WHATSAPP_QUEUE_BACKOFF_STRATEGY', 'exponential'),
        'backoff_max_seconds' => (int) env('WHATSAPP_QUEUE_BACKOFF_MAX_SECONDS', 300),
        'timeout' => (int) env('WHATSAPP_QUEUE_TIMEOUT', 120),
    ],

    'duplicate_guard' => [
        'window_minutes' => (int) env('WHATSAPP_DUPLICATE_WINDOW_MINUTES', 30),
    ],

    'meta' => [
        'base_url' => env('WHATSAPP_META_BASE_URL', 'https://graph.facebook.com'),
        'graph_version' => env('WHATSAPP_META_GRAPH_VERSION', 'v23.0'),
        'access_token' => env('WHATSAPP_META_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_META_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_META_BUSINESS_ACCOUNT_ID'),
        'app_secret' => env('WHATSAPP_META_APP_SECRET'),
        'verify_token' => env('WHATSAPP_META_VERIFY_TOKEN'),
        'timeout' => (int) env('WHATSAPP_META_TIMEOUT', 20),
        'connect_timeout' => (int) env('WHATSAPP_META_CONNECT_TIMEOUT', 10),
        'retry_times' => (int) env('WHATSAPP_META_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('WHATSAPP_META_RETRY_SLEEP_MS', 400),
    ],

    'features' => [
        'order_confirmation' => env('WHATSAPP_FEATURE_ORDER_CONFIRMATION', true),
        'order_status_update' => env('WHATSAPP_FEATURE_ORDER_STATUS_UPDATE', false),
        'delivery_update' => env('WHATSAPP_FEATURE_DELIVERY_UPDATE', false),
    ],

    'templates' => [
        'order_confirmation' => [
            'name_ar' => env('WHATSAPP_TEMPLATE_ORDER_CONFIRMATION_AR'),
            'name_en' => env('WHATSAPP_TEMPLATE_ORDER_CONFIRMATION_EN'),
            'language_ar' => env('WHATSAPP_TEMPLATE_ORDER_CONFIRMATION_AR_LANG', 'ar'),
            'language_en' => env('WHATSAPP_TEMPLATE_ORDER_CONFIRMATION_EN_LANG', 'en_US'),
            'body_map' => ['customer_name', 'order_number', 'order_total', 'payment_method', 'delivery_method'],
            'sample_body_ar' => 'مرحبًا {{1}}، تم استلام طلبك رقم {{2}} بإجمالي {{3}}. طريقة الدفع: {{4}}. طريقة التوصيل: {{5}}.',
            'sample_body_en' => 'Hi {{1}}, we received your order {{2}} with a total of {{3}}. Payment: {{4}}. Delivery: {{5}}.',
        ],
        'order_status_update' => [
            'name_ar' => env('WHATSAPP_TEMPLATE_ORDER_STATUS_UPDATE_AR'),
            'name_en' => env('WHATSAPP_TEMPLATE_ORDER_STATUS_UPDATE_EN'),
            'language_ar' => env('WHATSAPP_TEMPLATE_ORDER_STATUS_UPDATE_AR_LANG', 'ar'),
            'language_en' => env('WHATSAPP_TEMPLATE_ORDER_STATUS_UPDATE_EN_LANG', 'en_US'),
            'body_map' => ['customer_name', 'order_number', 'order_status', 'order_total'],
            'sample_body_ar' => 'مرحبًا {{1}}، تم تحديث حالة طلبك رقم {{2}} إلى {{3}}. إجمالي الطلب {{4}}.',
            'sample_body_en' => 'Hi {{1}}, your order {{2}} status is now {{3}}. Order total: {{4}}.',
        ],
        'delivery_update' => [
            'name_ar' => env('WHATSAPP_TEMPLATE_DELIVERY_UPDATE_AR'),
            'name_en' => env('WHATSAPP_TEMPLATE_DELIVERY_UPDATE_EN'),
            'language_ar' => env('WHATSAPP_TEMPLATE_DELIVERY_UPDATE_AR_LANG', 'ar'),
            'language_en' => env('WHATSAPP_TEMPLATE_DELIVERY_UPDATE_EN_LANG', 'en_US'),
            'body_map' => ['customer_name', 'order_number', 'delivery_status', 'tracking_number'],
            'sample_body_ar' => 'مرحبًا {{1}}، تم تحديث شحنة الطلب {{2}} إلى {{3}}. رقم التتبع: {{4}}.',
            'sample_body_en' => 'Hi {{1}}, delivery for order {{2}} is now {{3}}. Tracking number: {{4}}.',
        ],
    ],
];
