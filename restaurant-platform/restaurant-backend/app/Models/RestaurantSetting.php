<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RestaurantSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings table: there is only ever one restaurant per
 * deployment. Always read/write it through current(), which pins the row
 * to id = 1, rather than querying the table directly.
 */
#[Fillable([
    'restaurant_name',
    'phone',
    'email',
    'address',
    'latitude',
    'longitude',
    'currency_code',
    'default_delivery_fee_amount',
    'min_order_amount',
    'default_preparation_minutes',
    'is_accepting_orders',
])]
class RestaurantSetting extends Model
{
    /** @use HasFactory<RestaurantSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'default_delivery_fee_amount' => 'integer',
            'min_order_amount' => 'integer',
            'default_preparation_minutes' => 'integer',
            'is_accepting_orders' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            ['restaurant_name' => config('app.name')],
        );
    }
}
