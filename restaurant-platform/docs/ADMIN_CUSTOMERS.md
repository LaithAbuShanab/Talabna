# Admin Customers & Addresses (FilamentPHP v5)

`restaurant-backend`'s Filament admin panel now has two Resources for the
customer side of the `users` table: `App\Filament\Resources\Customers\
CustomerResource` and `App\Filament\Resources\CustomerAddresses\
CustomerAddressResource`, both under the **Customers** navigation group.

## Same table, two Resources, two Policies problem

`CustomerResource` manages `role = customer` rows from the same `users`
table `App\Filament\Resources\Users\UserResource` already manages for
`role != customer` (admin) rows. Laravel only ever registers **one**
policy per Eloquent model class — `App\Policies\UserPolicy` is already
that policy, with a tight `super_admin`/`manager`-only `viewAny()` tier for
managing *other admin accounts*. `CustomerResource` needs a **different**
(broader) `viewAny`/`view` tier and different `create`/`update`/`delete`
rules for *customer* accounts, and Gate resolution can't give two
different answers for the same model class depending on which Filament
Resource is asking.

The fix: `CustomerResource` overrides `canViewAny()`/`canView()`/
`canCreate()`/`canEdit()`/`canDelete()` **directly on the Resource class**
(bypassing Gate/Policy resolution entirely for these), while `block()`/
`unblock()` — genuinely new named abilities, not part of Filament's
standard CRUD surface — were added straight onto the existing, singular
`UserPolicy` (reusable by both Resources without conflict, since they're
new method names, not a re-definition of `viewAny`/etc.).

## Customer Resource

Deliberately **no `form()`, no `create`/`edit` route** — "عرض بيانات
العميل" is a view, and the only write actions requested (block/unblock,
with a reason) go through dedicated actions, never a generic save:

- **List**: name, email, phone, order count (`withCount('orders')`), total
  spent, last order, active/blocked status.
- **"إجمالي إنفاقه" (total spent) only counts `delivered` orders** — a
  pending or cancelled order was never actually paid for; counting it
  would overstate a customer's real value. **"آخر طلب" (last order)**
  counts every order regardless of status — a customer's last *attempt*,
  useful context for support/kitchen even if it didn't complete.
- **Never renders a password or Sanctum token** — simply because no
  column for either is ever added to the table/infolist. `User`'s
  `#[Hidden(['password', 'remember_token'])]` attribute already guards
  API/array serialization; this is an independent guarantee on top of
  that, proven by a test that asserts the response body never contains
  either.
- **Block/unblock** (`App\Filament\Resources\Customers\Actions\
  CustomerBlockingActions`, shared between list row actions and the view
  page): `super_admin`/`manager` only (`UserPolicy::block()`/`unblock()`),
  requires a reason to block, requires confirmation either way. Both go
  through `App\Services\CustomerBlockingService` — never a raw form save —
  see "Blocking is real, not cosmetic" below.
- **"عدم السماح بحذف العميل بما يفسد السجلات"**: no delete route exists
  at all, and `canDelete()` unconditionally returns `false` as a backstop.
  `orders.user_id` is already `restrictOnDelete()` at the database level
  (see `docs/DATABASE_SCHEMA.md`), so a hard delete would fail there too —
  this is defense in depth, not the only guard.

## Blocking is real, not cosmetic

Before this task, `users.is_active` only gated **admin panel** login
(`User::canAccessPanel()`) — customer accounts were never checked against
it anywhere, so toggling it for a customer had zero effect. Blocking a
customer through this Resource is now real enforcement, end to end:

1. **`App\Services\CustomerBlockingService::block()`** sets `is_active =
   false` and records `blocked_reason`, **revokes every existing Sanctum
   token immediately** (`$customer->tokens()->delete()`), and logs an
   `AdminActivityLog` entry (`customer.blocked`/`customer.unblocked`,
   reusing the same audit trail admin-account changes already use — see
   `docs/ADMIN_PANEL.md`).
2. **`AuthController::login()`** rejects a blocked account *after* the
   password check succeeds (the customer already proved they own the
   credentials, so telling them plainly "this account has been blocked"
   rather than a generic failure isn't an enumeration risk).
3. **`App\Http\Middleware\EnsureAccountIsActive`** (aliased `ensure.active`
   in `bootstrap/app.php`, applied to the customer-facing `auth:sanctum`
   route group in `routes/api_v1.php`) is defense in depth for the (in
   practice empty) gap between "blocked" and "tokens revoked," and for any
   future write path that might set `is_active = false` without going
   through the service.

`is_active`/`blocked_reason` are both excluded from `User`'s
`#[Fillable(...)]` — the same privilege-escalation guard already applied
to `role` — so `CustomerBlockingService` (via `forceFill()`) is the only
intended writer.

`logout`/`logout-all-devices` deliberately skip the `ensure.active`
middleware: a blocked customer should still be able to log out of a token
that happens to still work, though in practice their tokens are already
gone the moment they're blocked.

## Customer Addresses: read-mostly

`App\Filament\Resources\CustomerAddresses\CustomerAddressResource` has
**no `form()`, no `create`/`edit` route** — an admin can look up a
customer's saved addresses (e.g. to help resolve a delivery issue) but
never change one.

Unlike `CustomerResource`, this one *is* a straightforward Gate/Policy
extension: `App\Policies\CustomerAddressPolicy` gained `viewAny()` (any
admin role) and an admin-role branch on `view()`, while `update()`/
`delete()` stay **exactly as they already were** for the customer-facing
API — ownership-only. An admin can never satisfy that ownership check, so
Filament's edit/delete actions are refused for every admin regardless of
role, with zero new code for that half — "read-mostly" falls out of the
existing rule for free.

## The phone number gap (background)

Neither `users` nor `customer_addresses` stored a phone number anywhere
before the Orders-screen task surfaced this gap (see `docs/ADMIN_ORDERS.md`).
A nullable `users.phone` column was added there, wired into the existing
profile-update endpoint — it's now shown on this Resource too (`—` when
never set).

## Testing

`tests/Feature/Filament/{CustomerResourceTest, CustomerAddressResourceTest}`,
`tests/Feature/Services/CustomerBlockingServiceTest`,
`tests/Feature/Http/EnsureAccountIsActiveTest`, plus additions to the
existing `tests/Feature/Api/V1/AuthTest` (blocked login rejected) and
`tests/Unit/Policies/UserPolicyTest` (block/unblock permission tiers) —
the access matrix, stats display (correctly excluding non-delivered orders
from spend), that no password/token ever appears in a response, block/
unblock's full effect (account disabled, reason recorded, tokens revoked,
activity logged), validation (a reason is required to block), the
permission tier, that no create/edit/delete route exists on either
Resource, and the address Resource's read-mostly guarantee.
