<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OrderNumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Generates customer-facing order numbers like "ORD-2026-000001", with
 * uniqueness guaranteed under concurrent order creation — not just usually
 * unique. See docs/DATABASE_SCHEMA.md "Order number generation & uniqueness".
 *
 * A dedicated class (not a static Order method) so App\Actions\CreateOrderAction
 * and App\Models\Order both depend on it through the container and it can be
 * swapped/mocked independently of Eloquent.
 */
class OrderNumberGenerator
{
    /**
     * Atomically reserves the next number for the current year under a row
     * lock on the per-year counter, so two concurrent calls can never
     * receive the same number.
     */
    public function generate(): string
    {
        return DB::transaction(function (): string {
            $year = (int) now()->year;

            $sequence = OrderNumberSequence::query()
                ->lockForUpdate()
                ->firstOrCreate(['year' => $year]);

            $sequence->increment('last_number');

            return sprintf('ORD-%d-%06d', $year, $sequence->last_number);
        });
    }
}
