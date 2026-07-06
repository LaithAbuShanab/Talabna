<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RestaurantSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantSetting>
 */
class RestaurantSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'restaurant_name' => fake()->company(),
            'restaurant_name_ar' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'currency_code' => 'JOD',
            'timezone' => 'UTC',
            'default_delivery_fee_amount' => fake()->numberBetween(200, 1000),
            'min_order_amount' => fake()->numberBetween(0, 2000),
            'default_preparation_minutes' => fake()->numberBetween(10, 45),
            'is_accepting_orders' => true,
            'allows_scheduled_orders' => false,
            'allows_delivery' => true,
            'allows_pickup' => true,
            'closure_message' => null,
            'is_tax_enabled' => false,
            'is_tax_inclusive' => false,
            'tax_rate_bps' => 0,
            'cancellation_policy_text' => fake()->paragraph(),
            'cancellation_policy_text_ar' => fake()->paragraph(),
            'terms_text' => fake()->paragraph(),
            'terms_text_ar' => fake()->paragraph(),
            'privacy_text' => fake()->paragraph(),
            'privacy_text_ar' => fake()->paragraph(),
            'facebook_url' => fake()->url(),
            'instagram_url' => fake()->url(),
            'twitter_url' => fake()->url(),
            'whatsapp_number' => fake()->phoneNumber(),
            'notify_new_orders_by_email' => false,
            'order_notification_emails' => fake()->companyEmail(),
            'push_notifications_enabled' => false,
            'push_notification_key' => null,
        ];
    }
}
