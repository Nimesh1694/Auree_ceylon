<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Hardens financial foreign-key relationships by replacing dangerous cascadeOnDelete()
     * constraints with restrictOnDelete() across all financial ledger, transaction, and
     * payout request tables.
     */
    public function up(): void
    {
        // 1. Commission Ledger Entries (Prevent deleting orders or users linked to immutable commissions)
        Schema::table('commission_ledger_entries', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['agent_id']);
            $table->dropForeign(['selling_agent_id']);

            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();
            $table->foreign('agent_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('selling_agent_id')->references('id')->on('users')->restrictOnDelete();
        });

        // 2. Wallet Transactions (Prevent deleting agents linked to append-only wallet transactions)
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);

            $table->foreign('agent_id')->references('id')->on('users')->restrictOnDelete();
        });

        // 3. Payout Requests (Prevent deleting agents linked to financial payout requests)
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);

            $table->foreign('agent_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Reversing this migration would re-introduce dangerous CASCADE deletion on immutable
     * financial records (commissions, wallet transactions, and payout requests).
     *
     * To preserve financial integrity and prevent accidental data loss, rolling back to
     * CASCADE behavior is explicitly blocked. Restoring cascade deletion requires a formal
     * written security override and deliberate, audited execution.
     */
    public function down(): void
    {
        throw new \RuntimeException(
            'Rollback prohibited: Reversing this migration would reintroduce dangerous CASCADE deletion constraints on financial records. Preserving financial ledger integrity requires ON DELETE RESTRICT.'
        );
    }
};

