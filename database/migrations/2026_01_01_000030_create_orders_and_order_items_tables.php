<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique()->index();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone')->index();
            $table->string('customer_email')->nullable();
            $table->text('shipping_address');
            $table->string('district')->index();
            $table->string('city');
            $table->string('postal_code')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('delivery_charge', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2);
            $table->string('payment_method')->default('cod'); // All orders are COD
            $table->string('status')->default('placed')->index(); // placed, confirmed, packed, shipped, delivered, cash_collected, completed, cancelled, refused, returned
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete(); // Agent who placed the order (if any)
            $table->string('referral_code')->nullable()->index(); // Referral code attributed via cookie (if any)
            $table->string('tracking_token', 64)->unique(); // Secure random token for verification
            $table->text('notes')->nullable();
            $table->timestamp('placed_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name'); // Immutable snapshot of product name
            $table->string('product_sku')->nullable(); // Immutable snapshot of SKU
            $table->decimal('unit_price', 12, 2); // Immutable snapshot of unit price
            $table->integer('quantity');
            $table->decimal('subtotal', 12, 2); // unit_price * quantity
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->string('courier_name')->nullable();
            $table->string('tracking_number')->nullable()->index();
            $table->string('status')->default('pending')->index(); // pending, assigned, in_transit, delivered, failed, returned
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};

