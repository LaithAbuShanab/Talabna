# Admin Menu Resources (FilamentPHP v5)

`restaurant-backend`'s Filament admin panel now has full CRUD for the menu
catalog: `Category`, `Product`, `ProductImage`, `OptionGroup`, `OptionValue`.
All five live under the **Menu** navigation group
(`App\Filament\Support\NavigationGroup::Menu`, from `docs/ADMIN_PANEL.md`),
each with its own `$navigationIcon` (that enum still deliberately doesn't
implement `HasIcon` — see `docs/ADMIN_PANEL.md` "Real bugs found").

## Authorization

Every resource is guarded by an ordinary Laravel Policy
(`App\Policies\{Category,Product,ProductImage,OptionGroup,OptionValue}Policy`),
auto-discovered by the `Model` → `{Model}Policy` convention — nothing
registered manually. All five use the same tier as the rest of the catalog:
`super_admin`/`manager` can view/create/update/delete; kitchen/cashier/
support get a `403` on every route. `forceDelete` is unconditionally denied
on every soft-deletable one of them (Category, Product, OptionGroup,
OptionValue) — see "Soft delete, never hard delete" below.

`CategoryPolicy::delete()` additionally refuses deletion while the category
still has **any** product, including soft-deleted ones:

```php
public function delete(User $user, Category $category): bool
{
    return $this->viewAny($user) && ! $category->products()->withTrashed()->exists();
}
```

This is "منع الأخطاء عند الحذف" (prevent delete errors): the delete button
is simply never reachable in that state (`assertActionHidden('delete')` in
`CategoryResourceTest`), rather than surfacing a database error after the
fact.

## `is_active` vs `is_available` on Product

Two independent booleans, both new columns on `products`:

- **`is_active`** — the admin's publish/unpublish switch. Same meaning as
  `Category.is_active`: an inactive product is treated as if it doesn't
  exist for customers at all. Toggled rarely (e.g. seasonal items).
- **`is_available`** — already existed from an earlier task. The kitchen's
  quick "temporarily out of stock" switch: the product stays visible in the
  menu but can't be added to a cart. Toggled often, several times a day.

Both gate visibility, and both were wired into the same two places that
already enforced `is_available` alone:

```php
// App\Services\MenuCacheService
$products = Product::query()
    ->where('is_active', true)
    ->where('is_available', true)
    ->whereHas('category', fn ($query) => $query->where('is_active', true))
    // ...

// App\Services\CartPricingService
if (! $product->is_active || ! $product->is_available) {
    throw new CartPricingException('product_unavailable', ['name' => $product->name]);
}
```

`preparation_minutes` (also new, nullable) is informational metadata only —
no automatic calculation reads it yet; `OrderStatusTransitionService` still
takes an explicit estimate per order from the admin accepting it. A natural
future integration point, not built out now.

## Money: major/minor units in the form, minor units in the database

Every price column (`Product.price_amount`, `OptionValue.price_delta_amount`)
is stored as an integer in the currency's smallest unit, same as the public
API (`docs/API_MENU.md`). The admin form converts transparently so the
admin types `2.500` (JOD) rather than `2500`:

```php
TextInput::make('price_amount')
    ->numeric()
    ->minValue(0)                                    // never negative
    ->step(1 / (10 ** Money::decimalsFor($currencyCode)))
    ->prefix($currencyCode)
    ->formatStateUsing(fn (?int $state) => $state !== null ? Money::toMajorUnits($state, $currencyCode) : null)
    ->dehydrateStateUsing(fn ($state): int => Money::toMinorUnits((float) $state, $currencyCode))
    ->helperText(fn ($state) => /* "Stored as 2500 (smallest JOD unit) — never negative." */),
```

`App\Support\Money` gained two new public helpers for this
(`toMinorUnits()`/`toMajorUnits()`), plus `decimalsFor()` (extracted from
the existing `format()`) so the admin form and the public API's rendering
share one source of truth for each currency's decimal places (3 for JOD/KWD/
BHD/OMR, 0 for JPY/KRW, 2 otherwise) instead of a second hardcoded copy that
could drift.

`minValue(0)` is the actual mechanism behind "منع الأسعار السالبة" (prevent
negative prices) — validated end-to-end in `ProductResourceTest::
test_a_negative_price_is_rejected` and `OptionValueResourceTest::
test_a_negative_extra_price_is_rejected`/`OptionGroupValuesRelationManagerTest::
test_a_negative_extra_price_is_rejected`, not just declared and assumed to
work.

## Soft delete, never hard delete

Category, Product, OptionGroup, and OptionValue all carry `SoftDeletes`.
None of their Filament resources ever exposes `ForceDeleteAction`/
`ForceDeleteBulkAction` — removed from the generated scaffolds — and every
policy's `forceDelete()` unconditionally returns `false` as defense in
depth, so even a direct URL to a force-delete route is refused server-side.
This is what satisfies "عدم حذف منتج مستخدم في طلب قديم؛ استخدم soft delete"
(never delete a product used in an old order — use soft delete): a
`Product` referenced by `order_items.product_id` can be soft-deleted freely
(the order keeps its historical snapshot), but never actually removed from
the table.

`ProductImage` is **not** soft-deletable (a removed image is just a stale
file reference — no order snapshot ever points back to it), so its resource
and relation manager use an ordinary `DeleteAction`.

## Drag-and-drop reordering

Filament v5's built-in `->reorderable('sort_order')` table method
(`Filament\Tables\Table\Concerns\CanReorderRecords`) — no extra package —
covers both explicit reordering requirements:

- `CategoriesTable` — categories, satisfying "ترتيب Drag-and-drop إن كان
  مدعومًا دون تعقيد غير ضروري".
- `OptionGroups\RelationManagers\ValuesRelationManager` — an option group's
  values, satisfying "إعادة ترتيب القيم".

Tested by calling the underlying Livewire method directly
(`->call('reorderTable', [$secondId, $firstId])`) and asserting the
resulting `sort_order` columns, rather than simulating an actual drag.

## Product images: one table, two entry points

The task asked for both a standalone `ProductImage` Resource **and**
"صورة رئيسية"/"صور إضافية" (primary image/additional images) as part of the
Product form. Both were built, pointing at the same `product_images` table,
with no conflict:

- **`Resources\ProductImages\ProductImageResource`** — a flat, cross-product
  view (e.g. for finding an orphaned or oversized file).
- **`Resources\Products\RelationManagers\ImagesRelationManager`** — nested
  on a product's edit page, the actually convenient place to manage *that*
  product's images without leaving the page. Enforces "exactly one image is
  ever primary" via `normalizePrimary()` after every create/edit/setPrimary
  action (see "Real bugs found" below for how that logic had to change).

The same dual pattern was used for values: `Resources\OptionValues\
OptionValueResource` (flat, cross-group) alongside `Resources\OptionGroups\
RelationManagers\ValuesRelationManager` (nested, reorderable, the natural
place to manage one group's values).

## Option groups: type + selection limits live in two different places

`OptionGroup` itself only carries `name`/`name_ar`/`selection_type`
(single/multiple — "single choice أو multiple choice")/`sort_order`. Whether
a group is **required** for a given product, and its **min/max
selections**, are *per-product* decisions stored on the pivot
(`product_option_groups.{is_required,min_select,max_select}`) — the same
"Size" group might be required for pizzas but optional for drinks. That's
why those fields appear on `Products\RelationManagers\
OptionGroupsRelationManager`'s form, not on `OptionGroupResource`'s.

`OptionGroupsRelationManager` deliberately uses a plain `HasMany` relation
(`Product::productOptionGroups()`, pointing straight at the `ProductOptionGroup`
pivot model) rather than Filament's `BelongsToMany`-with-pivot-schema APIs —
simpler and more predictable: an ordinary create/edit form with a `Select`
for `option_group_id` plus plain fields for the pivot columns, instead of
uncertain `AttachAction`-with-pivot-schema composition. `min_select`/
`max_select` use `->gte('min_select')` on `max_select` for cross-field
validation, and `option_group_id` is scoped-unique per product (one link per
group per product) via `->unique(modifyRuleUsing: ...->where('product_id', ...))`.

## Real bugs found (by testing, not by reading the code)

1. **`ProductOptionGroup` resolved to the wrong (singular) table name.**
   Adding `Product::productOptionGroups(): HasMany` — a plain `hasMany(
   ProductOptionGroup::class)`, needed for the relation manager above — threw
   `no such table: product_option_group` (missing the trailing `s`).
   `Illuminate\Database\Eloquent\Relations\Concerns\AsPivot::getTable()`
   overrides Eloquent's usual pluralized-table-name guess with a *singular*
   one for any `Pivot` subclass that doesn't set `$table` explicitly — the
   existing `BelongsToMany` relation had never hit this because it always
   passes the table name explicitly, so it had gone unnoticed until this
   new plain `HasMany` relation exposed it. Fixed with `protected $table =
   'product_option_groups';` on the model. Caught by a Livewire probe test
   attaching a real option group — reading the (idiomatic-looking)
   `hasMany()` call gave no hint of the problem.
2. **Uploaded images were being written to a disk with no public URL at
   all.** None of the new `FileUpload`/`ImageColumn` fields (`Category.
   image_path`, `Product`'s images, the standalone `ProductImage.path`) set
   an explicit `->disk()`. Filament's own default
   (`config('filament.default_filesystem_disk')`) resolved to `local` in
   this app, which — on Laravel 11+'s default `filesystems.php` — points at
   `storage/app/private` and has no `url` configured at all: not a "wrong
   disk," a disk that is structurally incapable of serving files publicly.
   The `public` disk (`storage/app/public`, with a real `url` config) also
   had no `storage:link` symlink yet, so it wasn't servable either, even
   once selected. Fixed by adding `->disk('public')` to every image field
   this task touched, and running `php artisan storage:link`. The
   pre-existing `RestaurantSetting.logo_path` field (`ManageRestaurantSettings`,
   from the earlier admin-panel task) has the same missing `->disk('public')`
   and is worth fixing too, but is out of this task's scope — flagged here
   rather than silently touched. **Fixed in the Restaurant Settings task**:
   see `docs/ADMIN_RESTAURANT_SETTINGS.md` — `->disk('public')` was added to
   `logo_path`/`cover_image_path`, and `AdminPanelProvider`'s `brandLogo`/
   `favicon` closures were also fixed to use `Storage::disk('public')->url()`
   instead of a bare `asset($path)`. Caught by a Livewire test that uploaded a
   real fake file and then tried to edit the record (the create-only path
   doesn't re-read the file from disk, so it wouldn't have caught this).
3. **`ProductImage.product_id` was missing from its `#[Fillable(...)]`
   list**, so the standalone `ProductImageResource`'s create form silently
   dropped the foreign key on save (`NOT NULL constraint failed`). This had
   been invisible on the *nested* `ImagesRelationManager` path, because a
   relation's `create()` sets the foreign key via
   `setForeignAttributesForCreate()` outside of mass assignment entirely —
   only the standalone resource's plain `Model::create($data)` path is
   mass-assignment-guarded and exposed the gap. Fixed by adding
   `'product_id'` to the Fillable list.
4. **`normalizePrimary()`'s tie-break by `updated_at` was wrong, not just
   imprecise.** The original logic, when more than one image was marked
   `is_primary` (e.g. right after marking a second one primary), kept
   whichever had the most recent `updated_at`. Two images saved within the
   same second — routine, not just a fast-test artifact — tie on that
   timestamp, and PHP's stable sort then keeps the *original* (insertion)
   order on ties, silently keeping the **first** image primary regardless of
   which one the admin had just marked. Fixed by passing the just-saved
   record explicitly into `normalizePrimary($product, $justSaved)` from every
   create/edit/setPrimary callback (never inferring intent from a
   timestamp), falling back to "first primary candidate, else first image"
   only when nothing was just saved (i.e. after a delete). Caught by a
   Livewire test creating a second primary image and asserting the *first*
   flipped to non-primary — the original heuristic passed every test that
   only checked "exactly one stays primary" without checking *which* one.

## Testing

`tests/Feature/Filament/{CategoryResourceTest, ProductResourceTest,
ProductImageResourceTest, ProductImagesRelationManagerTest,
ProductOptionGroupsRelationManagerTest, OptionGroupResourceTest,
OptionGroupValuesRelationManagerTest, OptionValueResourceTest}` — creation,
editing, validation (required fields, non-negative prices, `max ≥ min`,
scoped uniqueness), bulk enable/disable, soft delete/restore, drag-and-drop
reordering, the "never a force-delete action" and "never a delete action
when a category has products" guarantees, and the full super_admin/manager
vs. kitchen/cashier/support access matrix over real HTTP. Menu API ripple
from the new `is_active` column is covered by `test_lists_only_active_products()`
(`Api/V1/Menu/ProductTest`) and `test_rejects_an_inactive_product()`
(`Api/V1/Menu/CartPreviewTest`).
