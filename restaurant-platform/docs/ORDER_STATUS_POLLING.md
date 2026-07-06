# Order Status Polling

How `restaurant-customer-app` finds out an order's status changed, today
(HTTP polling) and the seam prepared for tomorrow (WebSockets/Reverb). This
is the first, simplest working version — "ابدأ بالحل الأبسط" — not the
final architecture.

## `GET /api/v1/orders/{order}/status`

Requires `Authorization: Bearer <token>` and is scoped to the authenticated
user's own order (`App\Policies\OrderPolicy::view()` — same rule as
`show()`/`timeline()`; another user's order is a `403`, a missing one a
`404`).

Deliberately separate from `GET /orders/{order}` (full order: items,
payments, coupon, addresses) — **this is the endpoint a poll loop calls
repeatedly**, so its payload is intentionally the smallest thing that
answers "did anything change": current status, the status timeline, and
nothing else. `GET /orders/{order}/timeline` still exists unchanged (just
the raw history array, no current-status summary); `status` is the one to
poll against going forward.

### Query parameters

| Param | Rules | Meaning |
|---|---|---|
| `updated_since` | nullable, ISO 8601 datetime | The client's last-seen `updated_at`. If the order hasn't changed since, the response is the small `changed: false` shape below — no status-history query even runs. |

### Response shape

Nothing changed since `updated_since` (or `updated_since` was invalid —
this never happens since `updated_since` is validated, but conceptually
"unknown" behaves like "assume changed", see below):

```json
{
  "success": true,
  "message": "",
  "data": {
    "changed": false,
    "status": "preparing",
    "updated_at": "2026-07-06T17:29:01+00:00"
  }
}
```

First poll (no `updated_since`), or the order changed since:

```json
{
  "success": true,
  "message": "",
  "data": {
    "changed": true,
    "status": "preparing",
    "updated_at": "2026-07-06T17:29:01+00:00",
    "expected_delivery_at": "2026-07-06T18:19:01+00:00",
    "can_be_cancelled": false,
    "timeline": [
      { "status": "pending", "note": null, "created_at": "2026-07-06T17:10:00+00:00" },
      { "status": "accepted", "note": null, "created_at": "2026-07-06T17:15:00+00:00" },
      { "status": "preparing", "note": null, "created_at": "2026-07-06T17:29:01+00:00" }
    ]
  }
}
```

`updated_at` is `orders.updated_at` (Eloquent bumps this on every save —
a status transition, a rejection/cancellation reason, and even a payment
status change all touch it, since `App\Services\PaymentStatusUpdateService`
updates the order row too) — this is the **version marker** ("إرجاع
version أو updated_at لتمييز التغيير"): the client stores it and passes it
back as `updated_since` on the next poll.

### HTTP conditional GET (ETag / Last-Modified)

The response also carries standard `ETag` and `Last-Modified` headers,
both derived from the same `updated_at`. A client whose HTTP layer already
understands conditional requests gets this for free: send
`If-None-Match: <etag>` (or `If-Modified-Since`) and, if nothing changed,
the server returns a real `304 Not Modified` with an **empty body** — the
absolute lightest possible response, one HTTP round trip with no JSON
parsing at all.

This exists *alongside* `updated_since`, not instead of it, because they
suit different clients: `updated_since` only requires the caller to
remember a timestamp string and pass it back — no HTTP-cache-header
plumbing needed, which is the simpler thing to implement in a hand-rolled
mobile HTTP client. ETag/Last-Modified is the standard mechanism for
anything that already speaks HTTP caching. Use either, or both — the
smallest response wins either way.

**Known limitation**: `orders.updated_at` (and so the ETag) only has
whole-second precision (the column is an ordinary `timestamp`, not
`timestamp(3)`/`timestamp(6)`). Two genuine changes to the same order
within the same wall-clock second would produce the same ETag. This is
accepted as out of scope for the first version — order status changes are
minutes apart in practice (kitchen prep time), never sub-second — and
would only need a migration change (declaring fractional-second precision)
if it ever became a real problem.

### Rate limiting

A dedicated, more generous limiter — `order-status-poll`
(`App\Providers\AppServiceProvider`) — **30 requests/minute per
authenticated user** (not per-order, so polling several active orders at
once still shares one budget), returning `429 Too Many Requests` past that.
This is intentionally looser than a typical write endpoint (polling is this
endpoint's whole purpose) but still bounded against a runaway client.

## Client polling strategy

**Poll every 5–10 seconds while an order is in an active (non-terminal)
status** (`pending`/`accepted`/`preparing`/`ready`/`out_for_delivery`), and
**stop polling entirely** once the order reaches a terminal status
(`delivered`/`cancelled`/`rejected` — see `App\Enums\OrderStatus::isTerminal()`).
A 5–10s interval comfortably clears the 30/minute rate limit (worst case
12/minute at a 5s interval) with headroom for a retry after a dropped
request, and is fast enough that a customer notices a status change
promptly without the app hammering the API "every couple of seconds" — the
explicit thing this task's requirements call out to avoid
("لا تجعل العميل يطلب تفاصيل الطلب كاملة كل ثانيتين"), doubly so since this
endpoint's payload is already far smaller than the full order anyway.

On each poll: send `updated_since` = the last `updated_at` you saw (omit it
on the very first poll for an order). If `changed: false` comes back, do
nothing. If `changed: true`, update the UI from `status`/`timeline` and
remember the new `updated_at` for the next poll. Also worth doing, cheaply:
back off the interval (e.g. to 20–30s) once the order is `preparing` with a
distant `expected_delivery_at`, and poll faster (every 5s) as that time
approaches or once `ready`/`out_for_delivery` — the order's own timeline
already tells the client when to pay closer attention.

Also worth pairing with **push notifications**: this app already sends a
push for every status transition (`docs/NOTIFICATIONS.md`) — the poll loop
is the reliable fallback/confirmation path (works even if the push didn't
arrive, the app was killed, permissions were denied, etc.), not the primary
signal. A client that receives a push for order X can treat it as a hint to
poll that order immediately rather than waiting for the next interval.

## The abstraction for WebSockets / Laravel Reverb later

**Not installed now** — no `laravel/reverb` or `pusher/pusher-php-server`
package, no `config/broadcasting.php`, no `BROADCAST_CONNECTION` — on
purpose, per this task's own instruction not to bring in Reverb before it's
technically needed. What *is* in place is the seam that makes adding it
later a small, contained change rather than a rewrite:

- **`App\Http\Resources\OrderStatusResource`** is the single place "what
  does an order-status update look like" is defined — today it's the
  `status`/`updated_at`/`expected_delivery_at`/`can_be_cancelled`/`timeline`
  shape the polling endpoint returns. When real-time is added, a
  broadcastable event's `broadcastWith()` would call this exact same
  Resource, so the client's parsing code for "what's in an order-status
  update" never has to change — only where it comes from.
- **The order-lifecycle events already exist and already fire at the right
  moment**: `App\Events\{OrderAccepted, OrderRejected, OrderPreparing,
  OrderReady, OrderOutForDelivery, OrderDelivered, OrderCancelled}` (see
  `docs/NOTIFICATIONS.md`), dispatched by
  `App\Services\OrderStatusTransitionService` only after its transaction
  commits. Making any of them broadcast-ready later is adding
  `implements ShouldBroadcastNow`, a `broadcastOn()` returning
  `new PrivateChannel("orders.{$this->order->id}")`, and a `broadcastWith()`
  delegating to `OrderStatusResource` — no change to where/when the event
  fires.
- **Concretely, when that day comes**: run
  `php artisan install:broadcasting` (installs Reverb + `config/broadcasting.php`
  + the `channels.php` routes file), add a `Broadcast::channel('orders.{orderId}', ...)`
  authorization callback mirroring `OrderPolicy::view()`, add the three
  pieces above to the events that should broadcast, and have the customer
  app subscribe via Laravel Echo instead of running its poll loop (or keep
  a slow poll as a fallback for reconnects — the two aren't mutually
  exclusive).

## Tests

`tests/Feature/Api/V1/Order/OrderStatusTest.php` covers: the full payload
shape, that items/payments/addresses are never included, `updated_at` as
the version marker, ownership (403)/auth (401)/not-found (404),
`updated_since` in the future vs. the past vs. omitted, invalid
`updated_since` (422), the `ETag`/`Last-Modified` headers being present, a
matching `If-None-Match` producing a true empty-bodied `304`, a stale one
after a real status change producing `200` again, and the 30/minute rate
limit.
