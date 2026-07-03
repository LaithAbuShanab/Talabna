<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pairs with the existing `is_active` column: blocking a customer sets
 * `is_active = false` and records why here. Excluded from User's
 * #[Fillable(...)] like `is_active` itself — only ever written through
 * App\Services\CustomerBlockingService, never raw mass assignment. See
 * docs/ADMIN_CUSTOMERS.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('blocked_reason')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('blocked_reason');
        });
    }
};
