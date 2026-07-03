# Admin Orders Screen (FilamentPHP v5)

`restaurant-backend`'s Filament admin panel now has a fast, mostly-read-only
screen for running the order lifecycle:
`App\Filament\Resources\Orders\OrderResource`, under the pre-existing
**Orders** navigation group.

## Why a Resource, not a custom Page

Filament Resources give a list + detail pair with authorization,
filters/search, and routing for free — exactly the shape this screen needs
(a list, and a detail view). The only thing a generic Resource *would*
normally add — a `form()` and `create`/`edit` routes/CRUD — is exactly what
this task explicitly forbids ("منع تعديل العناصر والأسعار بعد إنشاء الطلب
من خلال CRUD عادي"). So `OrderResource` has **no `form()` method, no
`create` route, no `edit` route** at all — not hidden buttons, an absent
capability. `App\Policies\OrderPolicy::create()/update()/delete()` all
unconditionally return `false` too, as defense in depth on top of the
missing routes.

An order's items/prices are fixed forever at checkout
(`App\Actions\CreateOrderAction`); its `status` only ever moves through
`App\Services\OrderStatusTransitionService` — see "Status actions" below.

## List page

`App\Filament\Resources\Orders\Tables\OrdersTable`:

- **Columns**: order number (bold, searchable), customer name (searchable),
  a hidden-by-default but still searchable phone column (see "The phone
  number gap" below), delivery type (badge), total (formatted via
  `App\Support\Money`), payment method (badge), payment status (badge),
  order status (badge), "placed" as a relative time (`->since()`), expected
  delivery/pickup time, and a dedicated overdue indicator (see "Visually
  highlighting new/late orders").
- **Search**: order number, customer name, and phone all use Filament's
  ordinary per-column `->searchable()` (including the `user.phone`
  relationship column, dot-notation search — the same pattern already
  proven working for `product.name` on `ProductImagesTable`), which
  Filament automatically folds into one global search box — no custom
  search query needed.
- **Filters**: status (multi-select), payment status (multi-select),
  payment method, delivery type, and a "date placed" range filter (two
  `DatePicker`s in one `Filter`, querying `created_at` inclusively on both
  ends).
- **Sort**: `->defaultSort('created_at', 'desc')` — newest first.
- **Auto-refresh**: `->poll('15s')` — Filament's own built-in table
  polling (`Filament\Tables\Table\Concerns\CanPollRecords`), not a custom
  JS timer. 15 seconds is frequent enough to feel live for a kitchen
  screen without hammering the database on every open tab.
- **Row actions**: every status action from `OrderStatusActions` (see
  below) plus a plain `ViewAction`, grouped into one `ActionGroup` so the
  row stays compact — this is what makes the screen "fast": accepting or
  marking an order ready never requires opening the detail page.

### Visually highlighting new/late orders

Two purely Filament-native mechanisms, chosen specifically because they're
*proven* to render correctly, unlike the alternative considered and
rejected below:

- **New** ("needs action"): the `status` badge's own color.
  `OrderStatus::Pending->getColor()` is `warning` (amber) — distinctly
  different from every other status — so a new, unactioned order is
  visually obvious in the status column without any extra markup.
- **Late**: a dedicated computed `is_late` `IconColumn`
  (`expected_delivery_at` is in the past *and* the order isn't already in a
  terminal status) rendering a red exclamation-triangle, blank otherwise.

**Rejected approach**: `Table::recordClasses()` (whole-row background
highlighting) — Filament fully supports the API, but its own docs example
uses classes the *developer* is expected to style themselves
(`vendor/filament/tables/docs/01-overview.md`, "Custom row classes"). This
app's admin panel renders through Filament's own separately-bundled CSS
(`public/css/filament/filament/*`), not through this project's own
Tailwind v4/Vite build (`resources/css/app.css`) — so arbitrary Tailwind
utility classes returned from `recordClasses()` would need a whole extra
custom-theme asset pipeline to actually render with any color at all, and
there was no reliable way to verify that in this environment. Badges/icons
with Filament's own built-in `->color()` are backed by CSS Filament always
ships, already proven throughout this project (every existing badge/icon
column). Same reasoning as the sound-alerts decision below: don't ship a
visual feature on a rendering path that can't be verified to actually work.

## Status actions

`App\Filament\Resources\Orders\Actions\OrderStatusActions` — one static
factory per lifecycle move (`accept`, `reject`, `startPreparing`,
`markReady`, `outForDelivery`, `markDelivered`, `cancel`), shared between
`OrdersTable`'s row actions and `Pages\ViewOrder`'s header actions, so the
two surfaces can never drift apart.

Every action:

- **Only ever calls `App\Services\OrderStatusTransitionService::transition()`** —
  never assigns `$record->status` directly. Nothing new was built for
  authorization or the lifecycle graph; this task is purely wiring the
  already-existing service to the admin panel for the first time (see
  `docs/ORDER_LIFECYCLE.md`, previously explicit that "no Filament action
  calls this service yet").
- **`->visible()` mirrors the exact same policy ability the service checks
  internally**, via `Gate::allows('manage'|'cancelAtReadyStage'|
  'cancelAtOutForDeliveryStage', $order)` — not a hand-rolled duplicate of
  the rule. This keeps the button's visibility and the service's real
  enforcement from ever silently drifting apart, and was proven not to be
  "just a hidden button": a test forces the `cancel` action on an
  `out_for_delivery` order as a Kitchen-role user (for whom the button is
  hidden) directly through Livewire, and the transition still fails
  server-side inside the service.
- **`->requiresConfirmation()`** on all seven — this is what satisfies
  "أضف confirmations للإجراءات الحساسة": every one of these changes an
  order's lifecycle state, not just the obviously-destructive ones
  (reject/cancel).
- **`accept`** collects `estimated_preparation_minutes` (required, ≥ 1) in
  its confirmation modal, passed straight through as
  `TransitionOrderStatusData::estimatedPreparationMinutes`.
- **`reject`**/**`cancel`** collect a required `reason` `Textarea` —
  `OrderStatusTransitionException('reason_required')` was already enforced
  service-side; the form field just means the admin sees this as a normal
  validation error, not a raw exception message.
- Any `OrderStatusTransitionException` the service still throws (e.g. a
  genuine race — someone else transitioned the order between page load and
  click) is caught and shown as a danger `Notification`, not an
  unhandled 500.

## Order detail page

`App\Filament\Resources\Orders\Schemas\OrderInfolist` — entirely
`TextEntry`/`RepeatableEntry` components, which are read-only by
construction (there is no form for a CRUD edit to even target):

- **Order summary**: number, status, type, placed/expected times, payment
  method/status.
- **Customer**: name, email, phone.
- **Delivery address** (snapshot columns, `orders.delivery_address_*` —
  never the live `CustomerAddress` — only shown for `delivery` orders).
- **Items**: one row per `OrderItem` (product name, quantity, line total),
  each with a nested, only-shown-if-non-empty `RepeatableEntry` of its
  selected options (group: value, + extra price if non-zero).
- **Financial breakdown**: subtotal, discount, delivery fee, tax, total —
  all through `App\Support\Money`. **Tax is derived, not stored**: `orders`
  has no `tax_amount` column (see `App\DataTransferObjects\Cart\
  CartPricingResultData`'s docblock — tax was always folded into
  `total_amount` at checkout without a separate persisted line). Shown here
  as `total - subtotal + discount - delivery_fee`, which reconciles exactly
  by construction since that's the same arithmetic `CreateOrderAction` used
  to compute `total_amount` in the first place — clearly labeled "derived"
  rather than presented as a real stored figure.
- **Notes**: customer notes always; rejection/cancellation reason only
  when the order is actually in that terminal status.
- **Status timeline**: every `OrderStatusHistory` row (status, note,
  who changed it — "System" when `changed_by_user_id` is null).
- **Payments**: every `Payment` row (method, status, amount, reference,
  paid-at, notes) — a section, not a single set of fields, since the
  relation is a `HasMany` (e.g. a failed attempt followed by a successful
  one).
- **Header actions**: every `OrderStatusActions` action, plus **Print**
  (opens the receipt in a new tab — see below).

## Printable receipt

`GET /admin/orders/{order}/print` → `App\Http\Controllers\Admin\
OrderPrintController` → `resources/views/admin/orders/print.blade.php`.

Deliberately a **plain Blade view with no Filament/app layout at all** —
registered in `routes/web.php`, not through Filament's own panel routing —
because a receipt printed on 80mm thermal paper must not carry a
sidebar/topbar along with it. `@page { size: 80mm auto; margin: 0; }` plus
a monospace font gives a real thermal-printer-shaped layout; the page's own
"Print" button just calls `window.print()` — the browser's native print
dialog handles device selection, exactly as requested ("دون دمج خدمة طباعة
خارجية الآن"). Authorization reuses `OrderPolicy::view()` — the same
ability the Filament view page and the customer-facing API already check —
rather than inventing a separate rule for "who can print a receipt" (which
is also why a customer can print their own receipt, but not anyone else's).

## Sound alerts: deliberately not implemented

The task explicitly asked for this to be optional/disableable **and**
explicitly said not to implement it if it can't be done reliably. Reliably
playing a *new-order-only* sound in a server-rendered Livewire admin panel
would need: client-side JS tracking which orders the browser has already
alerted on (survives across `->poll()` refreshes without re-playing on
every tick), and working around browser autoplay restrictions (most
browsers block audio until the page has seen a user gesture, which a
kitchen screen left open unattended may never get again after the first
load). None of this can be verified working in this environment — no
browser to actually load the page in and confirm a sound plays exactly
once per new order, not zero times, not on every poll. Skipped rather than
shipped as an unverified guess; the visual "new" cue (the Pending status
badge's `warning` color) covers the same need today.

## The phone number gap

Neither `users` nor `customer_addresses` stored a phone number anywhere
before this task — a real, pre-existing gap the search/customer-info
requirements surfaced. Added a nullable `users.phone` column (additive
migration), wired into the existing profile-update endpoint
(`App\Http\Requests\Api\V1\Profile\UpdateProfileRequest`,
`App\Http\Resources\UserResource`) so customers can actually set it — an
unreachable column would otherwise stay permanently empty. No uniqueness
constraint: this is a contact number for staff to call about an order, not
an authentication factor.

## Testing

`tests/Feature/Filament/{OrderResourceAccessTest, OrderStatusActionsTest}`,
`tests/Feature/Admin/OrderPrintControllerTest`, plus the extended
`tests/Unit/Policies/OrderPolicyTest` — the access matrix (every admin role
can list/view; a customer only their own order; no `create`/`edit` route
exists at all), search (order number/customer name/phone), every filter,
default sort, every status action's happy path, its exact permission tier
(mirroring `docs/ORDER_LIFECYCLE.md`'s table precisely — including the
pickup-only `ready → delivered` shortcut and the two "special permission"
cancellation tiers), validation (`estimated_preparation_minutes`/`reason`
required), a terminal order offering no further actions, a forced
Livewire call proving the visibility check isn't the only enforcement, and
the print page's content/authorization (including a customer being able to
print their own receipt but not anyone else's).
