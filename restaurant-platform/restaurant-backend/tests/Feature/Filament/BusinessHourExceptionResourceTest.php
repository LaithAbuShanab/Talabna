<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\BusinessHourExceptions\BusinessHourExceptionResource;
use App\Filament\Resources\BusinessHourExceptions\Pages\CreateBusinessHourException;
use App\Filament\Resources\BusinessHourExceptions\Pages\EditBusinessHourException;
use App\Models\BusinessHourException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers App\Filament\Resources\BusinessHourExceptions\
 * BusinessHourExceptionResource — "استثناءات العطل الرسمية": creation,
 * editing, validation, deletion, and access control (see
 * App\Policies\BusinessHourExceptionPolicy).
 */
class BusinessHourExceptionResourceTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
    }

    public function test_a_manager_can_create_a_fully_closed_holiday_exception(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateBusinessHourException::class)
            ->fillForm([
                'date' => now()->addMonth()->toDateString(),
                'is_closed' => true,
                'note' => 'Independence Day',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('business_hour_exceptions', ['is_closed' => true, 'note' => 'Independence Day']);
    }

    public function test_a_manager_can_create_a_custom_hours_exception(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateBusinessHourException::class)
            ->fillForm([
                'date' => now()->addMonth()->toDateString(),
                'is_closed' => false,
                'opens_at' => '12:00',
                'closes_at' => '16:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $exception = BusinessHourException::query()->latest('id')->first();
        $this->assertFalse($exception->is_closed);
        $this->assertNotNull($exception->opens_at);
    }

    public function test_opening_and_closing_times_are_required_when_not_fully_closed(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateBusinessHourException::class)
            ->fillForm([
                'date' => now()->addMonth()->toDateString(),
                'is_closed' => false,
                'opens_at' => null,
                'closes_at' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['opens_at' => 'required', 'closes_at' => 'required']);
    }

    public function test_date_is_required_and_unique(): void
    {
        $existing = BusinessHourException::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(CreateBusinessHourException::class)
            ->fillForm(['date' => null])
            ->call('create')
            ->assertHasFormErrors(['date' => 'required']);

        Livewire::actingAs($this->manager())
            ->test(CreateBusinessHourException::class)
            ->fillForm(['date' => $existing->date, 'is_closed' => true])
            ->call('create')
            ->assertHasFormErrors(['date' => 'unique']);
    }

    public function test_a_manager_can_edit_an_exception(): void
    {
        $exception = BusinessHourException::factory()->create(['note' => 'Old note']);

        Livewire::actingAs($this->manager())
            ->test(EditBusinessHourException::class, ['record' => $exception->getKey()])
            ->fillForm(['note' => 'Updated note'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated note', $exception->fresh()->note);
    }

    public function test_an_exception_can_be_deleted(): void
    {
        $exception = BusinessHourException::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(EditBusinessHourException::class, ['record' => $exception->getKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing('business_hour_exceptions', ['id' => $exception->id]);
    }

    public function test_super_admin_and_manager_can_view_the_exceptions_list(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager] as $role) {
            $admin = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($admin)->get(BusinessHourExceptionResource::getUrl('index'))->assertOk();
        }
    }

    public function test_kitchen_cashier_and_support_cannot_view_the_exceptions_list(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $admin = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($admin)->get(BusinessHourExceptionResource::getUrl('index'))->assertForbidden();
        }
    }
}
