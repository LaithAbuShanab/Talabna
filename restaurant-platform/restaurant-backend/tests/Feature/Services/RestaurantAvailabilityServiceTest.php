<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\BusinessHour;
use App\Models\BusinessHourException;
use App\Models\RestaurantSetting;
use App\Services\RestaurantAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Covers App\Services\RestaurantAvailabilityService::isOpenNow() — the
 * multi-period-per-day support ("أكثر من فترة في اليوم إن لزم") and the
 * new holiday-exception override, on top of the pre-existing
 * is_accepting_orders/single-period behavior.
 */
class RestaurantAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-08 13:00:00')); // a Wednesday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function service(): RestaurantAvailabilityService
    {
        return app(RestaurantAvailabilityService::class);
    }

    public function test_closed_when_not_accepting_orders_regardless_of_hours(): void
    {
        $settings = RestaurantSetting::current();
        $settings->update(['is_accepting_orders' => false]);
        BusinessHour::factory()->create(['day_of_week' => now()->dayOfWeek, 'opens_at' => '00:00:00', 'closes_at' => '23:59:59']);

        $this->assertFalse($this->service()->isOpenNow());
    }

    public function test_open_during_a_single_periods_hours(): void
    {
        RestaurantSetting::current()->update(['is_accepting_orders' => true]);
        BusinessHour::factory()->create(['day_of_week' => now()->dayOfWeek, 'opens_at' => '10:00:00', 'closes_at' => '23:00:00']);

        $this->assertTrue($this->service()->isOpenNow());
    }

    public function test_closed_outside_the_single_periods_hours(): void
    {
        RestaurantSetting::current()->update(['is_accepting_orders' => true]);
        BusinessHour::factory()->create(['day_of_week' => now()->dayOfWeek, 'opens_at' => '18:00:00', 'closes_at' => '23:00:00']);

        $this->assertFalse($this->service()->isOpenNow());
    }

    public function test_open_during_the_second_of_two_periods_on_the_same_day(): void
    {
        RestaurantSetting::current()->update(['is_accepting_orders' => true]);
        BusinessHour::factory()->create(['day_of_week' => now()->dayOfWeek, 'opens_at' => '08:00:00', 'closes_at' => '11:00:00']);
        BusinessHour::factory()->create(['day_of_week' => now()->dayOfWeek, 'opens_at' => '12:00:00', 'closes_at' => '23:00:00']);

        $this->assertTrue($this->service()->isOpenNow());
    }

    public function test_closed_in_the_gap_between_two_periods(): void
    {
        RestaurantSetting::current()->update(['is_accepting_orders' => true]);
        BusinessHour::factory()->create(['day_of_week' => now()->dayOfWeek, 'opens_at' => '08:00:00', 'closes_at' => '11:00:00']);
        BusinessHour::factory()->create(['day_of_week' => now()->dayOfWeek, 'opens_at' => '14:00:00', 'closes_at' => '23:00:00']);

        $this->assertFalse($this->service()->isOpenNow());
    }

    public function test_a_holiday_exception_closes_the_restaurant_despite_normal_open_hours(): void
    {
        RestaurantSetting::current()->update(['is_accepting_orders' => true]);
        BusinessHour::factory()->create(['day_of_week' => now()->dayOfWeek, 'opens_at' => '00:00:00', 'closes_at' => '23:59:59']);
        BusinessHourException::factory()->create(['date' => now()->toDateString(), 'is_closed' => true]);

        $this->assertFalse($this->service()->isOpenNow());
    }

    public function test_a_holiday_exception_can_grant_custom_hours_outside_the_normal_schedule(): void
    {
        RestaurantSetting::current()->update(['is_accepting_orders' => true]);
        BusinessHour::factory()->create(['day_of_week' => now()->dayOfWeek, 'is_closed' => true, 'opens_at' => null, 'closes_at' => null]);
        BusinessHourException::factory()->customHours()->create(['date' => now()->toDateString(), 'opens_at' => '12:00:00', 'closes_at' => '18:00:00']);

        $this->assertTrue($this->service()->isOpenNow());
    }

    public function test_falls_back_to_the_regular_schedule_when_no_exception_exists_for_today(): void
    {
        RestaurantSetting::current()->update(['is_accepting_orders' => true]);
        BusinessHour::factory()->create(['day_of_week' => now()->dayOfWeek, 'opens_at' => '10:00:00', 'closes_at' => '23:00:00']);
        BusinessHourException::factory()->create(['date' => now()->addDays(5)->toDateString(), 'is_closed' => true]);

        $this->assertTrue($this->service()->isOpenNow());
    }

    public function test_it_uses_the_configured_timezone_rather_than_utc(): void
    {
        // Test time is 2026-07-08 13:00:00 UTC, which is 16:00 in Asia/Amman
        // (UTC+3) — business hours are checked against the *local* time.
        $settings = RestaurantSetting::current();
        $settings->update(['is_accepting_orders' => true, 'timezone' => 'Asia/Amman']);
        BusinessHour::factory()->create(['day_of_week' => now('Asia/Amman')->dayOfWeek, 'opens_at' => '15:00:00', 'closes_at' => '17:00:00']);

        $this->assertTrue($this->service()->isOpenNow($settings));
    }

    public function test_it_is_closed_outside_business_hours_measured_in_the_configured_timezone(): void
    {
        // 13:00 UTC falls inside 12:00-14:00, but that window has already
        // passed by 16:00 in Asia/Amman (UTC+3).
        $settings = RestaurantSetting::current();
        $settings->update(['is_accepting_orders' => true, 'timezone' => 'Asia/Amman']);
        BusinessHour::factory()->create(['day_of_week' => now('Asia/Amman')->dayOfWeek, 'opens_at' => '12:00:00', 'closes_at' => '14:00:00']);

        $this->assertFalse($this->service()->isOpenNow($settings));
    }
}
