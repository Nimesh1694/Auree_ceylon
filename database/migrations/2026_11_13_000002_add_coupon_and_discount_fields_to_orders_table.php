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
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('postal_code')->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 50)->nullable()->after('coupon_id');
            $table->decimal('discount_amount', 12, 2)->default(0.00)->after('subtotal');
            $table->decimal('shipping_discount_amount', 12, 2)->default(0.00)->after('delivery_charge');

            $table->index('coupon_code', 'idx_orders_coupon_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropIndex('idx_orders_coupon_code');
            $table->dropColumn([
                'coupon_id',
                'coupon_code',
                'discount_amount',
                'shipping_discount_amount',
            ]);
        });
    }
};

