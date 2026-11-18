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
        Schema::create('recently_viewed_merge_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->char('idempotency_key', 36);
            $table->char('payload_hash', 64)->comment('SHA-256 hex of canonical client payload');
            $table->unsignedSmallInteger('merged_count')->default(0);
            $table->timestamp('created_at')->useCurrent();

            // Customer-scoped composite unique constraint (CRITICAL-02)
            $table->unique(['customer_id', 'idempotency_key'], 'uk_rvmr_customer_idempotency');
            $table->index('created_at', 'idx_rvmr_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recently_viewed_merge_records');
    }
};

