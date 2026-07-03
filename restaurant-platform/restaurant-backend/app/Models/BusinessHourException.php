<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BusinessHourExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per calendar date that overrides the regular weekly
 * App\Models\BusinessHour schedule for that specific date — a public
 * holiday, a one-off early closure, etc. See
 * App\Services\RestaurantAvailabilityService, which checks here first.
 *
 * `date` is deliberately left uncast (a plain "Y-m-d" string): Eloquent's
 * `date` cast re-serializes the attribute to a full "Y-m-d H:i:s" string
 * before it's written to the database (via `getDateFormat()`, which isn't
 * overridden here), while Filament's `->unique()` form validation compares
 * the raw "Y-m-d" the DatePicker submits against the *stored* value — a
 * mismatch that made the uniqueness check silently never match, so a
 * genuine duplicate date sailed past form validation and only surfaced as
 * a raw SQL "UNIQUE constraint failed" exception instead of a clean
 * validation error. Comparing/storing a plain string sidesteps the
 * mismatch entirely; nothing reads `date` as a Carbon instance elsewhere
 * (RestaurantAvailabilityService already compares it against a plain
 * `Y-m-d` string too).
 */
#[Fillable(['date', 'is_closed', 'opens_at', 'closes_at', 'note'])]
class BusinessHourException extends Model
{
    /** @use HasFactory<BusinessHourExceptionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_closed' => 'boolean',
        ];
    }
}
