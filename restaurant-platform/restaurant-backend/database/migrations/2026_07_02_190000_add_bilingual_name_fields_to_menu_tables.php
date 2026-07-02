<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bilingual menu content: adds nullable `name_ar`/`description_ar` columns
 * alongside the existing `name`/`description` (kept exactly as-is, still
 * treated as the English/default value everywhere else in the codebase —
 * App\Services\CartPricingService, App\Actions\CreateOrderAction, and the
 * order_items/order_item_options snapshot columns all keep reading a plain
 * string from `name`, unaffected by this migration). This is the
 * "separate columns" option from the two documented in docs/API_MENU.md,
 * chosen over converting `name`/`description` to JSON specifically to
 * avoid touching any of that already-tested business logic. See
 * docs/API_MENU.md's "Bilingual content" section.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('name_ar')->nullable()->after('name');
            $table->text('description_ar')->nullable()->after('description');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('name_ar')->nullable()->after('name');
            $table->text('description_ar')->nullable()->after('description');
        });

        Schema::table('option_groups', function (Blueprint $table): void {
            $table->string('name_ar')->nullable()->after('name');
        });

        Schema::table('option_values', function (Blueprint $table): void {
            $table->string('name_ar')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['name_ar', 'description_ar']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['name_ar', 'description_ar']);
        });

        Schema::table('option_groups', function (Blueprint $table): void {
            $table->dropColumn('name_ar');
        });

        Schema::table('option_values', function (Blueprint $table): void {
            $table->dropColumn('name_ar');
        });
    }
};
