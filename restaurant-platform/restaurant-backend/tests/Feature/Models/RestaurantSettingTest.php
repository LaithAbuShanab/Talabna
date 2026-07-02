<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\RestaurantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_creates_the_singleton_row_on_first_call(): void
    {
        $this->assertDatabaseCount('restaurant_settings', 0);

        $settings = RestaurantSetting::current();

        $this->assertSame(1, $settings->id);
        $this->assertDatabaseCount('restaurant_settings', 1);
    }

    public function test_current_always_returns_the_same_row(): void
    {
        $first = RestaurantSetting::current();
        $first->update(['restaurant_name' => 'My Restaurant']);

        $second = RestaurantSetting::current();

        $this->assertSame($first->id, $second->id);
        $this->assertSame('My Restaurant', $second->restaurant_name);
        $this->assertDatabaseCount('restaurant_settings', 1);
    }

    public function test_money_and_boolean_fields_cast_correctly(): void
    {
        $settings = RestaurantSetting::factory()->create([
            'default_delivery_fee_amount' => '500',
            'is_accepting_orders' => 1,
        ]);

        $fresh = $settings->fresh();

        $this->assertIsInt($fresh->default_delivery_fee_amount);
        $this->assertIsBool($fresh->is_accepting_orders);
        $this->assertTrue($fresh->is_accepting_orders);
    }
}
