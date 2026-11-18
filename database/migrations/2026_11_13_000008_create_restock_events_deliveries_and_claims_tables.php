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
        // 1. Restock events ledger
        Schema::create('restock_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('previous_stock');
            $table->integer('new_stock');
            $table->integer('restocked_quantity');
            $table->integer('batch_limit');
            $table->integer('claimed_count')->default(0);
            $table->enum('status', ['pending_claims', 'claimed', 'completed', 'cancelled'])->default('pending_claims');
            $table->timestamps();

            $table->index(['product_id', 'status'], 'idx_re_product_status');
        });

        // 2. Restock event claims (Optimistic worker lease concurrency)
        Schema::create('restock_event_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('restock_event_id')->constrained('restock_events')->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('back_in_stock_subscriptions')->cascadeOnDelete();
            $table->integer('claim_order');
            $table->string('lease_owner', 100)->nullable();
            $table->string('lease_token', 36)->nullable();
            $table->timestamp('lease_acquired_at')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->unsignedInteger('lease_version')->default(1);
            $table->timestamp('created_at')->useCurrent();

            // Retained global unique constraint to prevent simultaneous cross-event claims (Directive 4.1)
            $table->unique('subscription_id', 'uk_rec_subscription');
            $table->index(['restock_event_id', 'subscription_id'], 'idx_rec_event_subscription');
            $table->index(['lease_expires_at', 'lease_version'], 'idx_rec_lease');
        });

        // 3. Dedicated logical delivery entities
        Schema::create('back_in_stock_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('back_in_stock_subscriptions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('restock_event_id')->constrained('restock_events')->cascadeOnDelete();
            $table->string('logical_delivery_key', 64)->unique();
            $table->enum('status', ['initiated', 'provider_accepted', 'provider_rejected', 'failed', 'manual_review'])->default('initiated');
            $table->timestamps();

            $table->unique(['subscription_id', 'restock_event_id'], 'uk_bisd_sub_event');
        });

        // 4. Physical delivery attempts and notification logs
        Schema::create('back_in_stock_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('back_in_stock_deliveries')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_number')->default(1);
            $table->string('idempotency_key', 64)->unique();
            $table->enum('status', ['initiated', 'provider_accepted', 'provider_rejected', 'failed'])->default('initiated');
            $table->string('provider_message_id', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->unique(['delivery_id', 'attempt_number'], 'uk_bisnl_delivery_attempt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('back_in_stock_notification_logs');
        Schema::dropIfExists('back_in_stock_deliveries');
        Schema::dropIfExists('restock_event_claims');
        Schema::dropIfExists('restock_events');
    }
};

