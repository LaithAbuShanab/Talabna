<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `status` and `created_at` were already separately indexed, but nearly
 * every admin Dashboard aggregation (docs/ADMIN_DASHBOARD.md) filters by
 * both together (e.g. "delivered orders created this week") — a composite
 * index lets the query planner use one index instead of intersecting two,
 * which matters once the `orders` table is large enough for "avoid heavy
 * queries" to be a real concern, not just an aspiration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['status', 'created_at']);
        });
    }
};
