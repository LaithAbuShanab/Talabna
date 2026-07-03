<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\CouponType;
use App\Enums\UserRole;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers App\Filament\Resources\Coupons\CouponResource: creation, editing,
 * validation, the type-dependent `value` field (percentage vs. fixed
 * amount, see App\Enums\CouponType), optional category/product
 * restriction, usage statistics, soft delete/restore, and access control
 * (see App\Policies\CouponPolicy).
 */
class CouponResourceTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
    }

    public function test_a_manager_can_create_a_percentage_coupon(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateCoupon::class)
            ->fillForm([
                'code' => 'save10',
                'type' => CouponType::Percentage->value,
                'value' => 10,
                'max_discount_amount' => 5,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::query()->where('code', 'SAVE10')->firstOrFail();
        $this->assertSame(CouponType::Percentage, $coupon->type);
        $this->assertSame(10, $coupon->value);
        $this->assertSame(5000, $coupon->max_discount_amount);
    }

    public function test_a_manager_can_create_a_fixed_amount_coupon(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateCoupon::class)
            ->fillForm([
                'code' => 'FLAT2',
                'type' => CouponType::FixedAmount->value,
                'value' => 2.5,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::query()->where('code', 'FLAT2')->firstOrFail();
        $this->assertSame(CouponType::FixedAmount, $coupon->type);
        $this->assertSame(2500, $coupon->value);
    }

    public function test_percentage_value_cannot_exceed_100(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateCoupon::class)
            ->fillForm([
                'code' => 'TOOBIG',
                'type' => CouponType::Percentage->value,
                'value' => 150,
            ])
            ->call('create')
            ->assertHasFormErrors(['value' => 'max']);
    }

    public function test_code_name_and_type_are_required(): void
    {
        Livewire::actingAs($this->manager())
            ->test(CreateCoupon::class)
            ->fillForm(['code' => '', 'type' => null, 'value' => null])
            ->call('create')
            ->assertHasFormErrors(['code' => 'required', 'type' => 'required', 'value' => 'required']);
    }

    public function test_code_must_be_unique(): void
    {
        Coupon::factory()->create(['code' => 'DUPLICATE']);

        Livewire::actingAs($this->manager())
            ->test(CreateCoupon::class)
            ->fillForm([
                'code' => 'DUPLICATE',
                'type' => CouponType::Percentage->value,
                'value' => 10,
            ])
            ->call('create')
            ->assertHasFormErrors(['code' => 'unique']);
    }

    public function test_a_manager_can_restrict_a_coupon_to_categories_and_products(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(CreateCoupon::class)
            ->fillForm([
                'code' => 'PIZZA20',
                'type' => CouponType::Percentage->value,
                'value' => 20,
                'categories' => [$category->id],
                'products' => [$product->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::query()->where('code', 'PIZZA20')->firstOrFail();
        $this->assertTrue($coupon->isRestricted());
        $this->assertTrue($coupon->categories()->where('categories.id', $category->id)->exists());
        $this->assertTrue($coupon->products()->where('products.id', $product->id)->exists());
    }

    public function test_a_coupon_with_no_categories_or_products_is_unrestricted(): void
    {
        $coupon = Coupon::factory()->create();

        $this->assertFalse($coupon->isRestricted());
    }

    public function test_a_manager_can_edit_a_coupon(): void
    {
        $coupon = Coupon::factory()->create(['is_active' => true]);

        Livewire::actingAs($this->manager())
            ->test(EditCoupon::class, ['record' => $coupon->getKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($coupon->fresh()->is_active);
    }

    public function test_edit_page_shows_usage_statistics(): void
    {
        $coupon = Coupon::factory()->create();
        CouponUsage::factory()->create(['coupon_id' => $coupon->id, 'discount_amount' => 500]);
        CouponUsage::factory()->create(['coupon_id' => $coupon->id, 'discount_amount' => 300]);

        $response = $this->actingAs($this->manager())->get(CouponResource::getUrl('edit', ['record' => $coupon]));

        $response->assertOk()->assertSee('2');
    }

    public function test_a_coupon_can_be_soft_deleted_and_restored(): void
    {
        $coupon = Coupon::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(EditCoupon::class, ['record' => $coupon->getKey()])
            ->callAction('delete');

        $this->assertSoftDeleted('coupons', ['id' => $coupon->id]);

        Livewire::actingAs($this->manager())
            ->test(EditCoupon::class, ['record' => $coupon->getKey()])
            ->callAction('restore');

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'deleted_at' => null]);
    }

    public function test_a_coupon_never_offers_a_force_delete_action(): void
    {
        $coupon = Coupon::factory()->create();

        Livewire::actingAs($this->manager())
            ->test(EditCoupon::class, ['record' => $coupon->getKey()])
            ->assertActionDoesNotExist('forceDelete');
    }

    public function test_super_admin_and_manager_can_view_the_coupons_list(): void
    {
        foreach ([UserRole::SuperAdmin, UserRole::Manager] as $role) {
            $admin = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($admin)->get(CouponResource::getUrl('index'))->assertOk();
        }
    }

    public function test_kitchen_cashier_and_support_cannot_view_the_coupons_list(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Cashier, UserRole::Support] as $role) {
            $admin = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($admin)->get(CouponResource::getUrl('index'))->assertForbidden();
        }
    }
}
