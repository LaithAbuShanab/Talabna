# Admin Delivery Zones & Business Hours (FilamentPHP v5)

Three Resources under the **Delivery**/**Settings** navigation groups:
`App\Filament\Resources\DeliveryZones\DeliveryZoneResource` (Delivery),
`App\Filament\Resources\BusinessHours\BusinessHourResource` and
`App\Filament\Resources\BusinessHourExceptions\BusinessHourExceptionResource`
(both Settings, since operating hours are a restaurant-wide setting, not
delivery-specific).

## Delivery Zones

Full CRUD, soft-delete-only (`forceDelete` always denied — see
`App\Policies\DeliveryZonePolicy`), covering every field already on the
`delivery_zones` table: name, delivery fee (money-aware, major/minor
conversion like every other price field in this admin panel), minimum
order amount, estimated delivery time, active flag, and the coverage area.

**"polygon أو geospatial mapping ليس مطلوبًا في النسخة الأولى؛ استخدم
مناطق معرفة إداريًا بطريقة بسيطة"**: the coverage area is three plain
numeric inputs — latitude, longitude, radius (meters) — deliberately not
a map/polygon-drawing UI. This isn't a shortcut: it's exactly the shape
`App\Http\Controllers\Api\V1\DeliveryZoneController::check()` already
used (a Haversine distance check against a center point + radius), so the
form just exposes the fields that already drive real zone matching, with
no schema change needed.

## Business Hours: now supports more than one period per day

Before this task, `business_hours` had a hard uniqueness constraint —
exactly one row per `day_of_week`, one opening/closing shift. "أكثر من
فترة في اليوم إن لزم" (e.g. a lunch shift and a separate dinner shift)
needed a real schema change: a migration drops that uniqueness (replaced
with a plain index), so a day can now have any number of rows.

`App\Filament\Resources\BusinessHours\BusinessHourResource` is
accordingly just an ordinary flat CRUD table now, not a per-day singleton
editor — `day_of_week` is an ordinary (non-unique) `Select`, and adding a
second period for a day that already has one is simply creating another
row. `opens_at`/`closes_at` are only required (and only shown) when
`is_closed` is off.

**`App\Services\RestaurantAvailabilityService::isOpenNow()`** was updated
to match: it now fetches *every* non-closed period for today
(`->where('day_of_week', ...)->get()`, not `->first()`) and is open if the
current time falls inside **any** of them — verified with a dedicated
test for the exact scenario a single-period design couldn't represent
(open during a second period, closed in the gap between two periods on
the same day).

## Holiday exceptions ("استثناءات العطل الرسمية")

A new, deliberately simple `business_hour_exceptions` table — one row per
calendar date (`date`, unique), `is_closed`, optional `opens_at`/
`closes_at`, an optional free-text `note` for the admin's own reference.
No recurrence rules, no calendar/holiday-provider integration — exactly
the "تطوير إضافي بسيط" the task allowed.

`App\Filament\Resources\BusinessHourExceptions\BusinessHourExceptionResource`
("Holiday Exceptions" in the sidebar) is full CRUD. `RestaurantAvailabilityService`
checks this table **first**: if an exception row exists for today, it
entirely replaces the regular weekly schedule for that date — either
`is_closed = true` (fully closed regardless of what `business_hours`
says), or a custom `opens_at`/`closes_at` pair (which can grant hours on a
day the regular schedule has marked closed, or restrict hours on a day
that's normally open). No exception for today → falls back to the
regular schedule exactly as before.

**Real bug found by testing:** `BusinessHourException.date` was originally
cast `'date'` (a Carbon instance). Eloquent's `date` cast reformats the
attribute to a full `"Y-m-d H:i:s"` string via `getDateFormat()` (not
overridden here) before it's written to the database — but Filament's
`->unique()` form validation compares the *raw* `"Y-m-d"` the `DatePicker`
submits against that already-reformatted stored value. The mismatch meant
the uniqueness check **silently never matched**, so creating a second
exception for an already-used date sailed straight past form validation
and only surfaced as a raw `SQLSTATE... UNIQUE constraint failed`
exception — an ugly 500-shaped failure instead of a clean validation
error. Fixed by leaving `date` uncast (a plain string): nothing else reads
it as a `Carbon` instance (`RestaurantAvailabilityService` already
compared it against a plain `Y-m-d` string), so removing the cast has no
other effect and closes the mismatch entirely. Caught by a permanent test
asserting the *validation* error, not the DB exception — the same test
that failed loudly with the raw SQL exception before the fix.

## Testing

`tests/Feature/Filament/{DeliveryZoneResourceTest, BusinessHourResourceTest,
BusinessHourExceptionResourceTest}` (creation, validation — including the
open/closed-conditional required fields on both business-hours-shaped
forms and the date-uniqueness fix specifically — multiple periods on one
day, deletion, access control) and `tests/Feature/Services/
RestaurantAvailabilityServiceTest` (single period open/closed, open during
the second of two periods, closed in the gap between them, a holiday
exception forcing closed despite normal hours, a holiday exception
granting custom hours on an otherwise-closed day, and falling back to the
regular schedule when no exception exists for today).
