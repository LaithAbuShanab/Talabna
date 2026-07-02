<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schema readiness only: a future task may let customers schedule an
     * order for later when the restaurant is currently closed. This column
     * exists so that decision has somewhere to live, but
     * App\Actions\CreateOrderAction does not implement scheduled ordering —
     * it still rejects every order while the restaurant is closed,
     * regardless of this flag. See docs/DATABASE_SCHEMA.md.
     */
    public function up(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table): void {
            $table->boolean('allows_scheduled_orders')->default(false)->after('is_accepting_orders');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table): void {
            $table->dropColumn('allows_scheduled_orders');
        });
    }
};
