<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optionally restricts a coupon to specific categories/products
 * ("فئات أو منتجات محددة اختياريًا"). A coupon with no rows in either table
 * is unrestricted (applies cart-wide, the existing/original behavior) —
 * see App\Services\CartPricingService::applyCoupon(). Plain pivots, no
 * extra columns: membership is all that matters here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['coupon_id', 'category_id']);
        });

        Schema::create('coupon_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['coupon_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_products');
        Schema::dropIfExists('coupon_categories');
    }
};
