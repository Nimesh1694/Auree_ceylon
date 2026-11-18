<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('status', 20)->default('open');
            $table->integer('stock_at_trigger');
            $table->integer('threshold_at_trigger');
            $table->integer('current_stock');
            $table->string('trigger_source', 50);
            $table->timestamp('opened_at');
            $table->timestamp('last_notified_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('acknowledgement_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_type', 30)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['status', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};

