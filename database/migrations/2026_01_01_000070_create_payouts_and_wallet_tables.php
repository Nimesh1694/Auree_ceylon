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
        // 1. Add reserved_balance to agent_profiles
        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->decimal('reserved_balance', 12, 2)->default(0.00)->after('wallet_balance');
        });

        // 2. Payout Requests Table
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 40)->unique();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('LKR');
            $table->string('status', 30)->default('pending')->index(); // pending, approved, processing, paid, rejected, cancelled, failed
            $table->string('bank_name', 100);
            $table->string('bank_branch', 100)->nullable();
            $table->string('bank_account_number', 100);
            $table->string('bank_account_name', 150);
            $table->json('bank_snapshot')->nullable();
            $table->text('agent_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('bank_transfer_reference', 100)->nullable()->index();
            $table->string('bank_transfer_receipt_path', 255)->nullable();
            $table->text('bank_transfer_notes')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['agent_id', 'created_at']);
        });

        // 3. Dedicated Wallet Transactions Table (Append-only wallet journal)
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 40)->unique();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->string('transaction_type', 40); // commission_credit, commission_reversal, payout_reserved, payout_released, payout_completed
            $table->decimal('amount', 12, 2); // Signed: + for credit/release, - for reservation/completion/reversal
            $table->decimal('balance_after', 12, 2);
            $table->string('currency', 3)->default('LKR');
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description', 255);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
            $table->index(['agent_id', 'transaction_type']);
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('payout_requests');

        Schema::table('agent_profiles', function (Blueprint $table) {
            $table->dropColumn('reserved_balance');
        });
    }
};

