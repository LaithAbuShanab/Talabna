<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * product_name/product_price_amount are a snapshot taken at order time,
     * so editing or deleting the product later never changes this row.
     * unit_total_amount = product_price_amount + sum of its selected
     * options' price_delta at that time; line_total_amount = unit_total_amount * quantity.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('product_name');
            $table->unsignedInteger('product_price_amount');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_total_amount');
            $table->unsignedInteger('line_total_amount');

            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
