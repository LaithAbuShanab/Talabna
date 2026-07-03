# Admin Coupons (FilamentPHP v5)

`App\Filament\Resources\Coupons\CouponResource`, under the **Promotions**
navigation group — full CRUD (soft-delete-only, like every other
catalog/promotions resource in this project), covering every field the
existing `coupons` table already had (code, type, value, max discount,
min order, usage limits, date range, active flag) plus two genuinely new
capabilities this task added: optional category/product restriction and
usage statistics.

## The `value` field's type-dependent behavior — and a real bug found

`value`'s meaning depends on `type` (`App\Enums\CouponType`): a plain
0–100 for `percentage`, a money amount for `fixed_amount`. One `TextInput`
switches its label/suffix/prefix/step/max-value and how it converts
to/from the stored integer, all keyed off the live `type` `Select`, using
Filament's `Get` utility.

**Real bug, caught by testing (not by reading the code):** every
`Get`-based comparison was originally written as
`$get('type') === CouponType::Percentage->value` (comparing against the
enum's *raw string* `->value`). A probe test proved this **always
evaluated false** — `$get('type')` on a `Select::make('type')->options
(CouponType::class)` actually resolves to the **enum case itself**
(`CouponType::Percentage`, an object), not its backing string. Comparing
an object to a string with `===` is never true, so every conditional
(the `max_discount_amount` field's visibility, and `value`'s own
`dehydrateStateUsing`) silently took the wrong branch — a fixed-amount
coupon's `2.5` got saved as the *integer* `2` (via a stray `(int)` cast on
the still-major-unit float) instead of being converted to `2500` minor
units, and a percentage coupon's `max_discount_amount` got silently
dropped (the field read as hidden, so Filament didn't dehydrate it).
Fixed by comparing against the enum case directly
(`$get('type') === CouponType::FixedAmount`, no `->value`). Confirmed via
`Log::debug` dumping the actual runtime value before settling on the root
cause, rather than guessing.

## Optional category/product restriction

"فئات أو منتجات محددة اختياريًا": two new plain pivot tables,
`coupon_categories`/`coupon_products` (`Coupon::categories()`/`products()`,
`BelongsToMany`), manageable as ordinary multi-selects on the form. A
coupon with rows in neither table is unrestricted — the original,
cart-wide behavior, unchanged for every coupon that predates this task.

**This is enforced in `App\Services\CartPricingService::applyCoupon()`,
not just recorded for display** — per the explicit choice to build this
all the way through rather than leave it inert:

- If a coupon is restricted and the cart has **no** item whose product or
  category matches, pricing throws a new `coupon_not_applicable` error
  (`lang/{en,ar}/cart.php`) — same fail-fast pattern as every other
  coupon rule.
- If it **does** match, the discount is computed over **only the eligible
  items' subtotal**, not the whole cart — "10% off pizzas" must never
  quietly discount the drinks in the same order. `App\DataTransferObjects\
  Cart\CartPricedItemData` gained a `categoryId` field (additive; nothing
  else that reads this DTO needed to change) so the eligibility check has
  a product ID *and* a category ID to match against per line.
- An **unrestricted** coupon's behavior is completely unchanged — proven
  by the full pre-existing coupon test suite passing untouched, plus a
  dedicated test.

## Usage statistics

"إحصائيات الاستخدام" — a read-only "Usage statistics" section on the edit
form (`Placeholder` components, not editable fields): times used
(`$coupon->usages()->count()`) and total discount given
(`$coupon->usages()->sum('discount_amount')`, formatted through
`App\Support\Money`). Hidden on the create form (`visible(fn (?Coupon
$record) => $record !== null)`) since a brand-new coupon has no usage yet.

## Testing

`tests/Feature/Filament/CouponResourceTest` (creation for both coupon
types, validation — required fields, unique code, percentage value capped
at 100 — category/product restriction actually attaching, usage stats
rendering, soft delete/restore, no force-delete, access control) plus
five new cases added to the existing `tests/Feature/Services/
CartPricingServiceTest`: a restricted coupon applying via an eligible
category, via an eligible product, discounting only the eligible items'
subtotal in a mixed cart, being rejected when nothing in the cart is
eligible, and an unrestricted coupon continuing to work exactly as before.
