# Admin Restaurant Settings (FilamentPHP v5)

`App\Filament\Pages\ManageRestaurantSettings` is a single-record settings
page bound to `App\Models\RestaurantSetting::current()` — not a Resource,
since there's exactly one row and list/create/delete would be meaningless.
Guarded by `App\Policies\RestaurantSettingPolicy`, same as before this task;
no settings package (spatie/laravel-settings, etc.) is used, see
`docs/ADMIN_PANEL.md`.

## Single-record enforcement

`RestaurantSetting::current()` always resolves the same row via
`firstOrCreate(['id' => 1], [...])`. There is no create/delete route or
action anywhere in the admin panel for this model — the Page's `mount()`
always loads row 1 and `save()` always updates it. A fresh install gets one
row lazily, on first read, with sensible defaults (see the model's
`current()` docblock for why every default is spelled out explicitly rather
than left to the migration's column defaults).

## Fields, organized into tabs

Basic info (bilingual name, logo, cover image, phone, email, address) →
Regional & tax (currency, timezone, tax enabled/rate/inclusive) → Ordering
(minimum order, delivery/pickup toggles, open/closed + closure message,
prep time) → Policies (bilingual cancellation/terms/privacy text) → Social
media (Facebook/Instagram/X/WhatsApp) → Notifications (order emails, push
key).

## Safe file uploads

`logo_path`/`cover_image_path` both use:

```php
FileUpload::make('logo_path')
    ->image()
    ->disk('public')
    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
    ->maxSize(2048)
```

`acceptedFileTypes` deliberately excludes `image/svg+xml` — an SVG can
embed `<script>`, making it an XSS vector if ever served or rendered
inline, unlike a raster image. No `->preserveFilenames()` — Filament's
default randomized filename is kept, so an uploaded filename can never
collide with, overwrite, or path-traverse into another file. `maxSize` is
in KB (2048 = 2MB).

**Found and fixed in this task**: `App\Providers\Filament\AdminPanelProvider`'s
`->brandLogo()`/`->favicon()` closures read `RestaurantSetting::current()->logo_path`
and built the URL with a bare `asset($path)`. That was never a working
combination — `logo_path` is uploaded to the `public` disk
(`storage/app/public/...`, exposed at `/storage/...` via the `storage:link`
symlink), so the correct URL is `Storage::disk('public')->url($path)`, not
`asset($path)` (which points at the web root and would 404). This bug
pre-dates this task (flagged as out-of-scope follow-up in
`docs/ADMIN_MENU_RESOURCES.md` and `docs/PROJECT_STATE.md` from the Menu
Resources task) and is fixed here since it directly affects the logo field
this task builds out.

## Validation

Standard Filament field rules (`required`, `->email()`, `->url()`,
`->tel()`, `numeric`/`minValue`/`maxValue`) plus two custom pieces:

- `tax_rate_bps` is edited as a 0–100 percentage (`->suffix('%')`) and
  converted to/from basis points via `formatStateUsing`/
  `dehydrateStateUsing` — the stored column stays in bps (1 bps = 0.01%),
  matching `App\Services\CartPricingService`'s `$rate = tax_rate_bps / 10000`.
- `order_notification_emails` is a comma-separated free-text field with a
  closure rule that validates every non-empty entry via
  `filter_var(..., FILTER_VALIDATE_EMAIL)`, required only when
  `notify_new_orders_by_email` is on.

## Cache invalidation — already handled, no new code

`App\Observers\MenuCacheObserver` already listens for `RestaurantSetting`
`saved`/`deleted`/`restored` events and calls
`MenuCacheService::forgetRestaurantSettings()` (from an earlier task). This
task's expanded field set needed zero new caching code — every field this
page can change goes through the same `RestaurantSetting::update()`/
`forceFill()->save()` calls the observer already watches.

## The one genuinely sensitive field: `push_notification_key`

"عدم وضع مفاتيح خدمات الدفع أو Push كنص مكشوف داخل قاعدة البيانات دون
تشفير" (never store payment/push service keys as plaintext) and "إظهار
الإعدادات الحساسة فقط لمن لديه صلاحية" (show sensitive settings only to
those with permission) are both handled entirely at this one field:

- **Encrypted at rest**: `RestaurantSetting`'s cast list marks it
  `'push_notification_key' => 'encrypted'` — Laravel's built-in
  `AsEncryptedString` cast, keyed off `APP_KEY`. Verified empirically (not
  just assumed): the raw `restaurant_settings.push_notification_key`
  column value is genuine ciphertext, never the plaintext key, and reading
  it back through the model transparently decrypts it.
- **Never mass-assignable**: it's the one field deliberately *absent* from
  `RestaurantSetting`'s `#[Fillable([...])]` list (mirrors the existing
  `User.role`/`is_active` exclusion). The only legitimate writer is
  `ManageRestaurantSettings::save()`, which sets it via `forceFill()`.
- **Gated by a new policy ability**: `RestaurantSettingPolicy::viewSensitive()`
  — `super_admin` only. The form field itself is
  `->visible($canViewSensitive)`; a manager's page never even renders the
  field, let alone its value.
- **Never re-displayed once saved**: `mount()` explicitly excludes
  `push_notification_key` when prefilling the form
  (`Arr::except($settings->toArray(), ['push_notification_key'])`), so the
  field always starts blank — a decrypted secret never round-trips back
  into the browser.
- **Blank submission preserves the existing value**: the field is
  `->dehydrated(fn (?string $state): bool => filled($state))`, the same
  "blank = keep current" convention already used for `User` passwords
  (`App\Filament\Resources\Users\Schemas\UserForm`). `save()` checks
  `array_key_exists('push_notification_key', $data)` — only present when a
  non-blank value was actually submitted — before calling `forceFill()`.
  Audit logging records that the key *changed* (`true`), never its value.

## Business rules this task wired into real enforcement, not just storage

Two settings were built with a real "should this actually be enforced, or
just decorative?" decision, resolved in favor of enforcement:

- **`allows_delivery` / `allows_pickup`**: checked as the very first
  validation step in `App\Services\CartPricingService::price()`, before any
  item pricing — a cart requesting a disabled delivery type throws
  `CartPricingException` (`delivery_not_allowed`/`pickup_not_allowed`),
  exactly like the pre-existing `min_order_not_met`/`delivery_zone_invalid`
  checks.
- **`is_tax_inclusive`**: changes `CartPricingService`'s actual tax math,
  not just a label. Exclusive (default) adds tax on top:
  `taxAmount = taxableAmount * rate`. Inclusive extracts the tax already
  baked into the price: `taxAmount = taxableAmount - taxableAmount / (1 + rate)`
  — and does **not** add anything further to the grand total. Delivery fee
  is never taxed either way (unchanged from before this task).
- **`timezone`**: also wired into `App\Services\RestaurantAvailabilityService::isOpenNow()`
  — `now($settings->timezone)` instead of a bare `now()`, so business-hours
  comparisons happen in the restaurant's local time, not the server's.
  Defaults to `UTC` (not a restaurant-locale guess like `Asia/Amman`) so
  existing behavior is unchanged for every restaurant that never touches
  this new field.

All three were confirmed against the full test suite (`CartPricingServiceTest`,
`RestaurantAvailabilityServiceTest`) with no regressions before and after.

## Tests

- `tests/Feature/Filament/RestaurantSettingsPageTest.php` — access control,
  single-record enforcement, save + audit log, sensitive-field visibility
  per role, blank-preserves-existing push key, push key encryption at rest,
  file-upload type rejection/acceptance, notification-email validation,
  tax-inclusive/rate persistence.
- `tests/Unit/Policies/RestaurantSettingPolicyTest.php` — `view`/`update`/
  `viewSensitive` per role.
- `tests/Feature/Services/CartPricingServiceTest.php` — delivery/pickup
  rejection, inclusive vs. exclusive tax math.
- `tests/Feature/Services/RestaurantAvailabilityServiceTest.php` —
  non-UTC timezone changes what "open now" means.
