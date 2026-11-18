<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Expand restock_events status ENUM to include 'processing' and 'exhausted'
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE restock_events 
                MODIFY COLUMN status ENUM(
                    'pending_claims',
                    'claimed',
                    'processing',
                    'completed',
                    'exhausted',
                    'cancelled'
                ) NOT NULL DEFAULT 'pending_claims'
            ");
        } else {
            Schema::table('restock_events', function (Blueprint $table) {
                $table->string('status', 32)->default('pending_claims')->change();
            });
        }

        // 2. Refine restock_event_claims to support multi-attempt historical auditing
        Schema::table('restock_event_claims', function (Blueprint $table) {
            // Add non-unique index to support subscription_id FK before dropping unique constraint (MySQL Error 1553 fix)
            $table->index('subscription_id', 'idx_rec_subscription');

            // Drop legacy global unique constraint that prevented intra-event and multi-event reclaims
            $table->dropUnique('uk_rec_subscription');

            // Add lifecycle status and historical attempt tracking
            $table->string('status', 32)->default('claimed')->after('claim_order');
            $table->unsignedInteger('attempt_number')->default(1)->after('status');
            $table->text('release_reason')->nullable()->after('attempt_number');

            // Unique Constraints:
            // A: Monotonic claim order sequence per event
            $table->unique(['restock_event_id', 'claim_order'], 'uk_rec_event_order');

            // B: Monotonic attempt sequence per subscription within event
            $table->unique(['restock_event_id', 'subscription_id', 'attempt_number'], 'uk_rec_event_sub_attempt');

            // Fast audit index
            $table->index(['restock_event_id', 'status'], 'idx_rec_event_status');
        });

        // 3. Create dedicated ephemeral active lease table (concurrency lock only)
        Schema::create('subscription_active_leases', function (Blueprint $table) {
            // Primary key strictly guarantees at most ONE active lease globally across all events
            $table->foreignId('subscription_id')
                  ->primary()
                  ->constrained('back_in_stock_subscriptions')
                  ->cascadeOnDelete();

            $table->foreignUuid('restock_event_id')
                  ->constrained('restock_events')
                  ->restrictOnDelete();

            $table->foreignId('claim_id')
                  ->constrained('restock_event_claims')
                  ->cascadeOnDelete();

            $table->string('lease_owner', 100);
            $table->string('lease_token', 36);
            $table->timestamp('lease_acquired_at');
            $table->timestamp('lease_expires_at');
            $table->timestamp('heartbeat_at')->nullable();
            $table->unsignedInteger('lease_version')->default(1);
            $table->unsignedSmallInteger('yield_count')->default(0);
            $table->timestamps();

            $table->index(['lease_expires_at', 'lease_version'], 'idx_sal_expiry');
            $table->index('restock_event_id', 'idx_sal_event');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop active lease table
        Schema::dropIfExists('subscription_active_leases');

        // 2. Revert restock_event_claims constraints and columns
        Schema::table('restock_event_claims', function (Blueprint $table) {
            $table->dropUnique('uk_rec_event_order');
            $table->dropUnique('uk_rec_event_sub_attempt');
            $table->dropIndex('idx_rec_event_status');

            $table->dropColumn(['status', 'attempt_number', 'release_reason']);

            // Reinstate legacy global unique constraint (valid on clean/test datasets)
            $table->unique('subscription_id', 'uk_rec_subscription');

            // Drop supporting non-unique index now that unique constraint provides FK support
            $table->dropIndex('idx_rec_subscription');
        });

        // 3. Revert restock_events status ENUM
        if (DB::getDriverName() === 'mysql') {
            DB::table('restock_events')
                ->whereIn('status', ['processing', 'exhausted'])
                ->update(['status' => 'claimed']);

            DB::statement("
                ALTER TABLE restock_events 
                MODIFY COLUMN status ENUM(
                    'pending_claims',
                    'claimed',
                    'completed',
                    'cancelled'
                ) NOT NULL DEFAULT 'pending_claims'
            ");
        } else {
            Schema::table('restock_events', function (Blueprint $table) {
                $table->string('status', 32)->default('pending_claims')->change();
            });
        }
    }
};
