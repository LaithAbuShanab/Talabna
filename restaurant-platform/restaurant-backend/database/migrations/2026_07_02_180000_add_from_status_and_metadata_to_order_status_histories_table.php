<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `from_status` makes the transition explicit per row (nullable: the
     * very first "pending" row on order creation has no prior state).
     * `metadata` is optional, free-form JSON context for the transition
     * (e.g. a rejection sub-reason code, a delivery driver id) — see
     * App\Services\OrderStatusTransitionService / docs/ORDER_LIFECYCLE.md.
     * Still append-only: these columns are set once, at insert time, same
     * as every other column on this table.
     */
    public function up(): void
    {
        Schema::table('order_status_histories', function (Blueprint $table): void {
            $table->string('from_status')->nullable()->after('order_id');
            $table->json('metadata')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('order_status_histories', function (Blueprint $table): void {
            $table->dropColumn(['from_status', 'metadata']);
        });
    }
};
