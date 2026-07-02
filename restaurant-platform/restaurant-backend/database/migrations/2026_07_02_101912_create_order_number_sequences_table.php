<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Internal counter backing Order::generateOrderNumber(): one row per
     * year, incremented atomically under a row lock so "ORD-2026-000001"
     * style numbers are gapless-per-request and guaranteed unique even
     * under concurrent order creation. Not a customer-facing entity.
     */
    public function up(): void
    {
        Schema::create('order_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_number_sequences');
    }
};
