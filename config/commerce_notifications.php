<?php

use App\Services\Commerce\OrderNotificationService;

return [
    'default_channels' => [
        'database',
        'email',
    ],

    'events' => [
        OrderNotificationService::EVENT_STATUS_UPDATED => ['database', 'email', 'whatsapp'],
        OrderNotificationService::EVENT_CANCELLED => ['database', 'email', 'whatsapp'],
        OrderNotificationService::EVENT_DELIVERY_UPDATED => ['database', 'email', 'whatsapp'],
        OrderNotificationService::EVENT_REFUND_RECORDED => ['database', 'email'],
    ],
];
