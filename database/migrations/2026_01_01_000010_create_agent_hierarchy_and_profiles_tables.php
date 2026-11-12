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
        // Closure table for the agent hierarchy
        Schema::create('user_closures', function (Blueprint $table) {
            $table->foreignId('ancestor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('descendant_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('depth')->default(0);

            $table->primary(['ancestor_id', 'descendant_id']);
            $table->index('descendant_id');
            $table->index(['ancestor_id', 'depth']);
            $table->index('depth');
        });

        // Agent profile and payout/KYC details
        Schema::create('agent_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('business_name')->nullable();
            $table->string('nic')->nullable()->index(); // National Identity Card
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('kyc_status')->default('pending')->index(); // pending, verified, rejected
            $table->text('kyc_notes')->nullable();
            $table->decimal('commission_rate_override', 5, 2)->nullable();
            $table->decimal('wallet_balance', 12, 2)->default(0.00);
            $table->decimal('total_earned', 12, 2)->default(0.00);
            $table->decimal('total_withdrawn', 12, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_profiles');
        Schema::dropIfExists('user_closures');
    }
};

