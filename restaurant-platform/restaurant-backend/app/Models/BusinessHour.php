<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BusinessHourFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per weekday, 0 (Sunday) through 6 (Saturday) — matching Carbon's
 * dayOfWeek numbering.
 */
#[Fillable(['day_of_week', 'opens_at', 'closes_at', 'is_closed'])]
class BusinessHour extends Model
{
    /** @use HasFactory<BusinessHourFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed' => 'boolean',
        ];
    }
}
