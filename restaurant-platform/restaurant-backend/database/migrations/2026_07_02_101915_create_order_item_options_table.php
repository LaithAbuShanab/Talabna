<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot of a selected option at order time (group name, value name,
     * price delta) so later edits to option_groups/option_values never
     * change a past order.
     */
    public function up(): void
    {
        Schema::create('order_item_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_value_id')->nullable()->constrained()->nullOnDelete();

            $table->string('option_group_name');
            $table->string('option_value_name');
            $table->integer('price_delta_amount');

            $table->timestamps();

            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_options');
    }
};
