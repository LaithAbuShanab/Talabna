<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Internal counter used by App\Services\OrderNumberGenerator. Not a
 * customer-facing entity — see docs/DATABASE_SCHEMA.md.
 */
class OrderNumberSequence extends Model
{
    protected $fillable = ['year', 'last_number'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
        ];
    }
}
