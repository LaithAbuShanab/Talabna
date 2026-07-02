<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\DataTransferObjects\Cart\CartItemInputData;
use App\DataTransferObjects\Cart\CartPricingRequestData;
use App\Enums\CouponType;
use App\Enums\DeliveryType;
use App\Enums\OptionSelectionType;
use App\Exceptions\CartPricingException;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\DeliveryZone;
use App\Models\OptionGroup;
use App\Models\OptionValue;
use App\Models\Product;
use App\Models\RestaurantSetting;
use App\Models\User;
use App\Services\CartPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test, not tests/Unit, per docs/TESTING.md's own rule: anything
 * that needs the database is a Feature test. CartPricingService always
 * hits the database (that's the whole point — see docs/API_CONVENTIONS.md
 * "never trust client prices"), so it can't be a true isolated unit test.
 */
class CartPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartPricingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CartPricingService;
    }

    private function makeAvailableProduct(array $attributes = []): Product
    {
        $category = Category::factory()->create(['is_active' => true]);

        return Product::factory()->create([
            'category_id' => $category->id,
            'is_available' => true,
            ...$attributes,
        ]);
    }

    private function attachOptionGroup(
        Product $product,
        OptionGroup $group,
        bool $isRequired,
        ?int $minSelect = null,
        ?int $maxSelect = null,
    ): void {
        $product->optionGroups()->attach($group->id, [
            'is_required' => $isRequired,
            'min_select' => $minSelect,
            'max_select' => $maxSelect,
            'sort_order' => 0,
        ]);
    }

    // --- Basic pricing -------------------------------------------------

    public function test_it_prices_a_simple_product_with_no_options(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 450]);

        $result = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 2)],
            deliveryType: DeliveryType::Pickup,
        ));

        $this->assertSame(900, $result->itemsSubtotalAmount);
        $this->assertSame(0, $result->optionsTotalAmount);
        $this->assertSame(900, $result->grandTotalAmount);
        $this->assertSame(0, $result->deliveryFeeAmount);
        $this->assertCount(1, $result->items);
        $this->assertSame($product->id, $result->items[0]->productId);
    }

    public function test_it_prices_a_product_with_selected_options(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 500]);
        $group = OptionGroup::factory()->create(['selection_type' => OptionSelectionType::Single]);
        $value = OptionValue::factory()->create(['option_group_id' => $group->id, 'price_delta_amount' => 150]);
        $this->attachOptionGroup($product, $group, isRequired: false);

        $result = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 3, optionValueIds: [$value->id])],
            deliveryType: DeliveryType::Pickup,
        ));

        $this->assertSame(650, $result->items[0]->unitTotalAmount);
        $this->assertSame(1950, $result->items[0]->lineTotalAmount);
        $this->assertSame(450, $result->optionsTotalAmount);
        $this->assertSame(1950, $result->itemsSubtotalAmount);
        $this->assertSame($value->id, $result->items[0]->options[0]->optionValueId);
        $this->assertSame($group->name, $result->items[0]->options[0]->optionGroupName);
    }

    // --- Required option group -------------------------------------------------

    public function test_it_throws_when_a_required_option_group_has_no_selection(): void
    {
        $product = $this->makeAvailableProduct();
        $group = OptionGroup::factory()->create(['selection_type' => OptionSelectionType::Single]);
        OptionValue::factory()->create(['option_group_id' => $group->id]);
        $this->attachOptionGroup($product, $group, isRequired: true);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Pickup,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('option_group_required', $e->errorCode);
        }
    }

    public function test_it_throws_when_option_group_selection_exceeds_max(): void
    {
        $product = $this->makeAvailableProduct();
        $group = OptionGroup::factory()->create(['selection_type' => OptionSelectionType::Multiple]);
        $values = OptionValue::factory()->count(3)->create(['option_group_id' => $group->id]);
        $this->attachOptionGroup($product, $group, isRequired: false, maxSelect: 2);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1, optionValueIds: $values->pluck('id')->all())],
                deliveryType: DeliveryType::Pickup,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('option_group_max_exceeded', $e->errorCode);
        }
    }

    public function test_it_throws_for_duplicate_option_value_selection(): void
    {
        $product = $this->makeAvailableProduct();
        $group = OptionGroup::factory()->create(['selection_type' => OptionSelectionType::Multiple]);
        $value = OptionValue::factory()->create(['option_group_id' => $group->id]);
        $this->attachOptionGroup($product, $group, isRequired: false);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1, optionValueIds: [$value->id, $value->id])],
                deliveryType: DeliveryType::Pickup,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('option_value_duplicate', $e->errorCode);
        }
    }

    public function test_it_throws_for_an_option_value_that_does_not_belong_to_the_product(): void
    {
        $product = $this->makeAvailableProduct();
        $unrelatedValue = OptionValue::factory()->create();

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1, optionValueIds: [$unrelatedValue->id])],
                deliveryType: DeliveryType::Pickup,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('option_value_invalid', $e->errorCode);
        }
    }

    public function test_it_throws_for_an_inactive_option_value(): void
    {
        $product = $this->makeAvailableProduct();
        $group = OptionGroup::factory()->create();
        $value = OptionValue::factory()->create(['option_group_id' => $group->id, 'is_active' => false]);
        $this->attachOptionGroup($product, $group, isRequired: false);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1, optionValueIds: [$value->id])],
                deliveryType: DeliveryType::Pickup,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('option_value_invalid', $e->errorCode);
        }
    }

    // --- Product / category availability -------------------------------------------------

    public function test_it_throws_for_an_unavailable_product(): void
    {
        $product = $this->makeAvailableProduct(['is_available' => false]);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Pickup,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('product_unavailable', $e->errorCode);
        }
    }

    public function test_it_throws_for_a_product_in_an_inactive_category(): void
    {
        $category = Category::factory()->create(['is_active' => false]);
        $product = Product::factory()->create(['category_id' => $category->id, 'is_available' => true]);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Pickup,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('category_inactive', $e->errorCode);
        }
    }

    public function test_it_throws_for_a_nonexistent_product(): void
    {
        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: 999999, quantity: 1)],
                deliveryType: DeliveryType::Pickup,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('product_not_found', $e->errorCode);
        }
    }

    // --- Quantity -------------------------------------------------

    public function test_it_throws_for_a_quantity_below_the_minimum(): void
    {
        $product = $this->makeAvailableProduct();

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 0)],
                deliveryType: DeliveryType::Pickup,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('quantity_invalid', $e->errorCode);
        }
    }

    public function test_it_throws_for_a_quantity_above_the_maximum(): void
    {
        $product = $this->makeAvailableProduct();

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 51)],
                deliveryType: DeliveryType::Pickup,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('quantity_invalid', $e->errorCode);
        }
    }

    // --- Cart-level -------------------------------------------------

    public function test_it_throws_when_the_cart_is_empty(): void
    {
        try {
            $this->service->price(new CartPricingRequestData(items: [], deliveryType: DeliveryType::Pickup));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('cart_empty', $e->errorCode);
        }
    }

    // --- Delivery -------------------------------------------------

    public function test_it_throws_for_an_invalid_delivery_zone(): void
    {
        $product = $this->makeAvailableProduct();

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Delivery,
                deliveryZoneId: 999999,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('delivery_zone_invalid', $e->errorCode);
        }
    }

    public function test_it_throws_for_an_inactive_delivery_zone(): void
    {
        $product = $this->makeAvailableProduct();
        $zone = DeliveryZone::factory()->create(['is_active' => false]);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Delivery,
                deliveryZoneId: $zone->id,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('delivery_zone_invalid', $e->errorCode);
        }
    }

    public function test_it_throws_when_delivery_type_is_delivery_but_no_zone_given(): void
    {
        $product = $this->makeAvailableProduct();

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Delivery,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('delivery_zone_required', $e->errorCode);
        }
    }

    public function test_delivery_fee_is_zero_for_pickup(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);

        $result = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 1)],
            deliveryType: DeliveryType::Pickup,
        ));

        $this->assertSame(0, $result->deliveryFeeAmount);
    }

    public function test_delivery_fee_uses_the_zones_fee_for_delivery(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);
        $zone = DeliveryZone::factory()->create(['delivery_fee_amount' => 350, 'is_active' => true, 'min_order_amount' => null]);

        $result = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 1)],
            deliveryType: DeliveryType::Delivery,
            deliveryZoneId: $zone->id,
        ));

        $this->assertSame(350, $result->deliveryFeeAmount);
        $this->assertSame(1000 + 350, $result->grandTotalAmount);
    }

    // --- Minimum order -------------------------------------------------

    public function test_it_throws_when_the_restaurants_minimum_order_amount_is_not_met(): void
    {
        RestaurantSetting::factory()->create(['min_order_amount' => 2000]);
        $product = $this->makeAvailableProduct(['price_amount' => 500]);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Pickup,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('min_order_not_met', $e->errorCode);
        }
    }

    public function test_it_throws_when_the_delivery_zones_minimum_order_amount_is_not_met(): void
    {
        RestaurantSetting::factory()->create(['min_order_amount' => 0]);
        $product = $this->makeAvailableProduct(['price_amount' => 500]);
        $zone = DeliveryZone::factory()->create(['min_order_amount' => 2000, 'is_active' => true]);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Delivery,
                deliveryZoneId: $zone->id,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('min_order_not_met', $e->errorCode);
        }
    }

    // --- Coupons -------------------------------------------------

    public function test_it_applies_a_valid_percentage_coupon(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);
        Coupon::factory()->create([
            'code' => 'TEN',
            'type' => CouponType::Percentage,
            'value' => 10,
            'max_discount_amount' => null,
            'min_order_amount' => null,
        ]);

        $result = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 1)],
            deliveryType: DeliveryType::Pickup,
            couponCode: 'ten',
        ));

        $this->assertSame(100, $result->discountAmount);
        $this->assertSame('TEN', $result->appliedCouponCode);
        $this->assertSame(900, $result->grandTotalAmount);
    }

    public function test_percentage_coupon_discount_is_capped_by_max_discount_amount(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 10000]);
        Coupon::factory()->create([
            'code' => 'CAPPED',
            'type' => CouponType::Percentage,
            'value' => 50,
            'max_discount_amount' => 300,
            'min_order_amount' => null,
        ]);

        $result = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 1)],
            deliveryType: DeliveryType::Pickup,
            couponCode: 'CAPPED',
        ));

        $this->assertSame(300, $result->discountAmount);
    }

    public function test_it_applies_a_valid_fixed_amount_coupon(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);
        Coupon::factory()->create([
            'code' => 'FIXED5',
            'type' => CouponType::FixedAmount,
            'value' => 500,
            'min_order_amount' => null,
        ]);

        $result = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 1)],
            deliveryType: DeliveryType::Pickup,
            couponCode: 'FIXED5',
        ));

        $this->assertSame(500, $result->discountAmount);
        $this->assertSame(500, $result->grandTotalAmount);
    }

    public function test_fixed_amount_coupon_never_discounts_more_than_the_subtotal(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 200]);
        Coupon::factory()->create([
            'code' => 'BIGFIXED',
            'type' => CouponType::FixedAmount,
            'value' => 5000,
            'min_order_amount' => null,
        ]);

        $result = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 1)],
            deliveryType: DeliveryType::Pickup,
            couponCode: 'BIGFIXED',
        ));

        $this->assertSame(200, $result->discountAmount);
        $this->assertSame(0, $result->grandTotalAmount);
    }

    public function test_it_throws_for_an_expired_coupon(): void
    {
        $product = $this->makeAvailableProduct();
        Coupon::factory()->expired()->create(['code' => 'OLD']);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Pickup,
                couponCode: 'OLD',
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('coupon_expired', $e->errorCode);
        }
    }

    public function test_it_throws_for_a_coupon_that_has_not_started_yet(): void
    {
        $product = $this->makeAvailableProduct();
        Coupon::factory()->create(['code' => 'FUTURE', 'starts_at' => now()->addWeek()]);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Pickup,
                couponCode: 'FUTURE',
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('coupon_expired', $e->errorCode);
        }
    }

    public function test_it_throws_for_a_nonexistent_coupon_code(): void
    {
        $product = $this->makeAvailableProduct();

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Pickup,
                couponCode: 'DOES-NOT-EXIST',
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('coupon_invalid', $e->errorCode);
        }
    }

    public function test_it_throws_for_an_inactive_coupon(): void
    {
        $product = $this->makeAvailableProduct();
        Coupon::factory()->create(['code' => 'OFF', 'is_active' => false]);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Pickup,
                couponCode: 'OFF',
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('coupon_invalid', $e->errorCode);
        }
    }

    public function test_it_throws_when_the_cart_is_below_the_coupons_minimum_order(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 100]);
        Coupon::factory()->create(['code' => 'BIGSPEND', 'min_order_amount' => 5000]);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Pickup,
                couponCode: 'BIGSPEND',
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('coupon_min_order_not_met', $e->errorCode);
        }
    }

    public function test_it_throws_when_the_coupons_global_usage_limit_is_reached(): void
    {
        $product = $this->makeAvailableProduct();
        $coupon = Coupon::factory()->create(['code' => 'LIMITED', 'usage_limit' => 1, 'min_order_amount' => null]);
        CouponUsage::factory()->create(['coupon_id' => $coupon->id]);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Pickup,
                couponCode: 'LIMITED',
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('coupon_usage_limit_reached', $e->errorCode);
        }
    }

    public function test_it_throws_when_the_users_per_user_coupon_limit_is_reached(): void
    {
        $product = $this->makeAvailableProduct();
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'ONCE', 'per_user_limit' => 1, 'min_order_amount' => null]);
        CouponUsage::factory()->create(['coupon_id' => $coupon->id, 'user_id' => $user->id]);

        try {
            $this->service->price(new CartPricingRequestData(
                items: [new CartItemInputData(productId: $product->id, quantity: 1)],
                deliveryType: DeliveryType::Pickup,
                couponCode: 'ONCE',
                userId: $user->id,
            ));
            $this->fail('Expected CartPricingException was not thrown.');
        } catch (CartPricingException $e) {
            $this->assertSame('coupon_per_user_limit_reached', $e->errorCode);
        }
    }

    public function test_per_user_coupon_limit_is_not_checked_without_a_user_id(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);
        $coupon = Coupon::factory()->create(['code' => 'GUEST', 'per_user_limit' => 1, 'min_order_amount' => null, 'type' => CouponType::FixedAmount, 'value' => 100]);

        $result = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 1)],
            deliveryType: DeliveryType::Pickup,
            couponCode: 'GUEST',
            userId: null,
        ));

        $this->assertSame(100, $result->discountAmount);
    }

    // --- Tax -------------------------------------------------

    public function test_it_computes_tax_when_enabled_in_restaurant_settings(): void
    {
        RestaurantSetting::factory()->create(['is_tax_enabled' => true, 'tax_rate_bps' => 1000, 'min_order_amount' => 0]);
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);

        $result = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 1)],
            deliveryType: DeliveryType::Pickup,
        ));

        $this->assertTrue($result->isTaxApplied);
        $this->assertSame(100, $result->taxAmount);
        $this->assertSame(1100, $result->grandTotalAmount);
    }

    public function test_it_does_not_compute_tax_when_disabled(): void
    {
        RestaurantSetting::factory()->create(['is_tax_enabled' => false, 'tax_rate_bps' => 1600, 'min_order_amount' => 0]);
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);

        $result = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 1)],
            deliveryType: DeliveryType::Pickup,
        ));

        $this->assertFalse($result->isTaxApplied);
        $this->assertSame(0, $result->taxAmount);
    }

    public function test_tax_is_computed_on_the_discounted_amount_not_the_raw_subtotal(): void
    {
        RestaurantSetting::factory()->create(['is_tax_enabled' => true, 'tax_rate_bps' => 1000, 'min_order_amount' => 0]);
        $product = $this->makeAvailableProduct(['price_amount' => 1000]);
        Coupon::factory()->create(['code' => 'HALF', 'type' => CouponType::Percentage, 'value' => 50, 'max_discount_amount' => null, 'min_order_amount' => null]);

        $result = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 1)],
            deliveryType: DeliveryType::Pickup,
            couponCode: 'HALF',
        ));

        // subtotal 1000, discount 500 -> taxable 500 -> tax 10% = 50
        $this->assertSame(500, $result->discountAmount);
        $this->assertSame(50, $result->taxAmount);
        $this->assertSame(550, $result->grandTotalAmount);
    }

    // --- Security: client-supplied prices are impossible -------------------------------------------------

    public function test_pricing_always_uses_the_products_current_database_price_never_a_client_supplied_one(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 500]);

        $first = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 1)],
            deliveryType: DeliveryType::Pickup,
        ));
        $this->assertSame(500, $first->itemsSubtotalAmount);

        // Nothing in CartItemInputData/CartPricingRequestData can carry a
        // price (see the DTOs) — the only way the total changes is if the
        // database price changes, which we now do and re-price to prove it.
        $product->update(['price_amount' => 999]);

        $second = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 1)],
            deliveryType: DeliveryType::Pickup,
        ));
        $this->assertSame(999, $second->itemsSubtotalAmount);
    }

    // --- Determinism & types -------------------------------------------------

    public function test_pricing_is_deterministic_for_identical_input(): void
    {
        $product = $this->makeAvailableProduct(['price_amount' => 777]);
        $request = new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 3)],
            deliveryType: DeliveryType::Pickup,
        );

        $first = $this->service->price($request);
        $second = $this->service->price($request);

        $this->assertEquals($first, $second);
    }

    public function test_all_money_fields_are_integers(): void
    {
        RestaurantSetting::factory()->create(['is_tax_enabled' => true, 'tax_rate_bps' => 1234, 'min_order_amount' => 0]);
        $product = $this->makeAvailableProduct(['price_amount' => 333]);
        $zone = DeliveryZone::factory()->create(['delivery_fee_amount' => 111, 'is_active' => true, 'min_order_amount' => null]);
        Coupon::factory()->create(['code' => 'INTS', 'type' => CouponType::Percentage, 'value' => 7, 'max_discount_amount' => null, 'min_order_amount' => null]);

        $result = $this->service->price(new CartPricingRequestData(
            items: [new CartItemInputData(productId: $product->id, quantity: 3)],
            deliveryType: DeliveryType::Delivery,
            deliveryZoneId: $zone->id,
            couponCode: 'INTS',
        ));

        $this->assertIsInt($result->itemsSubtotalAmount);
        $this->assertIsInt($result->optionsTotalAmount);
        $this->assertIsInt($result->discountAmount);
        $this->assertIsInt($result->deliveryFeeAmount);
        $this->assertIsInt($result->taxAmount);
        $this->assertIsInt($result->grandTotalAmount);
    }
}
