# Order Lifecycle

The single source of truth for how an order's status may change after
creation. Every rule described here is enforced in one place —
`App\Services\OrderStatusTransitionService::transition()` — not scattered
across controllers, Filament actions, or jobs. Nothing outside that class
should ever write to `orders.status` directly.

## The state graph

```
                    ┌──────────┐
        ┌──────────▶│ accepted │──────────┐
        │           └────┬─────┘          │
        │                │                │
        │                ▼                │
        │          ┌───────────┐          │
        │          │ preparing │──────────┤
        │           └────┬─────┘          │
        │                │                │
        │                ▼                │
        │            ┌───────┐            │
        │      ┌────▶│ ready │───────┐    │
        │      │      └───┬───┘      │    │
        │      │          │          │    │
        │      │          ▼          │    │
        │      │  ┌─────────────────┐│    │
        │      │  │ out_for_delivery││    │
        │      │  └────────┬────────┘│    │
        │      │           │         │    │
   ┌────┴───┐  │           ▼         ▼    ▼
   │pending │  │      ┌───────────┐ ┌───────────┐
   └────┬───┘  │      │ delivered │ │ cancelled │
        │      │      └───────────┘ └───────────┘
        │      │ (pickup only, direct
        │      │  ready → delivered)
        ▼      │
   ┌──────────┐│
   │ rejected │◀── (from pending only)
   └──────────┘
```

`delivered`, `cancelled`, and `rejected` are **terminal** — no status ever
moves again once it reaches one of these
(`OrderStatus::isTerminal()` / `OrderStatusTransitionService` both refuse).

## Allowed transitions

| From | To | Who | Notes |
|---|---|---|---|
| `pending` | `accepted` | admin (`manage`) | may set an estimated prep/delivery time |
| `pending` | `rejected` | admin (`manage`) | reason **required** |
| `pending` | `cancelled` | customer (own order) or admin | reason **required** |
| `accepted` | `preparing` | admin (`manage`) | — |
| `accepted` | `cancelled` | customer (own order) or admin | reason **required** — last point a customer can cancel |
| `preparing` | `ready` | admin (`manage`) | — |
| `preparing` | `cancelled` | admin only | reason **required** — customer's window has closed |
| `ready` | `out_for_delivery` | admin (`manage`) | delivery orders |
| `ready` | `delivered` | admin (`manage`) | **pickup orders only** — a delivery order must go through `out_for_delivery` first |
| `ready` | `cancelled` | admin **with special permission** | reason **required** — food may already be made |
| `out_for_delivery` | `delivered` | admin (`manage`) | — |
| `out_for_delivery` | `cancelled` | admin **with very special permission** | reason **required** — highest-risk cancellation in the lifecycle |

Any pair not in this table (e.g. `pending → preparing`, `accepted → ready`,
`preparing → delivered`, anything `→ pending`) is structurally invalid and
rejected with `invalid_transition`, regardless of who's asking.

## Who can do what

Only two roles exist right now (`App\Enums\UserRole`: `customer`, `admin` —
see `docs/DATABASE_SCHEMA.md`), so today every admin-only rule below
resolves to "any admin." The abilities are still named and checked
separately in `App\Policies\OrderPolicy`, so tightening any one of them
later (e.g. if a `manager` role is ever introduced) is a one-method change,
not a rewrite of the transition service:

- **`cancelAsCustomer`** — the ordering customer, only while the order is
  `pending` or `accepted` (`OrderStatus::isCustomerCancellable()`). This is
  the entire customer-facing surface of this system: a customer can never
  accept, reject, prepare, mark ready, dispatch, or mark delivered — only
  cancel their own order, and only before the kitchen starts on it.
- **`manage`** — the baseline ability to run the forward lifecycle
  (accept/reject/prepare/ready/dispatch/deliver) and to cancel up through
  `preparing`.
- **`cancelAtReadyStage`** — "special permission" for cancelling a `ready`
  order.
- **`cancelAtOutForDeliveryStage`** — "very special permission" for
  cancelling an `out_for_delivery` order.

A `null` actor represents a system/automated transition (e.g. a future
scheduled job) and skips all authorization checks — used sparingly and only
from trusted, non-user-facing code paths.

## What every transition enforces

`OrderStatusTransitionService::transition(Order $order, TransitionOrderStatusData $data)`:

1. **Row lock**: re-fetches the order with `lockForUpdate()` inside a
   `DB::transaction()`, so it always validates against the *current*
   database state, never a stale in-memory copy — two concurrent attempts
   on the same order can't both win against an outdated status.
2. **Terminal check**: refuses if the order is already `delivered`,
   `cancelled`, or `rejected`.
3. **Graph check**: refuses if `from → to` isn't one of the pairs above
   (`OrderStatus::canTransitionTo()`).
4. **Pickup-only check**: refuses `ready → delivered` unless
   `delivery_type = pickup`.
5. **Authorization**: via `OrderPolicy`, as described above.
6. **Reason check**: `rejected` and `cancelled` both require a non-empty
   `reason` — stored as `orders.rejection_reason` /
   `orders.cancellation_reason` respectively (the *current* terminal
   reason; every transition's reason is also kept in
   `order_status_histories.note` for the full history).
7. **Estimated time**: only meaningful on `→ accepted`. Pass either
   `estimatedPreparationMinutes` (computed as `now() + minutes`) or an
   explicit `expectedDeliveryAt` (which takes precedence); either way it's
   stored on `orders.expected_delivery_at`.
8. **Persistence**, all inside the same transaction: update `orders.status`
   (+ the reason/expected-time fields above) → insert one
   `order_status_histories` row recording `from_status`, `status` (the new
   status), `note` (the reason, if any), `metadata` (arbitrary optional
   JSON context), and `changed_by_user_id` (the actor, or `null` for a
   system transition).
9. **Event**: `App\Events\OrderStatusChanged` is dispatched *after* the
   transaction returns successfully — never from inside it, so a listener
   can never observe a transition that later rolls back.
   `App\Listeners\SendOrderStatusChangedNotification` (queued) sends a push
   notification through the same `App\Contracts\PushNotifier` seam used by
   order creation.

## Example

```php
$service = app(\App\Services\OrderStatusTransitionService::class);

$order = $service->transition($order, new \App\DataTransferObjects\Order\TransitionOrderStatusData(
    to: \App\Enums\OrderStatus::Accepted,
    actor: $admin,
    estimatedPreparationMinutes: 25,
));
```

## What's deliberately not here

- No HTTP endpoint or Filament action calls this service yet — it's the use
  case itself, callable directly, following the same pattern as
  `App\Actions\CreateOrderAction` (see `docs/DATABASE_SCHEMA.md` "Order
  creation"). Wiring it to `/admin` and a customer-facing "cancel my order"
  endpoint is future work.
- No scheduled/automated transitions exist yet (e.g. auto-cancelling a
  `pending` order after N minutes with no admin action) — the `null` actor
  path exists for this, but nothing calls it today.
- No granular roles beyond `customer`/`admin` — see "Who can do what" above
  for how that would slot in without touching this service.
