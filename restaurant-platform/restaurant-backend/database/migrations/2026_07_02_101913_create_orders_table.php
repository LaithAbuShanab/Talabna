<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No soft deletes here: orders are financial/transactional records and
     * are never deleted. delivery_address_* columns are a snapshot of the
     * customer_address at order time (see docs/DATABASE_SCHEMA.md) so a
     * later edit/deletion of that address can never change a past order.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->string('delivery_type');
            $table->string('payment_method');
            $table->string('payment_status')->default('pending');

            $table->unsignedInteger('subtotal_amount');
            $table->unsignedInteger('discount_amount')->default(0);
            $table->unsignedInteger('delivery_fee_amount')->default(0);
            $table->unsignedInteger('total_amount');

            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_address_id')->nullable()->constrained()->nullOnDelete();

            $table->string('delivery_address_line')->nullable();
            $table->string('delivery_city')->nullable();
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();

            $table->text('customer_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('expected_delivery_at')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('payment_status');
            $table->index('delivery_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
