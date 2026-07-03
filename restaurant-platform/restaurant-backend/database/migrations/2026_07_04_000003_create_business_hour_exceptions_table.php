<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "استثناءات العطل الرسمية" — one row per calendar date that overrides the
 * regular day-of-week business_hours for that specific date (e.g. a public
 * holiday). `is_closed = true` closes the restaurant entirely that day;
 * otherwise `opens_at`/`closes_at` replace the normal hours for that date.
 * Deliberately simple: a flat date-keyed table, no recurrence rules — see
 * App\Services\RestaurantAvailabilityService, which checks this table
 * first and only falls back to the regular weekly schedule when no
 * exception exists for today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_hour_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->boolean('is_closed')->default(true);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hour_exceptions');
    }
};
