<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Services\OrderNumberGenerator;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * No soft deletes: orders are financial/transactional records and are
 * never deleted. delivery_address_* columns are a snapshot of the
 * customer_address at order time, so a later edit/deletion of that
 * address can never change a past order — see docs/DATABASE_SCHEMA.md.
 */
#[Fillable([
    'order_number',
    'idempotency_key',
    'user_id',
    'status',
    'delivery_type',
    'payment_method',
    'payment_status',
    'subtotal_amount',
    'discount_amount',
    'delivery_fee_amount',
    'total_amount',
    'coupon_id',
    'delivery_zone_id',
    'customer_address_id',
    'delivery_address_line',
    'delivery_city',
    'delivery_latitude',
    'delivery_longitude',
    'customer_notes',
    'rejection_reason',
    'cancellation_reason',
    'expected_delivery_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * order_number is generated here (via the container-resolved
     * OrderNumberGenerator, not a static call) so any code path that
     * creates an Order gets one automatically if it isn't already set.
     */
    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->order_number ??= app(OrderNumberGenerator::class)->generate();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'delivery_type' => DeliveryType::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'delivery_fee_amount' => 'integer',
            'total_amount' => 'integer',
            'delivery_latitude' => 'decimal:7',
            'delivery_longitude' => 'decimal:7',
            'expected_delivery_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function deliveryZone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class);
    }

    public function customerAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function couponUsage(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }
}
