<?php

namespace App\Enums;

enum WhatsAppMessageType: string
{
    case ORDER_CONFIRMATION = 'order_confirmation';
    case ORDER_STATUS_UPDATE = 'order_status_update';
    case DELIVERY_UPDATE = 'delivery_update';
}
