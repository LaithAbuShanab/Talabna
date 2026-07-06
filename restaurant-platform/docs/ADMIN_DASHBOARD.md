# Admin Dashboard (FilamentPHP v5)

`restaurant-backend`'s Filament admin panel now has a real Dashboard
(`App\Filament\Pages\Dashboard`, replacing Filament's default one) with
seven widgets under `app/Filament/Widgets/`, backed by one aggregation
service (`App\Services\DashboardMetricsService`).

## Revenue recognition: `delivered`, not `paid` — the required decision

`orders.payment_status` (`pending`/`paid`/`failed`/`refunded`/
`partially_refunded`) and `orders.status` (the lifecycle:
`pending → ... → delivered`/`cancelled`/`rejected`) are two independent
signals, and neither is redundant with the other:

- A card-on-delivery payment can be captured and marked `paid` before the
  order has even left the kitchen.
- A cash-on-delivery payment often stays `pending` in the system for a
  while after the food has actually been handed over, since nothing
  forces an admin to mark it `paid` the instant cash changes hands.

Neither reliably means "the restaurant has completed this sale." `status
= delivered` is the one signal that's true exactly when fulfillment is
actually done — so every dashboard revenue figure (Revenue, Average Order
Value, the sales trend chart, best-selling products) counts **only**
`delivered` orders. This is the same rule the previous Customers-Resource
task already established for "total spent"
(`App\Filament\Resources\Customers\{Tables\CustomersTable,
Schemas\CustomerInfolist}`) — kept consistent here rather than inventing a
second revenue rule for the Dashboard. Cancelled (and rejected) orders are
automatically excluded, since only `delivered` ever counts —
satisfying "عدم اعتبار الطلبات الملغاة ضمن الإيرادات" as a direct
consequence of the rule, not a separate special case.

`App\Services\DashboardMetricsService`'s own docblock carries this same
explanation, next to the code that implements it.

## Widgets

All scoped to the shared period filter is described below except where
noted. Sort order top to bottom:

| # | Widget | Type | Period-aware? | Visible to |
|---|---|---|---|---|
| 1 | `OrdersOverviewWidget` | Stats | Yes (page filter) | super_admin, manager, cashier |
| 2 | `OperationalStatusWidget` | Stats | No — always "right now" | every admin role |
| 3 | `SalesTrendChartWidget` | Chart (line) | Yes (its own 7d/30d filter) | super_admin, manager, cashier |
| 4 | `OrderStatusDistributionWidget` | Chart (doughnut) | Yes (page filter) | every admin role |
| 5 | `PaymentMethodDistributionWidget` | Chart (doughnut) | Yes (page filter) | super_admin, manager, cashier |
| 6 | `BestSellingProductsWidget` | Custom (Blade table) | Yes (page filter) | super_admin, manager, cashier |
| 7 | `LatestOrdersWidget` | Table | No — always latest 10 | every admin role |

- **`OrdersOverviewWidget`**: Orders/Revenue/Avg. order value/Cancelled,
  covering "الطلبات اليوم", "المبيعات اليوم", "متوسط قيمة الطلب",
  "الطلبات الملغاة".
- **`OperationalStatusWidget`**: Pending/Preparing/Late — "الطلبات
  pending", "الطلبات قيد التحضير", "الطلبات المتأخرة". Deliberately
  **not** period-scoped: these reflect the restaurant's state *right
  now*, not a historical report — a kitchen-role user needs "how many are
  late right now," never "how many were late during the last 30 days."
  Mirrors `App\Filament\Resources\Orders\Tables\OrdersTable::isLate()`'s
  exact overdue definition.
- **`BestSellingProductsWidget`**: a plain custom `Filament\Widgets\Widget`
  with its own Blade view (`resources/views/filament/widgets/
  best-selling-products.blade.php`), not a `TableWidget` — the underlying
  query is a `GROUP BY product_id` aggregate, which doesn't map onto a
  real per-record Eloquent query the way Filament's Table component
  expects (it assumes ordinary model rows for sorting/pagination/
  actions). A Blade loop over the already-cached aggregate array is
  simpler and doesn't force a non-CRUD read through CRUD-shaped
  infrastructure. Grouped by `product_id` against `order_items`' own
  snapshotted `product_name` (never the live `Product`, which may have
  been renamed or soft-deleted since) — same snapshotting principle used
  everywhere else order data is read.
- **`LatestOrdersWidget`**: a genuine `TableWidget` (real per-row `Order`
  query, no aggregation) — "آخر الطلبات", also not period-scoped for the
  same "what just happened" reasoning as `OperationalStatusWidget`.
  Read-only, no row actions; managing an order stays on
  `App\Filament\Resources\Orders\OrderResource`.

### Who sees what ("احترام صلاحيات الأدوار")

Two tiers, each enforced via `canView()` on the widget class itself (not
a Policy — widgets aren't Eloquent-model-backed, so there's no natural
Policy to auto-discover):

- **Financial/reporting** (revenue, average order value, payment methods,
  sales trend, best sellers): `super_admin`/`manager`/`cashier` only.
  Kitchen and support don't need revenue visibility.
- **Operational** (pending/preparing/late counts, order-status
  distribution, latest orders): every admin role — the same tier
  `App\Policies\OrderPolicy::viewAny()` already grants for the Orders
  screen itself, since none of this is revenue-sensitive and kitchen/
  support both have real reasons to want it (kitchen: the whole pipeline
  shape; support: what just came in).

The Dashboard *page* itself has no separate gate beyond
`canAccessPanel()` (any of the 5 admin roles) — the widgets police
themselves individually, which is also why a customer never even reaches
`/admin` at all (blocked earlier, before any widget is considered).

## The shared period filter

`App\Filament\Pages\Dashboard` uses Filament's own built-in
`HasFiltersForm` (`Filament\Pages\Dashboard\Concerns\HasFiltersForm`) with
one `Select::make('period')` (`App\Enums\DashboardPeriod`: Today/Last 7
days/Last 30 days/This month — "اختيار فترة زمنية"). Every period-aware
widget reads it via `Filament\Widgets\Concerns\InteractsWithPageFilters`
(`$this->pageFilters['period']`) — Filament persists the selection in the
session between visits, so a manager doesn't need to re-pick "last 7
days" every morning.

**A real ambiguity, checked rather than assumed**: this project already
hit a bug (`App\Filament\Resources\Coupons\Schemas\CouponForm`, see
`docs/ADMIN_COUPONS.md`) where a `Select::make(...)->options(EnumClass::class)`
resolved its live state to the *enum case itself* via `Get`, not the
plain string `->value` the code assumed. Rather than assume `pageFilters`
behaves the same way (or the opposite way) for this Select, every
period-aware widget reads it through one shared trait,
`App\Filament\Widgets\Concerns\ReadsDashboardPeriodFilter`, which accepts
*either* shape defensively — verified directly in
`tests/Feature/Filament/DashboardWidgetsTest.php` against both an
already-cast `DashboardPeriod` instance and a raw string.

`SalesTrendChartWidget` deliberately does **not** use this shared filter:
"المبيعات خلال آخر 7 أو 30 يومًا" asks for exactly one choice on exactly
this one chart, so it uses `ChartWidget`'s own simpler, independent
built-in local filter (`$filter`/`getFilters()`) instead.

## Performance: aggregation, indexes, caching

"عدم تنفيذ queries ثقيلة في كل تحميل" / "استخدام aggregation وindexes
وcaching عند الحاجة":

- **Aggregation, not row-by-row PHP**: every count/sum/group-by happens in
  SQL (`->count()`, `->sum()`, `->selectRaw(...)->groupBy(...)`) — no
  widget ever loads a collection of orders into PHP just to add them up.
- **New composite index**: `orders(status, created_at)` — nearly every
  dashboard query filters by both together (e.g. "delivered orders this
  week"), and the two columns were previously only indexed separately.
- **Caching is TTL-only, no invalidation observer** — a deliberate
  difference from `App\Services\MenuCacheService`'s catalog-data pattern
  (which *does* invalidate on write via an Observer). Orders change
  constantly — every status transition touches the same rows these
  aggregations scan — so invalidating a cached dashboard number on every
  order write would defeat the entire point of caching it. A dashboard
  figure lagging by up to two minutes is an acceptable, explicit
  trade-off for a reporting screen. Two TTLs: 120s for reporting/financial
  aggregations, 30s for the "right now" operational counts (staff act on
  those more immediately). Every cache key is scoped to the metric and the
  selected period (e.g. `dashboard:revenue:last_7_days`), so switching
  periods never serves another period's stale number.
- Widget-level Livewire polling is set explicitly (`30s`/`60s`) rather
  than left at Filament's aggressive `5s` default — polling that fast
  would call `getStats()`/`getData()` on every tick regardless of the
  cache TTL, working against the whole point of caching.

## Money: everything is an integer minor-unit amount

"جميع الأرقام المالية من integer minor units" — every amount
`DashboardMetricsService` returns is a raw integer in the currency's
smallest unit, exactly what `orders.total_amount`/
`order_items.line_total_amount` already store; `App\Support\Money` only
ever formats at render time (widgets, the Blade view, the CSV export). The
one place that divides — `averageOrderValueAmount()` — immediately
`round()`s back to an integer rather than ever returning or caching a
float.

## CSV export, safely

"إمكانية تصدير تقرير طلبات CSV بطريقة آمنة" — reached from the Dashboard's
"Export orders (CSV)" header action, which passes along whatever period
is currently selected (`GET /admin/orders/export/csv?period=...`,
`App\Http\Controllers\Admin\OrderExportController`).

- **Streamed**, not built as one array in memory —
  `response()->streamDownload()` + `Order::query()->chunk(200, ...)` — so
  the cost stays flat regardless of how many orders fall in the selected
  range.
- Registered outside Filament's own resource routing, at
  `/admin/orders/export/csv` — deliberately *not* `/admin/orders/export`,
  which would be the exact same single-segment shape as
  `OrderResource`'s own `/admin/orders/{record}` view route and risked
  colliding with it (confirmed via `php artisan route:list` that no such
  collision exists with the extra segment).
- **Authorization** reuses `OrderPolicy::viewAny()` — the same ability
  that already gates the Orders list — since this is a read-only export
  of the same data admins can already see, not a new capability. Every
  admin role that can view the Orders list can also export it (including
  kitchen/support — deliberate, since it's the same data, not the
  revenue-only tier the financial widgets use).
- **CSV injection prevention** ("منع CSV injection"): `App\Support\Csv::sanitizeCell()`
  applies the standard OWASP mitigation to *every* cell — any value
  starting with `=`, `+`, `-`, `@`, a tab, or a carriage return gets a
  literal single-quote prefix, which every mainstream spreadsheet app
  renders as plain leading text rather than triggering formula
  evaluation. The realistic vector here is a customer's free-text `name`
  (e.g. `=HYPERLINK("http://evil","click")`) — nothing server-side is
  ever at risk; the attack only fires if and when *someone later opens
  the exported file in a spreadsheet app*, which is exactly the scenario
  this mitigation closes.
- A UTF-8 BOM is written first so Arabic customer names render correctly
  in Excel instead of as mojibake.

## Testing

`tests/Feature/Services/DashboardMetricsServiceTest` (every aggregation:
revenue counting only `delivered`, cancelled orders never counting as
revenue even with a large `total_amount`, average order value's math and
its zero-orders edge case, period-scoping, the "right now" counts
ignoring the period entirely, best-sellers ranking and its
delivered-only filter, the sales trend series including zero-revenue
days, both distribution breakdowns including zero-count buckets, and the
cache actually caching — a change made after the first call is proven not
to appear until the TTL would expire), `tests/Feature/Filament/
DashboardWidgetsTest` (the full role-visibility matrix for every widget,
the Dashboard page loading for every admin role and rejecting a customer,
every widget rendering without error, stat values reflecting the selected
period, and the period filter working whether `pageFilters` hands back a
raw string or the enum instance directly), and `tests/Feature/Admin/
OrderExportControllerTest` (authorization, the BOM and header row, every
dangerous leading character actually neutralized, an ordinary name left
untouched, defaulting to "today" with no period given, and an invalid
period falling back to "today" instead of erroring).
