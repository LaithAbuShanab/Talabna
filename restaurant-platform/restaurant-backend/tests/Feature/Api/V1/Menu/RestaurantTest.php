<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Menu;

use App\Models\BusinessHour;
use App\Models\RestaurantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_restaurant_info(): void
    {
        RestaurantSetting::factory()->create([
            'restaurant_name' => 'Talabna Demo',
            'currency_code' => 'JOD',
            'min_order_amount' => 1000,
        ]);

        $response = $this->getJson('/api/v1/restaurant');

        $response->assertOk()
            ->assertJsonPath('data.name', 'Talabna Demo')
            ->assertJsonPath('data.currency', 'JOD')
            ->assertJsonPath('data.min_order_amount.amount_minor', 1000)
            ->assertJsonPath('data.min_order_amount.formatted', '1.000');
    }

    public function test_can_view_business_hours_ordered_by_day(): void
    {
        BusinessHour::factory()->create(['day_of_week' => 3]);
        BusinessHour::factory()->create(['day_of_week' => 0]);

        $response = $this->getJson('/api/v1/restaurant/hours');

        $response->assertOk()
            ->assertJsonPath('data.0.day_of_week', 0)
            ->assertJsonPath('data.0.day_name', 'sunday')
            ->assertJsonPath('data.1.day_of_week', 3);
    }

    public function test_is_open_true_within_business_hours(): void
    {
        RestaurantSetting::factory()->create(['is_accepting_orders' => true]);
        BusinessHour::factory()->create([
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:59',
            'is_closed' => false,
        ]);

        $this->getJson('/api/v1/restaurant/is-open')->assertOk()->assertJsonPath('data.is_open', true);
    }

    public function test_is_open_false_when_today_is_marked_closed(): void
    {
        RestaurantSetting::factory()->create(['is_accepting_orders' => true]);
        BusinessHour::factory()->closed()->create(['day_of_week' => now()->dayOfWeek]);

        $this->getJson('/api/v1/restaurant/is-open')->assertOk()->assertJsonPath('data.is_open', false);
    }

    public function test_is_open_false_when_not_accepting_orders_regardless_of_hours(): void
    {
        RestaurantSetting::factory()->create(['is_accepting_orders' => false]);
        BusinessHour::factory()->create([
            'day_of_week' => now()->dayOfWeek,
            'opens_at' => '00:00:00',
            'closes_at' => '23:59:59',
            'is_closed' => false,
        ]);

        $this->getJson('/api/v1/restaurant/is-open')->assertOk()->assertJsonPath('data.is_open', false);
    }
}
