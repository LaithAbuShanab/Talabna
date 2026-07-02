<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets a client-generated key (e.g. a UUID created once per checkout
     * attempt) make retries safe: App\Actions\CreateOrderAction returns the
     * already-created order instead of creating a second one when the same
     * (user_id, idempotency_key) pair is seen again — see
     * docs/DATABASE_SCHEMA.md "Order creation".
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('idempotency_key')->nullable()->after('order_number');
            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
