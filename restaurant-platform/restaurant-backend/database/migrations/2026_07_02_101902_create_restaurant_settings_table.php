<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row settings table: exactly one restaurant per deployment.
     * The application enforces this via RestaurantSetting::current(),
     * which always reads/creates the row with id = 1.
     */
    public function up(): void
    {
        Schema::create('restaurant_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('restaurant_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('currency_code', 3)->default('JOD');
            $table->unsignedInteger('default_delivery_fee_amount')->default(0);
            $table->unsignedInteger('min_order_amount')->default(0);
            $table->unsignedInteger('default_preparation_minutes')->nullable();
            $table->boolean('is_accepting_orders')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_settings');
    }
};
