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
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->default(0.00)->after('subtotal');
            $table->json('unit_refund_schedule')->nullable()->after('discount_amount');
            $table->json('refunded_unit_indexes')->nullable()->after('unit_refund_schedule');
            $table->integer('returned_quantity')->default(0)->after('quantity');
            $table->decimal('refunded_amount', 12, 2)->default(0.00)->after('returned_quantity');

            // Composite unique key to guarantee order/item relationship integrity for refunds (Directive 4.3)
            $table->unique(['order_id', 'id'], 'uk_oi_order_item_composite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropUnique('uk_oi_order_item_composite');
            $table->dropColumn([
                'discount_amount',
                'unit_refund_schedule',
                'refunded_unit_indexes',
                'returned_quantity',
                'refunded_amount',
            ]);
        });
    }
};

