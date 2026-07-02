<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliveryType: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';
}
