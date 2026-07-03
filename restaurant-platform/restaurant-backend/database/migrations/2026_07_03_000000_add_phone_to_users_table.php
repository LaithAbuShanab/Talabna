<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable, no uniqueness constraint: this is a contact number for staff to
 * call about an order (search/display on the new admin Orders screen — see
 * docs/ADMIN_ORDERS.md), not an authentication factor. Customers set it
 * themselves via the existing profile-update endpoint
 * (App\Http\Requests\Api\V1\Profile\UpdateProfileRequest); nothing requires
 * it to be present, since plenty of existing accounts predate this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });
    }
};
