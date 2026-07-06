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
    'restaurant_name_ar',
    'logo_path',
    'cover_image_path',
    'phone',
    'email',
    'address',
    'latitude',
    'longitude',
    'currency_code',
    'timezone',
    'default_delivery_fee_amount',
    'min_order_amount',
    'default_preparation_minutes',
    'is_accepting_orders',
    'allows_scheduled_orders',
    'allows_delivery',
    'allows_pickup',
    'closure_message',
    'is_tax_enabled',
    'is_tax_inclusive',
    'tax_rate_bps',
    'cancellation_policy_text',
    'cancellation_policy_text_ar',
    'terms_text',
    'terms_text_ar',
    'privacy_text',
    'privacy_text_ar',
    'facebook_url',
    'instagram_url',
    'twitter_url',
    'whatsapp_number',
    'notify_new_orders_by_email',
    'order_notification_emails',
    'push_notifications_enabled',
    // 'push_notification_key' is deliberately absent here — the only
    // legitimate writer is App\Filament\Pages\ManageRestaurantSettings'
    // save() method, which sets it via forceFill() (mirroring the same
    // privilege-escalation guard already used for User::role): a page
    // bug that accidentally mass-assigned the wrong fields could never
    // silently overwrite an encrypted secret this way.
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
            'allows_scheduled_orders' => 'boolean',
            'allows_delivery' => 'boolean',
            'allows_pickup' => 'boolean',
            'is_tax_enabled' => 'boolean',
            'is_tax_inclusive' => 'boolean',
            'tax_rate_bps' => 'integer',
            'notify_new_orders_by_email' => 'boolean',
            'push_notifications_enabled' => 'boolean',
            // Laravel's built-in transparent encryption (Illuminate\Database\
            // Eloquent\Casts\AsEncryptedString, keyed off APP_KEY) — "عدم
            // وضع مفاتيح خدمات الدفع أو Push كنص مكشوف داخل قاعدة البيانات
            // دون تشفير". Never logged/serialized in plaintext; see
            // docs/ADMIN_RESTAURANT_SETTINGS.md for the full read/write
            // story (the settings form never re-displays the decrypted
            // value either).
            'push_notification_key' => 'encrypted',
        ];
    }

    /**
     * Every default is spelled out explicitly here rather than left to the
     * migration's column defaults: Eloquent doesn't re-read DB-applied
     * defaults back into the in-memory model after an insert, so relying on
     * them left currency_code/min_order_amount/etc. null on a freshly
     * created row until the next full reload from the database.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            [
                'restaurant_name' => config('app.name'),
                'currency_code' => 'JOD',
                'timezone' => 'UTC',
                'default_delivery_fee_amount' => 0,
                'min_order_amount' => 0,
                'default_preparation_minutes' => 30,
                'is_accepting_orders' => true,
                'allows_scheduled_orders' => false,
                'allows_delivery' => true,
                'allows_pickup' => true,
                'is_tax_enabled' => false,
                'is_tax_inclusive' => false,
                'tax_rate_bps' => 0,
                'notify_new_orders_by_email' => false,
                'push_notifications_enabled' => false,
            ],
        );
    }
}
