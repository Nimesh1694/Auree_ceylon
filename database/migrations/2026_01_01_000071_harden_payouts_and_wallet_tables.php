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
        // 1. Add unique constraint and failure audit tracking to payout_requests
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->unique('bank_transfer_reference');
            $table->foreignId('failed_by')->nullable()->constrained('users')->nullOnDelete()->after('paid_at');
            $table->timestamp('failed_at')->nullable()->after('failed_by');
            $table->text('failure_reason')->nullable()->after('failed_at');
        });

        // 2. Add available_balance_after to wallet_transactions for explicit ledger dual-balance reconciliation
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->decimal('available_balance_after', 12, 2)->default(0.00)->after('balance_after');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropColumn('available_balance_after');
        });

        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropForeign(['failed_by']);
            $table->dropColumn(['failed_by', 'failed_at', 'failure_reason']);
            $table->dropUnique(['bank_transfer_reference']);
        });
    }
};

