<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tax is off by default (is_tax_enabled = false). tax_rate_bps is basis
     * points (1500 = 15.00%) rather than a whole percent or a decimal
     * column, so App\Services\CartPricingService never needs a float to
     * represent the rate — consistent with "money as integers" applying to
     * rates too, not just amounts.
     */
    public function up(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table): void {
            $table->boolean('is_tax_enabled')->default(false)->after('is_accepting_orders');
            $table->unsignedInteger('tax_rate_bps')->default(0)->after('is_tax_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table): void {
            $table->dropColumn(['is_tax_enabled', 'tax_rate_bps']);
        });
    }
};
