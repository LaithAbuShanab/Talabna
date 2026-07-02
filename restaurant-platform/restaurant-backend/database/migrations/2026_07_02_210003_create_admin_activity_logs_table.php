<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A simple, append-only audit trail for sensitive administrative actions
 * (see App\Services\AdminActivityLogger / docs/ADMIN_PANEL.md) — who did
 * what, to which record, when. Not a generic per-model change tracker:
 * only a deliberately small, curated set of events writes here.
 * `user_id` is nullOnDelete (not cascadeOnDelete) so the audit trail
 * outlives the admin account that generated it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};
