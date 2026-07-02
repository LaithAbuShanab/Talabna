<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\BusinessHour;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\RestaurantSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_base_seeder_runs_cleanly_and_creates_the_expected_baseline_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, RestaurantSetting::query()->count());
        $this->assertSame(7, BusinessHour::query()->count());
        $this->assertGreaterThan(0, Category::query()->count());
        $this->assertGreaterThan(0, Product::query()->count());
        $this->assertGreaterThan(0, OptionGroup::query()->count());
        $this->assertGreaterThan(0, DeliveryZone::query()->count());
        $this->assertTrue(User::query()->where('role', UserRole::Admin)->exists());
    }

    public function test_running_the_seeder_twice_does_not_duplicate_singleton_or_unique_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, RestaurantSetting::query()->count());
        $this->assertSame(7, BusinessHour::query()->count());
    }
}
