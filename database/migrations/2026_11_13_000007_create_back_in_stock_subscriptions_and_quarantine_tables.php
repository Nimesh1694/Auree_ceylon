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
        // 1. Back-in-stock subscriptions registry
        Schema::create('back_in_stock_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 255);
            $table->string('normalized_email', 255);
            $table->enum('status', ['pending', 'processing', 'notified', 'failed', 'unsubscribed', 'manual_review'])->default('pending');
            $table->boolean('is_active')->nullable()->default(true); // Nullable: allows multiple deactivated/notified historical rows
            $table->string('unsubscribe_token_hash', 64)->unique();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'status', 'created_at'], 'idx_biss_product_status');
            $table->index('email', 'idx_biss_email');
            $table->index('normalized_email', 'idx_biss_normalized_email');
            $table->unique(['product_id', 'normalized_email', 'is_active'], 'uk_biss_product_email_active');
        });

        // 2. Quarantine table for malformed or conflicting bulk imports (Guard 03)
        Schema::create('back_in_stock_quarantined_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('import_batch_id');
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('raw_email', 255)->nullable(); // Nullable: overlength (>255 chars) emails left NULL per Guard 03
            $table->string('normalized_email', 255)->nullable();
            $table->string('quarantine_reason', 255);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('import_batch_id', 'idx_bisq_batch');
            $table->index('product_id', 'idx_bisq_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('back_in_stock_quarantined_records');
        Schema::dropIfExists('back_in_stock_subscriptions');
    }
};

