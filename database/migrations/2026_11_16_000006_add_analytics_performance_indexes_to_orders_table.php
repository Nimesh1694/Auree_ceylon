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
            // Covering index for Direct Agent orders filtered by placed_at and status
            $table->index(['agent_id', 'status', 'placed_at'], 'idx_orders_agent_status_placed');

            // Covering index for Cookie Referral orders filtered by placed_at and status
            $table->index(['referral_code', 'status', 'placed_at'], 'idx_orders_ref_status_placed');

            // Index for company-wide admin aggregations across placed_at and status
            $table->index(['status', 'placed_at'], 'idx_orders_status_placed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_agent_status_placed');
            $table->dropIndex('idx_orders_ref_status_placed');
            $table->dropIndex('idx_orders_status_placed');
        });
    }
};

