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
        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->unsignedInteger('unit_index');
            $table->unsignedBigInteger('refund_cents'); // Hardened to 64-bit integer cents (Directive 4.2)
            $table->decimal('refund_amount', 12, 2);
            $table->string('reason', 255);
            $table->foreignId('processed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // Composite foreign key enforcing that the item belongs to the referenced order (Directive 4.3)
            $table->foreign(['order_id', 'order_item_id'], 'fk_or_order_item_composite')
                ->references(['order_id', 'id'])
                ->on('order_items')
                ->restrictOnDelete();

            $table->index(['order_id', 'order_item_id'], 'idx_or_order_item');
            $table->unique(['order_item_id', 'unit_index'], 'uk_or_item_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_refunds');
    }
};

