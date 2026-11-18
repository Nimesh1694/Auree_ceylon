<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('supplier_sku', 100)->nullable();
            $table->decimal('unit_cost', 12, 2);
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->unsignedInteger('min_order_qty')->default(1);
            $table->boolean('is_preferred')->default(false);
            $table->timestamp('last_purchased_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'supplier_id']);
            $table->index(['product_id', 'is_preferred']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_supplier');
    }
};

