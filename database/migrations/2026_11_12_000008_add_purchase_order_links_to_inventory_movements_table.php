<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')
                ->nullable()
                ->after('order_id')
                ->constrained('purchase_orders')
                ->nullOnDelete();
            $table->foreignId('purchase_order_receipt_id')
                ->nullable()
                ->after('purchase_order_id')
                ->constrained('purchase_order_receipts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_receipt_id');
            $table->dropConstrainedForeignId('purchase_order_id');
        });
    }
};

