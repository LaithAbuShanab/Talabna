<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `is_active` is distinct from the existing `is_available`: `is_active`
 * is the admin's "publish/unpublish this product entirely" toggle (same
 * meaning as Category.is_active — an inactive product is treated as if it
 * doesn't exist for customers, same as an inactive category already is),
 * while `is_available` stays the quicker "temporarily out of stock"
 * toggle a kitchen might flip several times a day. Both gate visibility —
 * see App\Services\{MenuCacheService,CartPricingService}.
 *
 * `preparation_minutes` is informational metadata for admins (e.g. "this
 * pizza takes ~20 minutes") — nullable, no automatic calculation reads it
 * yet (App\Services\OrderStatusTransitionService still takes an explicit
 * estimatedPreparationMinutes per order from the admin accepting it); a
 * natural integration point for later, not built out now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('category_id');
            $table->unsignedInteger('preparation_minutes')->nullable()->after('sort_order');

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['is_active']);
            $table->dropColumn(['is_active', 'preparation_minutes']);
        });
    }
};
