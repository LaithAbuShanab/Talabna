<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's post-delivery rating. No review schema existed before this
 * task — see docs/API_ORDERS.md. One review per order (unique on
 * order_id), only ever created via App\Http\Controllers\Api\V1\OrderReviewController
 * after confirming the order belongs to the reviewing user and is
 * `delivered` (App\Exceptions\OrderReviewException otherwise).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_reviews');
    }
};
