# Public Menu & Catalog API (`/api/v1`)

Everything in this document is **public — no `Authorization` header required**.
Anyone can browse the menu, check delivery coverage, and preview a cart
before registering or logging in. See `docs/API_AUTH.md` for the
authenticated account endpoints and `docs/API_CONVENTIONS.md` for the
response envelope shared by every endpoint.

## Bilingual content

Category/product/option names (and category/product descriptions) are
stored as **two plain columns** — `name` (English, unchanged from every
other pre-existing use of it) and `name_ar` (nullable) — rather than a
single JSON translations column. This was the deliberate choice between the
two options considered:

- **Separate columns** (chosen): `name`/`name_ar`, `description`/`description_ar`.
  Nothing outside this API had to change — `App\Services\CartPricingService`,
  `App\Actions\CreateOrderAction`, and the `order_items`/`order_item_options`
  snapshot columns all keep reading `name` as a plain string, exactly as
  before.
- **JSON translations column** (not chosen): would have meant changing
  `name`'s cast to `array` on `Category`/`Product`, breaking every place
  that already reads it as a string.

The API always returns both languages as `{"en": "...", "ar": "..."}`,
falling back to the English value when no Arabic translation exists yet, so
the field is never `null`:

```json
"name": { "en": "Margherita Pizza", "ar": "بيتزا مارغريتا" }
```

Applies to: `Category.name`/`.description`, `Product.name`/`.description`,
`OptionGroup.name`, `OptionValue.name`. `DeliveryZone.name` and
`RestaurantSetting.restaurant_name` are **not** bilingual (out of scope —
operational/geographic labels and a single brand name, not "menu" content).

## Prices

Every price in every response uses the same shape:

```json
{ "amount_minor": 450, "formatted": "0.450", "currency": "JOD" }
```

- `amount_minor` — the raw integer stored in the database (smallest
  currency unit — fils for JOD).
- `formatted` — divided by the currency's ISO 4217 minor-unit exponent.
  JOD/KWD/BHD/OMR use 3 decimals, JPY/KRW use 0, everything else defaults
  to 2 (`App\Support\Money`).
- `currency` — the restaurant's configured `currency_code`
  (`RestaurantSetting.currency_code`, currently always the same value —
  single-restaurant deployment, no per-order currency).

## Caching

`App\Services\MenuCacheService` is a read-through cache (1 hour TTL) in
front of every endpoint below except `GET /restaurant/is-open` (depends on
the current wall-clock time, so it's always computed fresh — the settings/
business-hours rows it reads are still served from cache).

- Categories, delivery zones, restaurant settings, and business hours are
  cached as **plain arrays** (`Model::query()->get()->toArray()`) and
  reconstituted with `Model::hydrate()`/`newFromBuilder()` on read.
- Products are cached as **already-resolved API Resource arrays** — both
  the lean list shape and the full per-product detail shape are computed
  once per cache population and stored together, so building the Resource
  output never re-runs on a cache hit. The list/search/pagination endpoint
  filters this cached array in memory instead of querying the database
  once per `{category, search, page}` combination.
- **Why plain arrays, not cached model/Collection objects**: this
  project's cache store (`database`, see `config/cache.php`) has
  `serializable_classes => false` — a deliberate Laravel security default
  that blocks unserializing arbitrary PHP objects from cache (prevents
  gadget-chain attacks if `APP_KEY` ever leaks). Caching a raw Eloquent
  model/Collection would silently come back as a useless
  `__PHP_Incomplete_Class` on every cache *hit*. Rather than weaken that
  security setting, everything cached here is a plain, fully-resolved
  array. See `MenuCacheService`'s class docblock and its private
  `deepResolve()` helper (a `json_encode()`/`json_decode()` round trip,
  needed because `JsonResource::resolve()` only converts the *outer*
  resource — a nested resource returned as a value, like
  `ProductDetailResource`'s `category` or `option_groups`, is otherwise
  left as a live object).
- **Invalidation**: `App\Observers\MenuCacheObserver`, registered in
  `AppServiceProvider::boot()` for `Category`, `Product`, `OptionGroup`,
  `OptionValue`, `ProductOptionGroup`, `DeliveryZone`, `RestaurantSetting`,
  and `BusinessHour`. It hooks plain Eloquent model events
  (`saved`/`deleted`/`restored`), so it fires no matter what triggers the
  change — Filament, `tinker`, a seeder, a future admin API — without
  needing any Filament-specific code. **Known gap**: attaching/detaching a
  product's option groups via `BelongsToMany::sync()` (how a Filament
  relation manager would typically manage this) does not reliably fire
  Eloquent events on the pivot model (`ProductOptionGroup`) — only an
  explicit `Pivot::save()`/`delete()` does. The 1 hour TTL bounds how long
  that specific edge case could stay stale.
- Plain string cache keys, not `Cache::tags()` — the `database` store
  doesn't support tagging.

## Restaurant info

### `GET /api/v1/restaurant`

Cached. Restaurant profile:

```json
{
  "name": "Talabna Restaurant",
  "phone": "+962700000000",
  "email": "contact@talabna.example",
  "address": "Amman, Jordan",
  "latitude": "31.9539000",
  "longitude": "35.9106000",
  "currency": "JOD",
  "min_order_amount": { "amount_minor": 1000, "formatted": "1.000", "currency": "JOD" },
  "default_delivery_fee_amount": { "amount_minor": 500, "formatted": "0.500", "currency": "JOD" },
  "default_preparation_minutes": 25,
  "is_accepting_orders": true,
  "allows_scheduled_orders": false,
  "tax": { "enabled": false, "rate_percent": 0 }
}
```

### `GET /api/v1/restaurant/hours`

Cached. All 7 rows (`day_of_week` 0=Sunday..6=Saturday), ordered by day:

```json
[{ "day_of_week": 5, "day_name": "friday", "opens_at": null, "closes_at": null, "is_closed": true }, ...]
```

### `GET /api/v1/restaurant/is-open`

**Not cached** — depends on the current time. `{ "is_open": true }`. Uses
`App\Services\RestaurantAvailabilityService::isOpenNow()`, the exact same
rule `App\Actions\CreateOrderAction` uses to reject checkout when closed
(extracted out of that class so the two can never drift apart): must have
`is_accepting_orders = true` **and** today's `business_hours` row must
exist, not be `is_closed`, and the current time must fall within
`opens_at`/`closes_at`.

## Categories

### `GET /api/v1/categories`

Cached. Active categories only, ordered by `sort_order`. Not paginated —
a small, low-cardinality reference list.

```json
[{ "id": 1, "slug": "burgers", "name": { "en": "Burgers", "ar": "برجر" }, "description": { "en": "", "ar": "" }, "image_url": null }]
```

## Products

### `GET /api/v1/products`

Active products (`is_available = true`) belonging to an active category
only. **Filters** (query params, all optional):

| Param | Type | Meaning |
|---|---|---|
| `category_id` | integer | Only products in this category |
| `search` | string, max 100 | Case-insensitive substring match against **either** the English or Arabic name — this is also the "search products" endpoint; there is no separate route |
| `page` | integer, min 1 | Page number, default 1 |
| `per_page` | integer, 1–50 | Page size, default 20 |

Response (paginated):

```json
{
  "data": [
    { "id": 1, "slug": "classic-beef-burger", "category_id": 1, "name": {"en":"...","ar":"..."}, "price": {...}, "image_url": "..." }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 17, "last_page": 1 }
}
```

The list shape deliberately excludes `description` and option groups/values
— those only appear on the detail endpoint, so listing many products never
pulls in a large nested payload per item.

### `GET /api/v1/products/{product}`

Full detail: `category` (nested), bilingual `name`/`description`, `price`,
`images` (ordered, each `{url, is_primary}`), and `option_groups` — each
with the product-specific `is_required`/`min_select`/`max_select` (the
exact same effective-min/max fallback rule
`CartPricingService::assertOptionGroupSelections()` applies at pricing
time) and its nested, active-only `values` (each with `price_delta`).
`404` if the product doesn't exist, isn't available, or its category isn't
active.

## Delivery zones

### `GET /api/v1/delivery-zones`

Cached. Active zones only:

```json
[{ "id": 1, "name": "Nearby (0-3km)", "delivery_fee_amount": {...}, "min_order_amount": {...}, "estimated_minutes": 20 }]
```

### `POST /api/v1/delivery-zones/check`

A preliminary "can we deliver here" check — informational only, not
authoritative (the real check happens inside `CartPricingService` when a
cart is actually priced/ordered). Either:

| Field | Rules |
|---|---|
| `zone_id` | nullable, integer — check a zone the client already picked |
| `latitude` / `longitude` | required if `zone_id` is absent, numeric, valid range |

Coordinate checks match against each active zone's center point + radius
(`App\Support\Geo::distanceMeters()`, a Haversine calculation — zones are
naive circles, no polygon/map-matching). Response:

```json
{ "deliverable": true, "zone": { "id": 1, "name": "Nearby (0-3km)", "...": "..." } }
```

`zone` is `null` and `deliverable` is `false` if nothing matches.

## Cart preview

### `POST /api/v1/cart/preview`

Reuses `App\Services\CartPricingService` — the exact same computation
`App\Actions\CreateOrderAction` uses for real checkout — so a preview is
always exactly what an order would cost. **This is also the "validate
coupon" endpoint**: pass `coupon_code` and the same result either applies
the discount or fails with a coupon-specific error; there is no separate
coupon-check route.

| Field | Rules |
|---|---|
| `items` | required, array, min 1 |
| `items.*.product_id` | required, integer |
| `items.*.quantity` | required, integer, min 1 |
| `items.*.option_value_ids` | nullable, array of integers |
| `delivery_type` | required, `delivery` or `pickup` |
| `delivery_zone_id` | nullable, integer — required by the service if `delivery_type = delivery` |
| `coupon_code` | nullable, string, max 50 |

Only structural validation (types, required-ness) happens in the Form
Request; every business rule — product exists/available, option
selections valid, delivery zone valid, minimum order met, coupon valid —
is `CartPricingService`'s job, surfaced as a `422` with a stable
`errors.code` (e.g. `product_unavailable`, `coupon_expired`,
`min_order_not_met`) via `CartPricingException`, newly wired into
`bootstrap/app.php`'s exception envelope by this task (nothing rendered it
before now — no route had ever thrown it).

If the request happens to carry a valid bearer token, the user is resolved
(without requiring one) so a coupon's per-user usage limit can still be
checked; an anonymous preview simply skips that one check.

Response:

```json
{
  "items": [{
    "product_id": 1, "product_name": "Classic Beef Burger", "quantity": 2,
    "unit_base_price": {...}, "options": [{"option_value_id":4,"option_group_name":"Bread Type","option_value_name":"White Bun","price_delta":{...}}],
    "unit_options_total": {...}, "unit_total": {...}, "line_total": {...}
  }],
  "currency": "JOD",
  "items_subtotal": {...}, "options_total": {...},
  "coupon": { "id": 1, "code": "WELCOME10" },
  "discount_amount": {...},
  "delivery": { "type": "pickup", "zone_id": null, "fee_amount": {...} },
  "tax": { "applied": false, "amount": {...} },
  "grand_total": {...}
}
```

## Testing

`restaurant-backend/tests/Feature/Api/V1/Menu/`: `RestaurantTest`,
`CategoryTest`, `ProductTest`, `DeliveryZoneTest`, `CartPreviewTest`,
`MenuCacheTest` — cover every filter, pagination, the bilingual
fallback-to-English behavior, inactive-category/unavailable-product
exclusion (list and detail), nested option groups/values (including an
inactive value being excluded), coupon validation (valid/invalid/expired)
inside cart preview, delivery zone checks (by id and by coordinate, inside
and outside radius), `is-open`'s three independent conditions, and —
explicitly — that editing each underlying model (product, category,
option value, delivery zone, restaurant settings, business hours)
immediately invalidates that endpoint's cache rather than serving stale
data for the rest of the TTL window.
