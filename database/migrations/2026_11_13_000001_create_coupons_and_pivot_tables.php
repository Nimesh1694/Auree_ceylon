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
        // 1. Coupons definition table
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->enum('type', ['percentage', 'fixed_amount', 'free_shipping']);
            $table->decimal('value', 12, 2)->default(0.00);
            $table->decimal('min_order_subtotal', 12, 2)->default(0.00);
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->boolean('is_unlimited_cap')->default(false);
            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->enum('applies_to', ['all', 'specific_categories', 'specific_products'])->default('all');
            $table->boolean('allow_agent_referral')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['code', 'is_active'], 'idx_coupons_code_active');
            $table->index(['starts_at', 'expires_at'], 'idx_coupons_dates');
        });

        // 2. Coupon category restrictions pivot table
        Schema::create('coupon_categories', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->primary(['coupon_id', 'category_id']);
        });

        // 3. Coupon product restrictions pivot table
        Schema::create('coupon_products', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->primary(['coupon_id', 'product_id']);
        });

        // 4. Coupon usages tracking table (Financial audit trail with ON DELETE RESTRICT)
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_email', 255);
            $table->string('customer_phone', 30);
            $table->decimal('discount_amount', 12, 2)->default(0.00);
            $table->decimal('shipping_discount_amount', 12, 2)->default(0.00);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('restored_at')->nullable();

            $table->index(['coupon_id', 'customer_id'], 'idx_cu_coupon_customer');
            $table->index(['coupon_id', 'customer_email'], 'idx_cu_coupon_email');
            $table->index(['coupon_id', 'customer_phone'], 'idx_cu_coupon_phone');
            $table->unique(['order_id', 'coupon_id'], 'uk_cu_order_coupon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupon_products');
        Schema::dropIfExists('coupon_categories');
        Schema::dropIfExists('coupons');
    }
};

