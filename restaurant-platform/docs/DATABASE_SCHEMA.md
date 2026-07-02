# Database Schema

This documents the `restaurant-backend` database: a single-restaurant food
ordering schema. It lives entirely in `restaurant-backend` — per
`docs/ARCHITECTURE.md`, this is the platform's only database;
`restaurant-customer-app` never connects to it directly.

All money columns are integers in the smallest currency unit (fils/cents —
`RestaurantSetting.currency_code` records which currency, default `JOD`).
No database `ENUM` columns are used anywhere; status/type columns are plain
`string` columns backed by PHP enums (`app/Enums/`) via Eloquent casts.

## Enums (`app/Enums/`)

| Enum | Values | Used by |
|---|---|---|
| `UserRole` | `customer`, `admin` | `users.role` |
| `OrderStatus` | `pending`, `accepted`, `preparing`, `ready`, `out_for_delivery`, `delivered`, `cancelled`, `rejected` | `orders.status`, `order_status_histories.status` |
| `PaymentStatus` | `pending`, `paid`, `failed`, `refunded`, `partially_refunded` | `orders.payment_status`, `payments.status` |
| `PaymentMethod` | `cash_on_delivery`, `card_on_delivery` | `orders.payment_method`, `payments.method` |
| `DeliveryType` | `delivery`, `pickup` | `orders.delivery_type` |
| `OptionSelectionType` | `single`, `multiple` | `option_groups.selection_type` |
| `CouponType` | `percentage`, `fixed_amount` | `coupons.type` |
| `DevicePlatform` | `android`, `ios` | `device_tokens.platform` |

`OrderStatus` also carries the lifecycle graph (`canTransitionTo()`,
`isTerminal()`):

```
pending ──▶ accepted ──▶ preparing ──▶ ready ──┬─▶ out_for_delivery ──▶ delivered
   │             │            │               └─▶ delivered (pickup)
   ├─▶ rejected  └─▶ cancelled └─▶ cancelled       └─▶ cancelled
   └─▶ cancelled
```

`delivered`, `cancelled`, and `rejected` are terminal — no further
transitions are valid from them. Cancellation is allowed through `ready` but
not once an order is `out_for_delivery` (the order has already left the
building at that point); this is a judgment call made in the enum, not an
explicit requirement — revisit it if the business needs otherwise.

## Users & access control

### `users` (existing table, `role` column added)

The simplest-safe choice for admin vs. customer accounts: **one `users`
table** with a `role` string column (`UserRole`), not a separate
`AdminUser` table. Consequences of that choice, both enforced in code:

- `role` is **not** in `User`'s `#[Fillable]` list — it can never be set via
  mass assignment (e.g. a public registration endpoint), only by explicit
  code (`$user->role = UserRole::Admin`). This prevents privilege escalation
  through a customer-facing signup form.
- `App\Models\User` implements Filament's `FilamentUser` contract:
  `canAccessPanel()` returns `true` only for `role === UserRole::Admin`.
  Without this, **any** authenticated user — including a customer — could
  log into `/admin`, since Filament allows all authenticated users into a
  panel by default when no `FilamentUser` check is implemented.

### `customer_addresses`

A user's saved delivery addresses. `latitude`/`longitude` are simple
`decimal(10,7)` columns (no PostGIS/spatial types — "without advanced maps
yet" per the brief). `is_default` marks the address to preselect at
checkout. Orders don't have a hard foreign-key requirement on a specific
address surviving forever — see "Snapshotting" below.

## Restaurant configuration

### `restaurant_settings` — single row

One restaurant per deployment, so this is a deliberately singleton table:
always read/write it through `RestaurantSetting::current()`, which
`firstOrCreate`s the row with `id = 1`. Don't query the table directly or
create a second row.

`current()` spells out every default explicitly in the `firstOrCreate` call
rather than leaning on the migration's column defaults — Eloquent doesn't
re-read DB-applied defaults back into the in-memory model after an insert,
so a freshly created row's `currency_code`/`min_order_amount`/etc. would
otherwise sit `null` in PHP until the next full reload even though the
database itself has the right value. This was a real bug caught by
`CartPricingServiceTest`, not a hypothetical.

`is_tax_enabled` (boolean, default `false`) and `tax_rate_bps` (basis
points, e.g. `1500` = 15.00%, default `0`) were added for
`App\Services\CartPricingService` — basis points rather than a whole
percent or a decimal column, so the rate itself never needs a float any
more than a money amount does.

### `business_hours`

One row per weekday (`day_of_week`: 0 = Sunday .. 6 = Saturday, matching
Carbon's `dayOfWeek`), a single `opens_at`/`closes_at` shift per day, with
`unique(day_of_week)`. Split shifts (e.g. separate lunch/dinner windows)
would need a schema change (drop the uniqueness, add an ordering column) —
out of scope for now, noted here so it's not a silent limitation.

## Catalog

### `categories` → `products` → `product_images`

Standard menu hierarchy. Both `categories` and `products` have
**soft deletes** (`deleted_at`) — an admin can retire a category or product
without breaking history, because orders never depend on either still
existing (see snapshotting below). `products.category_id` is
`restrictOnDelete()`: a category with products can't be hard-deleted (force
`deleted()`), only soft-deleted.

`product_images.product_id` is `cascadeOnDelete()`: images are pure
children of a product with no independent meaning, and cascade only fires
on a hard delete anyway (soft-deleting a product leaves its images alone).

### `option_groups` → `option_values`, and `product_option_groups`

An **option group** is something like "Size" (`selection_type = single`,
radio-style) or "Extra Toppings" (`selection_type = multiple`,
checkbox-style). An **option value** is a choice within a group ("Large",
"Extra Cheese") with its own `price_delta_amount` (can be 0, or a surcharge).

Whether a group is *required* for a given product is deliberately **not**
stored on `option_groups` itself — the same "Size" group might be required
for a pizza but make no sense at all for a canned drink. Instead,
`product_option_groups` is a real Eloquent pivot model (not a bare array
pivot) carrying `is_required`, `min_select`, `max_select`, and `sort_order`
per product/group pairing:

```php
$product->optionGroups()   // BelongsToMany, ->using(ProductOptionGroup::class)
    ->withPivot(['is_required', 'min_select', 'max_select', 'sort_order']);
```

`min_select`/`max_select` are nullable **overrides** added when
`App\Services\CartPricingService` needed real min/max selection-count
validation, not just a single/multiple + required approximation. When
either is `null` (true for every row seeded before this existed — no
backfill needed), the service falls back to:

- `min_select ?? ($is_required ? 1 : 0)`
- `max_select ?? ($group->selection_type === Single ? 1 : unlimited)`

So a required single-select group still means "exactly 1" and an optional
multi-select group still means "0 or more" without every existing pivot row
needing an explicit value — only a product that genuinely needs, say, "pick
2 to 4 toppings" needs to set these explicitly.

Both `option_groups` and `option_values` are soft-deletable — a discontinued
topping doesn't need to disappear from historical orders (again: snapshots).

### Soft deletes: what got them and why

Soft deletes were added to `categories`, `products`, `option_groups`,
`option_values`, `delivery_zones`, and `coupons` — every "catalog/pricing
configuration" entity an admin might reasonably retire, all of which are
safe to remove without losing data integrity because **every order snapshots
what it actually needs at order time** (next section). Deliberately **not**
soft-deleted: `users`, `customer_addresses`, `business_hours`,
`restaurant_settings`, `product_images`, `product_option_groups`
(structural/pivot data, hard-delete is fine), and every transactional table
(`orders`, `order_items`, `order_item_options`, `order_status_histories`,
`payments`, `coupon_usages`, `device_tokens`) — financial/audit records are
never deleted, soft or otherwise.

## Snapshotting: why past orders never change

This is the most important invariant in the schema. `products.price_amount`,
`option_values.price_delta_amount`, a category's name, a coupon's terms —
all of these can change or disappear after an order was placed. If
`order_items`/`order_item_options` only stored foreign keys, editing a
product's price would silently rewrite every past order's totals. Instead:

- **`order_items`** stores `product_name` and `product_price_amount` as a
  copy taken at order time, alongside a *nullable* `product_id` (kept for
  convenience/reporting; can go `null` if the product is later hard-deleted,
  without touching the snapshot columns). `unit_total_amount` = snapshot
  product price + the snapshot price of its selected options;
  `line_total_amount` = `unit_total_amount * quantity`.
- **`order_item_options`** stores `option_group_name`, `option_value_name`,
  and `price_delta_amount` as a copy, alongside a nullable `option_value_id`.
- **`orders`** stores `delivery_address_line`, `delivery_city`,
  `delivery_latitude`, `delivery_longitude` as a copy of the
  `customer_address` used, alongside a nullable `customer_address_id`. This
  wasn't spelled out as explicitly as the product/option snapshots in the
  brief, but follows the same reasoning: editing or deleting a saved address
  later must not rewrite a past order's delivery details.
- **`orders`** also stores its own `subtotal_amount`, `discount_amount`,
  `delivery_fee_amount`, and `total_amount` — never recomputed from current
  catalog/coupon/zone state after the fact.

Net effect: you can delete every product, option, category, coupon, and
delivery zone referenced by a historical order, and that order's displayed
total, line items, and delivery address are completely unaffected.

## Orders

### `orders`

No soft deletes (financial/transactional — never deleted). Key columns:

- `order_number`: customer-facing, e.g. `ORD-2026-000001`. Generation and
  uniqueness are discussed below.
- `status`, `payment_status`, `payment_method`, `delivery_type`: enum-backed
  strings (see table above).
- `subtotal_amount`, `discount_amount`, `delivery_fee_amount`,
  `total_amount`: integers, `total_amount = subtotal - discount + delivery_fee`.
  No `tax_amount` column here yet — `App\Services\CartPricingService`
  computes tax (see "Cart pricing" below) but nothing persists an `Order`
  yet at all (deliberately out of scope so far); add `tax_amount` to this
  table in the same task that finally builds checkout/order creation.
- `coupon_id`, `delivery_zone_id`, `customer_address_id`: nullable,
  `nullOnDelete()` — an order keeps its own snapshot data regardless of
  whether these referenced rows still exist.
- `user_id`: `restrictOnDelete()` — a user with orders can't be hard-deleted,
  protecting order history from disappearing out from under a foreign key.
- `customer_notes`, `rejection_reason`, `cancellation_reason`,
  `expected_delivery_at`: exactly the fields called for in the brief.
  `rejection_reason`/`cancellation_reason` hold the *current* terminal
  reason; every intermediate transition (including these) is also recorded
  in `order_status_histories.note` for a full audit trail.
- No per-status timestamp columns (`accepted_at`, `delivered_at`, etc.) on
  `orders` itself — deliberately, to avoid two sources of truth.
  `order_status_histories` is authoritative for "when did each transition
  happen"; query it if you need that.

### Order number generation & uniqueness

`ORD-{year}-{000001}` numbers are generated by `Order::generateOrderNumber()`,
called automatically from a `creating` model event if `order_number` isn't
already set. It's backed by a tiny internal table, **`order_number_sequences`**
(`year` unique, `last_number`), incremented inside a transaction with
`lockForUpdate()`:

```php
DB::transaction(function () {
    $sequence = OrderNumberSequence::query()->lockForUpdate()->firstOrCreate(['year' => $year]);
    $sequence->increment('last_number');
    return sprintf('ORD-%d-%06d', $year, $sequence->last_number);
});
```

This was worth a dedicated table rather than e.g. counting existing orders
for the year: counting doesn't reserve a number, so two concurrent order
creations could compute the same "next" number and collide. The row lock
makes number reservation atomic, and `orders.order_number` still has a
`unique()` database constraint as a second line of defense. This table is
internal plumbing, not a requested entity — it exists solely to make the
"guarantee uniqueness" requirement actually true under concurrency, not just
usually true.

### `order_items` / `order_item_options`

Covered under "Snapshotting" above. No soft deletes.

### `order_status_histories` — append-only

Every status transition an order goes through, in order, forming the audit
trail. **No `updated_at` column exists**, and the model
(`App\Models\OrderStatusHistory`) throws `LogicException` from `updating()`
and `deleting()` model event hooks — this is enforced in code, not just a
naming convention:

```php
static::updating(fn () => throw new LogicException(...));
static::deleting(fn () => throw new LogicException(...));
```

`changed_by_user_id` is nullable (`nullOnDelete()`) to allow for
system/automated transitions with no specific admin attached.

### `payments`

One order can have more than one payment row over its lifetime (e.g. a cash
capture, later a partial refund), so this is `hasMany` from `Order`, not
`hasOne`. `transaction_reference` is unused in v1 (only
`cash_on_delivery`/`card_on_delivery`, both settled at the door) but is
there so a future real payment gateway doesn't need a schema change.

## Coupons

### `coupons`

`value`'s meaning depends on `type`: for `percentage` it's a whole percent
(0-100, not basis points — kept simple since nothing in the brief asked for
fractional-percent precision); for `fixed_amount` it's money in the smallest
currency unit. `max_discount_amount` caps a percentage discount so it can't
exceed a sane amount on a large order — this and `min_order_amount` weren't
explicitly requested but are standard, low-cost safety fields for a coupon
system; both are nullable and safe to ignore if unwanted. Soft-deletable
(see "Soft deletes" above).

### `coupon_usages`

One row per `(coupon_id, order_id)` pair (`unique` constraint) — records
that a specific coupon was actually redeemed on a specific order, with the
`discount_amount` actually granted (which may differ from a naive
percentage calculation once `max_discount_amount` is applied).
`per_user_limit`/`usage_limit` on `coupons` are enforced by counting rows
here (application-layer logic, not a DB constraint — a future Action/Service
task, not part of this schema task).

## Delivery

### `delivery_zones`

Naive circular zones: `latitude`/`longitude` center point +
`radius_meters`, matching the "no advanced maps yet" instruction — no
polygon geofencing. `delivery_fee_amount` and optional zone-specific
`min_order_amount` can override `restaurant_settings`' defaults. Soft-deletable.

## Cart pricing (domain layer, not a table)

`App\Services\CartPricingService::price()` is the single place that turns a
cart (product IDs, quantities, selected option value IDs, delivery
type/zone, an optional coupon code) into real amounts. It doesn't persist
anything — no `Order` is created here, this is pure calculation — but it's
documented here because it's the thing that actually *uses* most of the
schema above together.

- **Input**: `App\DataTransferObjects\Cart\CartPricingRequestData` /
  `CartItemInputData`. Neither carries a price field anywhere — the only
  things a caller can specify are IDs and a quantity. Every amount in the
  result comes from a fresh database lookup at call time
  (`Product::price_amount`, `OptionValue::price_delta_amount`,
  `DeliveryZone::delivery_fee_amount`, `Coupon`, `RestaurantSetting`), so a
  tampered client request has no channel to influence the total at all —
  this isn't a runtime check to bypass, it's structurally impossible given
  the DTO's shape.
- **Output**: `App\DataTransferObjects\Cart\CartPricingResultData`, with a
  `CartPricedItemData`/`CartPricedOptionData` per line — the same shape
  `order_items`/`order_item_options` would snapshot if this cart became a
  real order (see "Snapshotting" above). `itemsSubtotalAmount` matches what
  `orders.subtotal_amount` means; `optionsTotalAmount` is an additional
  transparency breakdown (how much of the subtotal came from add-ons), not
  a separate amount added on top.
- **Errors**: `App\Exceptions\CartPricingException`, translated at throw
  time via `lang/{locale}/cart.php` — `$exception->errorCode` gives calling
  code (a future controller) a stable value to branch on, while
  `$exception->getMessage()` is already localized for display. Validation
  is fail-fast (first violation wins), in this order: cart not empty → per
  item (quantity bounds, product exists/available, category active,
  duplicate/invalid option values, each attached option group's effective
  min/max selection count) → delivery zone resolution → the combined
  restaurant/zone minimum order check → coupon (exists/active, date range,
  global usage limit, per-user limit, its own minimum order) → tax.
- **Money math**: every amount is computed and returned as `int`. Discounts
  and tax involve a division (percentage/basis-point math), which is
  unavoidably a `float` *intermediate* in PHP — but the result is always
  `(int) round(...)` before it's stored in a DTO field or compared against
  anything, so no float ever crosses a method boundary or gets persisted.
  Tax is computed on `subtotal - discount` (goods, not delivery), gated by
  `RestaurantSetting::is_tax_enabled`.
- **A bug this caught**: `RestaurantSetting::current()` used to only set
  `restaurant_name` when lazily creating the singleton row, relying on the
  migration's column defaults for everything else. Eloquent doesn't re-read
  DB-applied defaults into the in-memory model after an `insert`, so
  `currency_code` (and every other default) came back `null` in PHP on a
  freshly created row despite being correct in the database. Fixed by
  spelling out every default explicitly in `current()`'s `firstOrCreate`
  call. Caught by `CartPricingServiceTest`, not by inspection.

## Notifications

### `device_tokens`

Push-notification tokens for the customer app (NativePHP push, once that's
wired up). `platform` is `DevicePlatform` (`android`/`ios`).
`token` is globally unique; `is_active` lets a token be disabled without
deleting the row (e.g. after a push failure) while keeping history.

## Indexes

Every foreign key has an index (via `constrained()`). Additional indexes
were added for the columns actually used to search/filter, per the brief:
`orders.status`, `orders.payment_status`, `orders.delivery_type`,
`orders.created_at` (reporting/date-range queries); `products.is_available`;
`categories.is_active`; `delivery_zones.is_active`; `coupons.is_active`;
`order_status_histories.status`; plus the natural unique indexes
(`orders.order_number`, `categories.slug`, `products.slug`, `coupons.code`,
`device_tokens.token`).

## What's deliberately not here

- `RestaurantSetting` now has `is_tax_enabled`/`tax_rate_bps` and cart
  pricing computes tax, but `orders.tax_amount` doesn't exist yet — nothing
  persists an `Order` at all so far (see "Cart pricing" above).
- No API endpoints, Form Requests, Policies, or checkout/order-creation
  business logic yet. `App\Services\CartPricingService` covers pricing and
  validation only — it never creates an `Order`. See
  `docs/API_CONVENTIONS.md` for the rules the eventual endpoints will
  follow, and `docs/PROJECT_STATE.md` for what's next.
- Coupon *redemption* (incrementing usage via a `CouponUsage` row) is not
  done by the pricing service — it only *checks* limits, it doesn't consume
  one, since pricing a cart (e.g. a live preview) must not have side
  effects. Recording a `CouponUsage` belongs to whatever Action eventually
  creates the `Order`.
