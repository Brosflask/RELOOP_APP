<?php

namespace App\Enums;

enum ComplaintCategory: string
{
    case OrderIssue = 'order_issue';
    case PaymentIssue = 'payment_issue';
    case ProductQuality = 'product_quality';
    case UserConduct = 'user_conduct';
    case Other = 'other';
}
