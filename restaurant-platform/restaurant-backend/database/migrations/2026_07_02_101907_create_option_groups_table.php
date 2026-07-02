<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * e.g. "Size" (selection_type = single) or "Extra Toppings" (multiple).
     * Whether a group is required is a per-product decision — see
     * product_option_groups.is_required, not stored here.
     */
    public function up(): void
    {
        Schema::create('option_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('selection_type');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_groups');
    }
};
