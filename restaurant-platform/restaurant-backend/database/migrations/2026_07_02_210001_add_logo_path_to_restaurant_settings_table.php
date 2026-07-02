<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the restaurant's logo be uploaded/changed from the admin panel
 * (App\Filament\Pages\ManageRestaurantSettings) rather than only via a
 * hardcoded asset — see docs/ADMIN_PANEL.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table): void {
            $table->string('logo_path')->nullable()->after('restaurant_name');
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_settings', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
