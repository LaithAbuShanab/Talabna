<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional per-product overrides for how many values may be selected
     * from an attached option group. Left nullable on purpose: when null,
     * App\Services\CartPricingService falls back to sensible defaults
     * derived from is_required + the group's selection_type (single/multiple)
     * — see docs/DATABASE_SCHEMA.md. This keeps every existing seeded row
     * valid with no backfill needed.
     */
    public function up(): void
    {
        Schema::table('product_option_groups', function (Blueprint $table): void {
            $table->unsignedTinyInteger('min_select')->nullable()->after('is_required');
            $table->unsignedTinyInteger('max_select')->nullable()->after('min_select');
        });
    }

    public function down(): void
    {
        Schema::table('product_option_groups', function (Blueprint $table): void {
            $table->dropColumn(['min_select', 'max_select']);
        });
    }
};
