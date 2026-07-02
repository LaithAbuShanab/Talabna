<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DeliveryZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'delivery_fee_amount',
    'min_order_amount',
    'estimated_minutes',
    'latitude',
    'longitude',
    'radius_meters',
    'sort_order',
    'is_active',
])]
class DeliveryZone extends Model
{
    /** @use HasFactory<DeliveryZoneFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'delivery_fee_amount' => 'integer',
            'min_order_amount' => 'integer',
            'estimated_minutes' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
