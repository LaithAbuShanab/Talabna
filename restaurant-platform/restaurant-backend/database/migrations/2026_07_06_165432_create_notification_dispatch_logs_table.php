<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An append-only idempotency ledger: one row per (event, recipient) push
 * notification that has been dispatched. See App\Models\NotificationDispatchLog
 * and App\Jobs\SendCustomerPushNotificationJob — a job claims its key here
 * before attempting delivery, so a queue-level retry of the same event can
 * never send the same push twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_dispatch_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_dispatch_logs');
    }
};
