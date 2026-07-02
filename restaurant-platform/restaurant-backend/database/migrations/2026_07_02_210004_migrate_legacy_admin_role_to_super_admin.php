<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * App\Enums\UserRole's single "admin" case was split into
 * super_admin/manager/kitchen/cashier/support (docs/ADMIN_PANEL.md) — any
 * row already stored as the old 'admin' string would otherwise fail to
 * cast to the enum at all (a ValueError on every read). Existing admins
 * become super_admin, the most privileged of the new roles and the
 * closest equivalent to the old undifferentiated "admin".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'admin')->update(['role' => 'super_admin']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'admin']);
    }
};
