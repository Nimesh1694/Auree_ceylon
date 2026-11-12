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
        // 1. Commission Ledger Entries (Append-only immutable financial records)
        Schema::create('commission_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number', 40)->unique();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete(); // Beneficiary
            $table->foreignId('selling_agent_id')->constrained('users')->cascadeOnDelete(); // Credited seller
            $table->string('commission_type', 40); // direct_high_level, direct_low_level, upline_override, reversal
            $table->string('calculation_base', 40)->default('order_subtotal');
            $table->decimal('base_amount', 12, 2);
            $table->decimal('rate_percentage', 5, 2);
            $table->decimal('calculated_amount', 12, 2);
            $table->string('currency', 3)->default('LKR');
            $table->string('status', 30)->default('pending'); // pending, approved, reversed
            $table->json('rate_snapshot')->nullable();
            $table->foreignId('original_entry_id')->nullable()->constrained('commission_ledger_entries')->nullOnDelete();
            $table->string('reversal_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for lightning query performance & reporting
            $table->index(['agent_id', 'status']);
            $table->index(['order_id', 'status']);
            $table->index('entry_number');
            $table->index('created_at');
        });

        // 2. Commission Rule Change Requests (Owner-approved governance)
        Schema::create('commission_rule_changes', function (Blueprint $table) {
            $table->id();
            $table->string('rule_key', 100);
            $table->string('old_value', 100)->nullable();
            $table->string('new_value', 100);
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending'); // pending, approved, rejected
            $table->text('reason');
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['rule_key', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_rule_changes');
        Schema::dropIfExists('commission_ledger_entries');
    }
};

