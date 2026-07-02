# Coding Standards

Applies to both `restaurant-backend` and `restaurant-customer-app` unless a
section says otherwise.

## Formatting

- **Laravel Pint** is the single source of truth for code style in both
  projects (`pint.json` at each project root, preset `laravel` +
  `declare_strict_types`). Never hand-debate style in review — run Pint.
- Every PHP file starts with `declare(strict_types=1);` (Pint enforces this
  automatically via the `declare_strict_types` rule). Use scalar/return type
  hints throughout; avoid mixed/untyped properties and parameters unless the
  value genuinely can be anything (e.g. a generic `mixed $data` payload).

## Naming conventions

| Kind | Convention | Example | Location |
|---|---|---|---|
| **Model** | Singular `PascalCase`, no suffix | `Order`, `MenuItem` | `app/Models/` |
| **Service** | `PascalCase` + `Service` suffix, one responsibility per class | `OrderPricingService` | `app/Services/` |
| **Action** | `PascalCase` verb phrase + `Action` suffix, single `handle()`/`__invoke()` entry point | `PlaceOrderAction` | `app/Actions/` |
| **DTO** | `PascalCase` + `Data`/`DTO` suffix, `readonly` where possible | `OrderLineData` | `app/DataTransferObjects/` |
| **Enum** | Singular `PascalCase`, no suffix, backed enum (`string` or `int`) | `OrderStatus`, `PaymentStatus`, `DeliveryType` | `app/Enums/` |
| **API Resource** | `PascalCase` + `Resource` suffix, matches the model it wraps | `OrderResource`, `MenuItemResource` | `app/Http/Resources/` |
| **Filament Resource** | `PascalCase` + `Resource` suffix, matches the model it manages (Filament's own generator convention) | `OrderResource` under `App\Filament\Resources` | `app/Filament/Resources/` |
| **Form Request** | `PascalCase` verb + noun + `Request` suffix | `StorePlaceOrderRequest`, `UpdateOrderStatusRequest` | `app/Http/Requests/` |
| **Policy** | Model name + `Policy` suffix | `OrderPolicy` | `app/Policies/` |
| **Controller** | Resource/noun + `Controller` suffix, no business logic | `OrderController` | `app/Http/Controllers/` |

Notes:

- A **Service** wraps a cohesive piece of business logic that's reused across
  multiple entry points (e.g. computing order totals). An **Action** wraps a
  single use case triggered from one place (e.g. "place an order"). If in
  doubt and it's only called from one controller method, it's an Action; if
  it's called from a controller, a queued job, and a console command, it's a
  Service.
- `App\Filament\Resources\OrderResource` (Filament panel CRUD) and
  `App\Http\Resources\OrderResource` (API JSON transformer) are two different
  classes with the same short name in different namespaces — this is
  intentional and matches each package's own convention; don't rename either
  to avoid the collision, the namespace already disambiguates them.
- Enums live in `app/Enums/` regardless of which model(s) use them, and are
  typed on the Eloquent model via `casts()`, never compared as raw
  strings/ints anywhere in the codebase.

## Business logic placement

- Controllers: validate (Form Request) → authorize (Policy/Gate) → delegate
  (Service/Action) → respond (API Resource wrapped in `ApiResponse`, see
  `docs/API_CONVENTIONS.md`). No query building or business rules directly in
  a controller method.
- Filament Resources configure the admin UI (forms/tables/actions) and may
  call the same Services/Actions the API controllers use — they should not
  duplicate business logic either.
- Anything that creates or mutates money-affecting records (orders, payments,
  refunds) runs inside `DB::transaction()`.

## Money and dates

- Monetary values are integers in the smallest currency unit, typed and
  named to make the unit obvious (e.g. `total_fils`, `price_fils`), never
  `float`/`decimal` columns or PHP `float` variables for money.
- Timestamps are stored and passed around in UTC; conversion for display is a
  presentation-layer concern only.

## Where this applies vs. `restaurant-customer-app`

`restaurant-customer-app` doesn't own domain models/an API of its own today
(see `docs/ARCHITECTURE.md`), so most of the naming table won't have entries
there yet. When it grows local concerns (e.g. a `CartItemData` DTO for
in-progress orders before they're submitted to the backend), the same
conventions apply.
