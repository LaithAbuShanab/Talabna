<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\BusinessHours\BusinessHourResource;
use App\Filament\Resources\BusinessHours\Pages\CreateBusinessHour;
use App\Filament\Resources\BusinessHours\Pages\EditBusinessHour;
use App\Models\BusinessHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers App\Filament\Resources\BusinessHours\BusinessHourResource:
 * creation, editing, validation, allowing more than one period per day
 * ("أكثر من فترة في اليوم إن لزم"), deletion, and access control (see
 * App\Policies\BusinessHourPolicy).
 */
class BusinessHourResourceTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
    }

    public function test_a_manager_can_create_a_business_hour_period(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateBusinessHour::class)
            ->fillForm([
                'day_of_week' => 1,
                'is_closed' => false,
                'opens_at' => '10:00',
                'closes_at' => '22:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('business_hours', ['day_of_week' => 1, 'is_closed' => false]);
    }

    public function test_a_day_can_have_more_than_one_period(): void
    {
        BusinessHour::factory()->create(['day_of_week' => 1, 'opens_at' => '11:00:00', 'closes_at' => '15:00:00']);

        Livewire::actingAs($this->manager())
            ->test(CreateBusinessHour::class)
            ->fillForm([
                'day_of_week' => 1,
                'is_closed' => false,
                'opens_at' => '18:00',
                'closes_at' => '23:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(2, BusinessHour::query()->where('day_of_week', 1)->count());
    }

    public function test_opening_and_closing_times_are_not_required_when_marked_closed(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateBusinessHour::class)
            ->fillForm([
                'day_of_week' => 5,
                'is_closed' => true,
                'opens_at' => null,
                'closes_at' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('business_hours', ['day_of_week' => 5, 'is_closed' => true]);
    }

    public function test_opening_and_closing_times_are_required_when_not_closed(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateBusinessHour::class)
            ->fillForm([
                'day_of_week' => 2,
                'is_closed' => false,
                'opens_at' => null,
                'closes_at' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['opens_at' => 'required', 'closes_at' => 'required']);
    }

    public function test_a_manager_can_edit_a_business_hour(): void
    {
        $hour = BusinessHour::factory()->create(['is_closed' => false]);

        Livewire::actingAs($this->manager())
            ->test(EditBusinessHour::class, ['record' => $hour->getKey()])
            ->fillForm(['is_closed' => true, 'opens_at' => null, 'closes_at' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($hour->fresh()->is_closed);
    }

    public function test_a_business_hour_can_be_deleted(): void
    {
        $hour = BusinessHour::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(EditBusinessHour::class, ['record' => $hour->getKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing('business_hours', ['id' => $hour->id]);
    }

    public function test_super_admin_and_manager_can_view_the_business_hours_list(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager] as $role) {
            $admin = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($admin)->get(BusinessHourResource::getUrl('index'))->assertOk();
        }
    }

    public function test_kitchen_cashier_and_support_cannot_view_the_business_hours_list(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $admin = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($admin)->get(BusinessHourResource::getUrl('index'))->assertForbidden();
        }
    }
}
