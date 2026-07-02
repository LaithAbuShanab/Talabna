<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Menu;

use App\Models\DeliveryZone;
use App\Models\RestaurantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryZoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RestaurantSetting::factory()->create(['currency_code' => 'JOD']);
    }

    public function test_lists_only_active_zones(): void
    {
        DeliveryZone::factory()->create(['name' => 'Active Zone']);
        DeliveryZone::factory()->create(['name' => 'Inactive Zone', 'is_active' => false]);

        $response = $this->getJson('/api/v1/delivery-zones');

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Active Zone');
    }

    public function test_check_by_zone_id_is_deliverable(): void
    {
        $zone = DeliveryZone::factory()->create();

        $response = $this->postJson('/api/v1/delivery-zones/check', ['zone_id' => $zone->id]);

        $response->assertOk()
            ->assertJsonPath('data.deliverable', true)
            ->assertJsonPath('data.zone.id', $zone->id);
    }

    public function test_check_by_unknown_zone_id_is_not_deliverable(): void
    {
        $response = $this->postJson('/api/v1/delivery-zones/check', ['zone_id' => 999999]);

        $response->assertOk()
            ->assertJsonPath('data.deliverable', false)
            ->assertJsonPath('data.zone', null);
    }

    public function test_check_by_inactive_zone_id_is_not_deliverable(): void
    {
        $zone = DeliveryZone::factory()->create(['is_active' => false]);

        $response = $this->postJson('/api/v1/delivery-zones/check', ['zone_id' => $zone->id]);

        $response->assertOk()->assertJsonPath('data.deliverable', false);
    }

    public function test_check_by_coordinates_inside_radius_is_deliverable(): void
    {
        $zone = DeliveryZone::factory()->create([
            'latitude' => 31.9539,
            'longitude' => 35.9106,
            'radius_meters' => 3000,
        ]);

        $response = $this->postJson('/api/v1/delivery-zones/check', [
            'latitude' => 31.9540,
            'longitude' => 35.9107,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.deliverable', true)
            ->assertJsonPath('data.zone.id', $zone->id);
    }

    public function test_check_by_coordinates_outside_radius_is_not_deliverable(): void
    {
        DeliveryZone::factory()->create([
            'latitude' => 31.9539,
            'longitude' => 35.9106,
            'radius_meters' => 100,
        ]);

        $response = $this->postJson('/api/v1/delivery-zones/check', [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        $response->assertOk()->assertJsonPath('data.deliverable', false);
    }

    public function test_check_requires_zone_id_or_coordinates(): void
    {
        $response = $this->postJson('/api/v1/delivery-zones/check', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
    }
}
