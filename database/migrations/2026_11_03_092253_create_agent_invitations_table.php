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
        Schema::create('agent_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('invitation_type', 30); // 'high_level_agent', 'low_level_agent'
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('parent_agent_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('candidate_name')->nullable();
            $table->string('candidate_email')->nullable();
            $table->string('candidate_phone', 50)->nullable();
            $table->string('status', 20)->default('pending'); // 'pending', 'accepted', 'expired', 'revoked'
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['created_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_invitations');
    }
};
