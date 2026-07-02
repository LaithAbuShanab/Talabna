# Order Endpoints (`/api/v1/orders`)

Everything in this document requires `Authorization: Bearer <token>`
(`auth:sanctum`) and is scoped to **the authenticated user's own orders
only** — viewing, cancelling, reordering, or reviewing another user's order
returns `403`. See `docs/API_AUTH.md` for how to obtain a token,
`docs/API_MENU.md` for the public menu/cart-preview endpoints these build
on, and `docs/ORDER_LIFECYCLE.md` for the full status-transition graph and
authorization rules.

## Response envelope

Standard platform envelope (`docs/API_CONVENTIONS.md`):
`{success, message, data}` / `{success, message, errors}`.

## Authorization model

- `App\Policies\OrderPolicy::view()` — bare ownership
  (`$user->id === $order->user_id`) — gates `show`, `timeline`,
  `reorder-preview`, and `review`.
- `App\Policies\OrderPolicy::cancelAsCustomer()` — ownership **and** the
  order must still be `pending` or `accepted` — gates `cancel`, enforced by
  `App\Services\OrderStatusTransitionService` (the single place
  `orders.status` is ever changed), not by the Form Request. This is a
  deliberate split: "not your order" is a `403`; "your order, but it's past
  the point where you can cancel it" is a `422` with a specific error code
  (`unauthorized_transition`) — the two are never conflated.
- **Internal/administrative fields are never exposed**: no
  `changed_by_user_id`, no soft/raw model internals — `App\Http\Resources\OrderStatusHistoryResource`
  only ever returns `{status, note, created_at}`.

## `GET /api/v1/orders`

The authenticated user's own orders, **newest first**
(`ORDER BY created_at DESC`), paginated.

| Query param | Rules | Meaning |
|---|---|---|
| `status` | nullable, one of `pending/accepted/preparing/ready/out_for_delivery/delivered/cancelled/rejected` | Filter to a single status |
| `page` | nullable, integer, min 1 | Page number, default 1 |
| `per_page` | nullable, integer, 1–50 | Page size, default 20 |

Response:

```json
{
  "data": [ { "id": 11, "order_number": "ORD-2026-000011", "status": "pending", "total_amount": 1350, "...": "..." } ],
  "meta": { "current_page": 1, "per_page": 20, "total": 2, "last_page": 1 }
}
```

The list shape deliberately **omits** `items`, `status_histories`,
`payments`, and `review` (kept lean for pagination) — those only appear on
`GET /orders/{order}`. `coupon` is still eager-loaded on the list query
since `OrderResource.applied_coupon_code` always reads it, avoiding an N+1.

## `POST /api/v1/orders`

Creates an order. Every business rule (restaurant open, cart/coupon/option
validity, delivery-address ownership) is enforced by the existing
`App\Actions\CreateOrderAction` / `App\Services\CartPricingService` — this
endpoint only translates the request into `CreateOrderData`; nothing new
was added to that logic.

**Requires the `Idempotency-Key` header** (any non-empty string, e.g. a
UUID generated once per checkout attempt client-side):

```
Idempotency-Key: 3fa85f64-5717-4562-b3fc-2c963f66afa6
```

Missing it fails validation exactly like a missing body field
(`errors.idempotency_key`). Repeating the **same** `(user, Idempotency-Key)`
pair — even with a different body — always returns the **original** order
unchanged, never creates a second one; a different key creates a genuinely
new order. See `App\Http\Requests\Api\V1\Order\CreateOrderRequest::prepareForValidation()`
and the existing `orders` table's `unique(user_id, idempotency_key)`
constraint.

| Field | Rules |
|---|---|
| `items` | required, array, min 1 |
| `items.*.product_id` | required, integer |
| `items.*.quantity` | required, integer, min 1 |
| `items.*.option_value_ids` | nullable, array of integers |
| `delivery_type` | required, `delivery` or `pickup` |
| `payment_method` | required, `cash_on_delivery` or `card_on_delivery` |
| `delivery_zone_id` | nullable, integer — required by the service if `delivery_type = delivery` |
| `customer_address_id` | nullable, integer — required by the service if `delivery_type = delivery`, must belong to the user |
| `coupon_code` | nullable, string, max 50 |
| `customer_notes` | nullable, string, max 1000 |

`201` with the full `OrderResource` on success. Business-rule failures
(`restaurant_closed`, `delivery_address_required`, `delivery_address_invalid`
from `OrderCreationException`; any `CartPricingException` code — see
`docs/API_MENU.md`) return `422` with a stable `errors.code` — both
exceptions were wired into `bootstrap/app.php`'s envelope by this task
(neither had ever been reachable over HTTP before now).

## `GET /api/v1/orders/{order}`

Full order detail: status, delivery/payment method, **payment status**
(`payment_status`, plus the full `payments` breakdown), **estimated time**
(`expected_delivery_at`), money breakdown, delivery address (built from the
order's own snapshot columns, never the live `CustomerAddress` — see
`docs/DATABASE_SCHEMA.md` "Snapshotting"), `can_be_cancelled` (mirrors
`OrderStatus::isCustomerCancellable()`, so a client can enable/disable a
cancel button without guessing), the full **status timeline**
(`status_histories`), `items` (with their selected options), and `review`
(`null` until one is submitted).

## `GET /api/v1/orders/{order}/timeline`

Just the status timeline (oldest first), for a client that wants to poll
order progress without the full order payload:

```json
[
  { "status": "pending", "note": null, "created_at": "2026-07-02T18:38:10+00:00" },
  { "status": "accepted", "note": null, "created_at": "2026-07-02T18:40:00+00:00" }
]
```

## `POST /api/v1/orders/{order}/cancel`

Cancels the order **per the same policy admins are bound by**
(`docs/ORDER_LIFECYCLE.md`): a customer may only cancel their own order
while it's still `pending` or `accepted` — once it's `preparing` or later,
only an admin (with escalating "special permission" at `ready`/
`out_for_delivery`) can cancel it.

| Field | Rules |
|---|---|
| `reason` | required, string, max 500 |

- `403` — not your order (`CancelOrderRequest::authorize()`).
- `403`, `errors.code: "unauthorized_transition"` — your order, but it's
  already past `pending`/`accepted` — cancelling isn't a permission you
  have at this stage.
- `422`, `errors.code: "terminal_state"` — already `delivered`/`cancelled`/`rejected`.

`unauthorized_transition` and `unauthorized_special_permission` render as
`403` (fundamentally "you don't have permission for this action," even
though the order itself is valid and belongs to you); every other
`OrderStatusTransitionException` code (`terminal_state`,
`invalid_transition`, `reason_required`, `pickup_only_transition`) renders
as `422` instead (a state/input problem, not a permission one) — see
`bootstrap/app.php`.

## `POST /api/v1/orders/{order}/reorder-preview`

Recomputes a past order's items at **current** prices/availability and
returns a cart proposal — **no new order is ever created**. Internally
rebuilds the same `CartPricingRequestData` `POST /cart/preview` takes
(`docs/API_MENU.md`) from the order's own `items`/`options` (same
delivery type/zone as the original order) and calls the exact same
`CartPricingService`, so a reorder preview is never a different
computation than a fresh cart preview.

| Field | Rules |
|---|---|
| `coupon_code` | nullable, string, max 50 — apply a *new* coupon to the preview; the order's original coupon (if any) is never silently reapplied |

```json
{
  "source_order": { "id": 11, "order_number": "ORD-2026-000011" },
  "preview": { "items": [...], "grand_total": {...}, "...": "..." },
  "unavailable_items": ["Discontinued Item"]
}
```

- Items whose `product_id` is `null` (the product was hard-deleted since)
  are silently dropped and listed by name in `unavailable_items` — there's
  nothing left to re-price.
- Any other pricing failure among the *surviving* items (still exists but
  now unavailable, an option no longer valid, etc.) fails the **whole**
  preview with the same `CartPricingException` `POST /cart/preview` would
  raise — this endpoint doesn't invent a separate partial-recovery scheme;
  it's exactly "call `/cart/preview` with this order's old items," so it
  behaves exactly the same way.

## `POST /api/v1/orders/{order}/review`

Submits a post-delivery rating. **No review/rating schema existed before
this task** — added as `order_reviews` (one row per order, unique on
`order_id`, `rating` 1–5, optional `comment`).

| Field | Rules |
|---|---|
| `rating` | required, integer, between 1 and 5 |
| `comment` | nullable, string, max 1000 |

- `422`, `errors.code: "order_not_delivered"` — the order hasn't reached
  `delivered` yet.
- `422`, `errors.code: "already_reviewed"` — one review per order, checked
  both in the controller and by the database's unique constraint (defense
  in depth against a race between two concurrent submissions).
- `201` with `{rating, comment, created_at}` on success. The review is
  also embedded in `GET /orders/{order}`'s `review` field from then on.

## Testing

`restaurant-backend/tests/Feature/Api/V1/Order/`:
`OrderCreationTest` (idempotency — same key/different body returns the
original order unchanged, different key creates a new one — validation,
restaurant-closed/product-unavailable business errors, auth required),
`OrderListTest` (ownership scoping, newest-first ordering, status filter,
pagination, lean payload), `OrderShowTest` (ownership 403/404, full detail
shape, timeline oldest-first), `OrderCancelTest` (customer can cancel
pending/accepted, cannot cancel preparing+/terminal, reason required,
ownership, status-history recorded), `OrderReorderPreviewTest` (no order
created, current price used instead of the snapshot, deleted-product items
flagged as unavailable, a fresh coupon can be applied, a still-unavailable
item fails the whole preview, ownership), `OrderReviewTest` (must be
delivered, one review per order, rating bounds, ownership, embedded in
order detail).
