<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `role` and `is_active` are deliberately excluded from User's
 * #[Fillable(...)] (a privilege-escalation guard against the customer
 * API), which means the default Filament CreateRecord/EditRecord mass-
 * assignment save path would silently drop both — these tests exist
 * specifically to prove App\Filament\Resources\Users\Pages\{CreateUser,
 * EditUser}'s forceFill() override actually persists them, not just that
 * the form submits without error.
 */
class UserResourceCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_admin_persists_role_and_active_status(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        Livewire::actingAs($superAdmin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'New Kitchen Hire',
                'email' => 'new-kitchen@example.com',
                'role' => UserRole::Kitchen->value,
                'is_active' => true,
                'password' => 'Password123!',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::query()->where('email', 'new-kitchen@example.com')->firstOrFail();
        $this->assertSame(UserRole::Kitchen, $created->role);
        $this->assertTrue($created->is_active);
    }

    public function test_creating_an_admin_records_an_audit_log_entry(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        Livewire::actingAs($superAdmin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Audited Hire',
                'email' => 'audited-hire@example.com',
                'role' => UserRole::Support->value,
                'is_active' => true,
                'password' => 'Password123!',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $superAdmin->id,
            'action' => 'user.created',
        ]);
    }

    public function test_editing_an_admins_role_persists_and_is_audited(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $target = User::factory()->kitchen()->create();

        Livewire::actingAs($superAdmin)
            ->test(EditUser::class, ['record' => $target->getKey()])
            ->fillForm(['role' => UserRole::Manager->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(UserRole::Manager, $target->fresh()->role);
        $this->assertDatabaseHas('admin_activity_logs', [
            'user_id' => $superAdmin->id,
            'action' => 'user.updated',
        ]);
    }

    public function test_deactivating_an_admin_persists(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $target = User::factory()->cashier()->create(['is_active' => true]);

        Livewire::actingAs($superAdmin)
            ->test(EditUser::class, ['record' => $target->getKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_a_manager_cannot_actually_update_a_user_even_by_calling_save_directly(): void
    {
        $manager = User::factory()->manager()->create();
        $target = User::factory()->kitchen()->create();

        Livewire::actingAs($manager)
            ->test(EditUser::class, ['record' => $target->getKey()])
            ->assertForbidden();
    }

    public function test_deleting_an_admin_records_an_audit_log_entry(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $target = User::factory()->support()->create();

        Livewire::actingAs($superAdmin)
            ->test(EditUser::class, ['record' => $target->getKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertSame(1, AdminActivityLog::query()->where('action', 'user.deleted')->count());
    }
}
