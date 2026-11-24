<?php

namespace App\Enums;

enum PaymentEventType: string
{
    case RequestToPay = 'request_to_pay';
    case CallbackReceived = 'callback_received';
    case StatusCheck = 'status_check';
    case StatusChanged = 'status_changed';
    case Error = 'error';
    case ManualSubmitted = 'manual_submitted';
    case ManualConfirmed = 'manual_confirmed';
    case ManualRejected = 'manual_rejected';
}
