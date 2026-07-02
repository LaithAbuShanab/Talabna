# Local Development Data (not for production)

This file documents the demo accounts and data created by `php artisan
db:seed` (or `php artisan migrate:fresh --seed`). **Never use these
credentials in a staging or production environment** — the password below
is a well-known Laravel development placeholder, not a real secret.

Running the seeders is idempotent: `php artisan db:seed` can be run
repeatedly without creating duplicates or erroring.

## Demo login credentials

| Role | Email | Password |
|---|---|---|
| Admin (Filament panel, `/admin`) | `admin@example.com` | `password` |
| Customer | `customer1@example.com` .. `customer5@example.com` | `password` |

The password is set by `database/factories/UserFactory.php`'s default
(`Hash::make('password')`) — the same placeholder every fresh Laravel
project ships with, applied consistently to every seeded user here.

## What gets seeded (`database/seeders/`)

| Seeder | Creates |
|---|---|
| `RestaurantSettingSeeder` | The single restaurant settings row |
| `BusinessHourSeeder` | 7 rows (one per weekday), Friday closed |
| `CategorySeeder` | 5 categories: Burgers, Pizza, Sandwiches, Drinks, Desserts |
| `OptionSeeder` | 4 option groups: **Size**, **Bread Type** (single-select), **Extras**, **Sauces** (multi-select), with values |
| `ProductSeeder` | 17 products across the 5 categories, each with a local placeholder image and the option groups that make sense for it |
| `DeliveryZoneSeeder` | 3 delivery zones with distinct fees and minimum order amounts |
| `CouponSeeder` | 3 demo coupons: `WELCOME10` (10% off), `SAVE5` (fixed discount), `EXPIRED20` (expired, for testing rejection) |
| `AdminUserSeeder` | The `admin@example.com` account above |
| `CustomerSeeder` | 5 demo customers (`customer1@example.com`..`customer5@example.com`), each with 1-2 addresses |
| `OrderSeeder` | 9 demo orders covering every `OrderStatus` value (pending, accepted, preparing, ready, out_for_delivery, delivered ×2, cancelled, rejected), each with real order items/options and a matching status-history audit trail |

See [`docs/DATABASE_SCHEMA.md`](../docs/DATABASE_SCHEMA.md) for what each
table actually means; this file is just "what did the seeder put in it and
how do I log in."

## Placeholder images

Product images point at small local SVG files under
`public/images/placeholders/` (one per category) — not an external image
service, so seeding works offline and never depends on a third party being
up. Swap `product_images.path` for real uploaded images whenever actual
product photography is available; the placeholder files can stay in the
repo as a fallback.

## Re-seeding

```bash
php artisan migrate:fresh --seed   # wipe and reseed everything
php artisan db:seed                # re-run seeders against existing data (idempotent)
```
