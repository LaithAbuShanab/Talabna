# Admin Panel (FilamentPHP v5)

`restaurant-backend`'s Filament panel, mounted at `/admin`
(`App\Providers\Filament\AdminPanelProvider`). Every API/namespace used
below is Filament v5's own (`Filament\Auth\*`, `Filament\Schemas\*`,
`Filament\Actions\*`, `Filament\Support\Icons\Heroicon`, etc.) — no v3/v4
patterns, no third-party Filament plugins.

## Who can log in

Only administrative roles, and only while active:

```php
// App\Models\User
public function canAccessPanel(Panel $panel): bool
{
    return $this->role->isAdmin() && $this->is_active;
}
```

`App\Enums\UserRole` has six cases: `Customer` (the only non-admin one —
`isAdmin()` returns `$this !== self::Customer`) and five admin roles:

| Role | Value |
|---|---|
| Super Admin | `super_admin` |
| Manager | `manager` |
| Kitchen | `kitchen` |
| Cashier | `cashier` |
| Support | `support` |

`is_active` (new `users` column, default `true`) lets an admin account be
deactivated without deleting it — a deactivated account can never log in
regardless of role. Both `role` and `is_active` are excluded from `User`'s
`#[Fillable(...)]` list (the same privilege-escalation guard the customer
API already relied on), so **every** write path that touches either field —
the Filament `CreateUser`/`EditUser` pages included — must use
`forceFill()`, never mass assignment. This is not a hypothetical: the
default Filament `CreateRecord`/`EditRecord` save methods
(`new Model($data)` / `$record->update($data)`) *are* mass assignment, and
would silently drop `role`/`is_active` without the override — caught by
`UserResourceCrudTest`, not by reading the code (see "Real bugs found"
below).

Demo accounts (`AdminUserSeeder`, one per role, `admin@example.com` /
`manager@example.com` / `kitchen@example.com` / `cashier@example.com` /
`support@example.com`, all password `password` — dev-only, see
`README.dev.md`).

## Why not a permissions package

The task asked to check Filament v5 compatibility before adding one.
`bezhansalleh/filament-shield` (the standard choice, wrapping
`spatie/laravel-permission`) **does already declare Filament v5 support**
(`filament/filament: ^4.0|^5.0` per its `composer.json`, verified against
Packagist directly rather than assumed) — so this wasn't a compatibility
rejection.

It wasn't adopted anyway, because:

- The role set here is **fixed and small** (5 known admin roles) — nothing
  in this task asks for admins to define arbitrary custom roles/permissions
  through a UI, which is filament-shield/spatie's actual value
  proposition (dynamic many-to-many role↔permission assignment).
- Adopting it would mean **replacing** the existing single `role` enum
  column — already used directly by `CartPricingService`,
  `OrderStatusTransitionService`, `OrderPolicy`, and every customer-facing
  API endpoint from earlier tasks — with spatie's separate
  roles/permissions/model_has_roles/model_has_permissions tables. That's a
  much larger, riskier change than "prepare the admin panel," touching
  code far outside the panel.
- Plain Laravel Policies (`App\Policies\*`), checked directly against the
  `UserRole` enum, give 100% of "fine-grained, policy-enforced
  authorization, not just hidden buttons" for this fixed role set, with
  zero new tables and zero new dependencies.

If the project later needs admins to define custom, per-installation
permissions, filament-shield + spatie/laravel-permission remains the
natural upgrade path — but that's a real, separate future task, not
something to speculatively build now.

## Authorization: policies, not button-hiding

Every Filament Resource/Page here is guarded by an ordinary Laravel Policy,
auto-discovered by Laravel's `App\Models\X` → `App\Policies\XPolicy`
convention (nothing registered manually) — Filament calls these
automatically for every resource action (`viewAny`, `view`, `create`,
`update`, `delete`), so a request that reaches the server through any path
other than the hidden button (a direct URL, a re-enabled button via
browser devtools, a race condition) is still refused server-side.
Confirmed directly: `UserResourceAccessTest`/`RestaurantSettingsPageTest`/
`AdminActivityLogResourceAccessTest` all assert `403` by hitting routes
over HTTP, not by checking whether a button is rendered.

| Policy | Guards | Rule |
|---|---|---|
| `OrderPolicy` | Order status transitions (existing, from an earlier task — see `docs/ORDER_LIFECYCLE.md`) | `manage` (accept/prepare/ready/dispatch): super_admin, manager, kitchen. `cancelAtReadyStage`: super_admin, manager. `cancelAtOutForDeliveryStage`: super_admin only. |
| `UserPolicy` | `Resources\Users\UserResource` (admin accounts only — see below) | `viewAny`/`view`: super_admin, manager. `create`/`update`/`delete`: super_admin only. `delete` also refuses deleting yourself (self-lockout guard). |
| `RestaurantSettingPolicy` | `Pages\ManageRestaurantSettings` | `view`/`update`: super_admin, manager. |
| `AdminActivityLogPolicy` | `Resources\AdminActivityLogs\AdminActivityLogResource` | `viewAny`/`view`: super_admin, manager. `create`/`update`/`delete`: always `false` (read-only by policy, not just by omitting the buttons — the resource also has no `create`/`edit` route at all). |

`OrderPolicy`'s three admin abilities were re-tiered by this task from a
single "any admin" check to the matrix above — kitchen staff run the
day-to-day accept/prepare/ready/dispatch flow but can't cancel a
ready-or-later order; only super_admin can cancel an order that's already
out for delivery, the highest-risk cancellation in the lifecycle.

## Navigation groups

`App\Filament\Support\NavigationGroup` (a plain enum implementing
`Filament\Support\Contracts\HasLabel`) is passed directly to
`Panel::navigationGroups()` — Filament v5 natively resolves a whole group
list from an enum class-string
(`Filament\Navigation\NavigationGroup::fromEnum()`), so no manual
`NavigationGroup::make()` calls are needed:

```php
->navigationGroups(NavigationGroup::class)
```

Nine groups: Dashboard, Orders, Menu, Customers, Promotions, Delivery,
Reports, Settings, Administration. **This task scoped in the panel's
infrastructure** (auth, roles, policies, branding, RTL, audit trail,
notifications, profile) **and three concrete Resources/Pages to prove it
end-to-end** — it did not build full CRUD for every domain (Orders,
Products/Categories, Coupons, DeliveryZones). Those groups are declared
and ready; Filament simply omits an empty group from the sidebar, so
adding a future Resource is a one-line `$navigationGroup` assignment, not
a panel reconfiguration. What exists today:

- **Administration**: `Resources\Users\UserResource` (admin accounts),
  `Resources\AdminActivityLogs\AdminActivityLogResource` (audit trail,
  read-only).
- **Settings**: `Pages\ManageRestaurantSettings`.
- Dashboard/Orders/Menu/Customers/Promotions/Delivery/Reports: declared,
  empty for now.

The enum deliberately does **not** implement `HasIcon` even though
Filament supports group icons too — see its docblock and "Real bugs
found" below for why.

## Branding, Arabic, RTL

```php
->brandName(fn () => RestaurantSetting::current()->restaurant_name)
->brandLogo(fn () => ($path = RestaurantSetting::current()->logo_path) !== null ? asset($path) : null)
```

Both are Closures reading the same `RestaurantSetting` singleton the
public menu API's `GET /api/v1/restaurant` already serves (`docs/API_MENU.md`)
— editing them on the new **Settings** page (`ManageRestaurantSettings`)
updates the panel's own branding immediately, no redeploy. `logo_path` is a
new nullable column, uploaded via `Filament\Forms\Components\FileUpload`.

Arabic + RTL needed **no new code at all**: Filament ships Arabic
translations for every one of its sub-packages out of the box
(`vendor/filament/*/resources/lang/ar`), and its layout picks `dir="rtl"`
from the `filament-panels::layout.direction` translation string, which the
`ar` locale already sets to `"rtl"`. Since `APP_LOCALE=ar` was already this
project's default (from an earlier task), the panel already rendered
`dir="rtl" lang="ar"` before this task touched anything — confirmed live
(`PanelAccessTest::test_login_page_renders_in_arabic_and_rtl_by_default`)
rather than assumed.

## Profile & password

```php
->profile(isSimple: false)
```

Uses Filament v5's **built-in** `Filament\Auth\Pages\EditProfile` — no
custom page was written. It already includes name/email/password/
current-password fields out of the box (`isSimple: false` renders it
inside the normal panel layout with the sidebar, rather than a bare
centered card).

## Database notifications

```php
->databaseNotifications()
```

Compatible and enabled — this is a core Filament v5 Panel feature, not a
plugin, reading `Illuminate\Notifications\Notifiable`'s standard
`notifications()` relationship (already used by `App\Models\User`). Needed
a new `notifications` table migration (Laravel's standard schema; none
existed before this task, since nothing in the app had sent a database
notification yet).

## Audit trail

A new, deliberately simple, append-only `admin_activity_logs` table
(`App\Models\AdminActivityLog`, same "throw on update/delete" enforcement
pattern as the existing `OrderStatusHistory`) — not a generic per-model
change tracker, and not `spatie/laravel-activitylog`: just a curated set
of writes for genuinely sensitive actions, via one small service:

```php
App\Services\AdminActivityLogger::log(?User $actor, string $action, ?Model $subject = null, ?string $description = null, array $metadata = []): AdminActivityLog
```

Wired into:

- **Order status changes made by staff** — `App\Listeners\LogAdminOrderStatusChange`,
  listening for the existing `OrderStatusChanged` event (from the earlier
  order-lifecycle task) but only logging when the actor is non-null *and*
  an admin role — a customer's own cancellation or a null-actor system
  transition is ordinary activity, not audit-worthy.
- **Admin account created/updated/deleted** — `Pages\{CreateUser,EditUser}`'s
  `afterCreate()`/`afterSave()` hooks and the `DeleteAction::after()`
  callback (shared via `UserResource::logDeletion()` so both the table-row
  and edit-page delete buttons record the same way). `afterSave()` only
  writes when `role` or `is_active` actually changed (via `getChanges()`),
  not on every save.
- **Restaurant settings updated** — `ManageRestaurantSettings::save()`.

Viewable at **Administration → Activity Log**
(`AdminActivityLogResource`), restricted to super_admin/manager
(`AdminActivityLogPolicy`), read-only (no create/edit routes exist, and
the policy denies those abilities unconditionally regardless).

## Real bugs found (by testing, not by reading the code)

1. **Navigation group icon conflict.** `NavigationGroup` originally also
   implemented `Filament\Support\Contracts\HasIcon` (alongside `HasLabel`),
   and every Resource/Page set its own `$navigationIcon` too. Filament
   refuses this combination outright: *"Navigation group [...] has an icon
   but one or more of its items also have icons. Either the group or its
   items can have icons, but not both."* — a hard `Exception`, not a
   warning, breaking every single page in an affected group with a `500`.
   Caught by an HTTP-level probe test (`actingAs($admin)->get('/admin')`)
   before the real test suite was even written — reading the enum/resource
   code in isolation gave no reason to suspect a conflict. Fixed by
   dropping `HasIcon` from the group enum and keeping each Resource/Page's
   own specific icon (more useful once a group holds more than one item
   anyway).
2. **Mass-assignment silently dropping `role`/`is_active`.** Covered above
   — `CreateUser`/`EditUser` needed `handleRecordCreation()`/
   `handleRecordUpdate()` overrides using `forceFill()`. Same root cause
   and same fix shape as the `AuthController::register()` bug found in the
   earlier auth-API task (`User::create(['role' => ...])` silently ignoring
   `role`); this task applied the lesson proactively before it could bite,
   then verified with `UserResourceCrudTest` (Livewire component tests
   that actually fill the form and call `create`/`save`, not just check
   the page loads).
3. **Legacy `role = 'admin'` data.** Splitting the old single `admin` case
   into five roles meant the one already-seeded admin row
   (`admin@example.com`, `role = 'admin'` in the real local MySQL database)
   would fail to cast to the enum at all — a `ValueError` on every read,
   not caught by any migration `up()` that doesn't touch existing data.
   Fixed with a dedicated data migration
   (`migrate_legacy_admin_role_to_super_admin`) converting `'admin'` →
   `'super_admin'` (and back, in `down()`), run and verified against the
   real database before writing any tests.

## Testing

`tests/Feature/Filament/{PanelAccessTest, UserResourceAccessTest,
UserResourceCrudTest, RestaurantSettingsPageTest,
AdminActivityLogResourceAccessTest}`, `tests/Unit/Policies/{UserPolicyTest,
RestaurantSettingPolicyTest, AdminActivityLogPolicyTest}` (plus the
existing `OrderPolicyTest`, extended for the new role matrix),
`tests/Feature/Services/AdminActivityLoggerTest`,
`tests/Feature/Listeners/LogAdminOrderStatusChangeTest` — panel login per
role, customer/deactivated-admin exclusion, RTL/Arabic/branding rendering,
every Resource/Page's authorization matrix asserted over real HTTP (`200`/
`403`), the `role`/`is_active` mass-assignment fix specifically (Livewire
`fillForm()`/`call('create'|'save')`, not just page-loads-OK), self-delete
prevention, and the audit trail actually recording (or correctly *not*
recording) for each wired event.
