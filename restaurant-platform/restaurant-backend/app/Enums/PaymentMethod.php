<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case CashOnDelivery = 'cash_on_delivery';
    case CardOnDelivery = 'card_on_delivery';
}
