<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the one-row-per-day uniqueness so a day can have more than one
 * opening period (e.g. lunch + dinner) — "أكثر من فترة في اليوم إن لزم".
 * App\Services\RestaurantAvailabilityService now checks every row for
 * `day_of_week`, not just the first. A day still marked `is_closed` on
 * every one of its rows (or with none at all) behaves exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_hours', function (Blueprint $table): void {
            $table->dropUnique(['day_of_week']);
            $table->index('day_of_week');
        });
    }

    public function down(): void
    {
        Schema::table('business_hours', function (Blueprint $table): void {
            $table->dropIndex(['day_of_week']);
            $table->unique('day_of_week');
        });
    }
};
