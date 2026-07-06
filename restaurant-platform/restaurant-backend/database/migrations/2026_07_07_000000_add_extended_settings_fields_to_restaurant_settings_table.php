<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every additional field the expanded admin Settings page needs — see
 * docs/ADMIN_RESTAURANT_SETTINGS.md. `push_notification_key` is nullable
 * text, not a fixed-length string: Eloquent's `encrypted` cast (see
 * App\Models\RestaurantSetting) stores a base64-ish ciphertext that's
 * substantially longer than the plaintext key ever was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table): void {
            $table->string('restaurant_name_ar')->nullable()->after('restaurant_name');
            $table->string('cover_image_path')->nullable()->after('logo_path');
            $table->string('timezone')->default('UTC')->after('currency_code');

            $table->boolean('is_tax_inclusive')->default(false)->after('is_tax_enabled');

            $table->boolean('allows_delivery')->default(true)->after('is_accepting_orders');
            $table->boolean('allows_pickup')->default(true)->after('allows_delivery');
            $table->text('closure_message')->nullable()->after('allows_pickup');

            $table->text('cancellation_policy_text')->nullable();
            $table->text('cancellation_policy_text_ar')->nullable();
            $table->text('terms_text')->nullable();
            $table->text('terms_text_ar')->nullable();
            $table->text('privacy_text')->nullable();
            $table->text('privacy_text_ar')->nullable();

            $table->string('facebook_url')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('whatsapp_number')->nullable();

            $table->boolean('notify_new_orders_by_email')->default(false);
            $table->text('order_notification_emails')->nullable();
            $table->boolean('push_notifications_enabled')->default(false);
            $table->text('push_notification_key')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'restaurant_name_ar',
                'cover_image_path',
                'timezone',
                'is_tax_inclusive',
                'allows_delivery',
                'allows_pickup',
                'closure_message',
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
                'push_notification_key',
            ]);
        });
    }
};
