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
        // 1. Expand status ENUM to include 'pending_verification' and 'cancelled'
        if (DB::getDriverName() === 'mysql') {
            DB::statement("
                ALTER TABLE back_in_stock_subscriptions 
                MODIFY COLUMN status ENUM(
                    'pending_verification',
                    'pending',
                    'processing',
                    'notified',
                    'failed',
                    'unsubscribed',
                    'cancelled',
                    'manual_review'
                ) NOT NULL DEFAULT 'pending'
            ");
        } else {
            // For SQLite and non-MySQL drivers, convert to string to expand domain set without check failures
            Schema::table('back_in_stock_subscriptions', function (Blueprint $table) {
                $table->string('status', 32)->default('pending')->change();
            });
        }

        // 2. Add verification, localization, and audit tracking fields
        Schema::table('back_in_stock_subscriptions', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('normalized_email');
            $table->string('verification_token_hash', 64)->nullable()->after('unsubscribe_token_hash');
            $table->timestamp('verification_expires_at')->nullable()->after('verification_token_hash');
            $table->timestamp('verified_at')->nullable()->after('verification_expires_at');

            // Indices for verification lookups and cleanup
            $table->index(['status', 'verification_expires_at'], 'idx_biss_verification');
            $table->unique('verification_token_hash', 'uk_biss_verification_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('back_in_stock_subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_biss_verification');
            $table->dropUnique('uk_biss_verification_token');

            $table->dropColumn([
                'locale',
                'verification_token_hash',
                'verification_expires_at',
                'verified_at',
            ]);
        });

        // Revert ENUM set to original specification
        if (DB::getDriverName() === 'mysql') {
            // Guard: Update any remaining 'pending_verification' or 'cancelled' records before shrinking enum
            DB::table('back_in_stock_subscriptions')
                ->whereIn('status', ['pending_verification', 'cancelled'])
                ->update(['status' => 'failed']);

            DB::statement("
                ALTER TABLE back_in_stock_subscriptions 
                MODIFY COLUMN status ENUM(
                    'pending',
                    'processing',
                    'notified',
                    'failed',
                    'unsubscribed',
                    'manual_review'
                ) NOT NULL DEFAULT 'pending'
            ");
        } else {
            Schema::table('back_in_stock_subscriptions', function (Blueprint $table) {
                $table->string('status', 32)->default('pending')->change();
            });
        }
    }
};
