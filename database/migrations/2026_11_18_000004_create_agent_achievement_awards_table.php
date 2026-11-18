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
        Schema::create('agent_achievement_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('achievement_id')->constrained('agent_achievements')->restrictOnDelete();
            $table->foreignId('version_id')->constrained('agent_achievement_versions')->restrictOnDelete();
            $table->string('period_key', 32);
            $table->decimal('achieved_value', 15, 2)->default(0.00);
            $table->decimal('threshold_snapshot', 15, 2)->default(0.00);
            $table->string('status', 32)->default('active');
            $table->boolean('is_discretionary')->default(false);
            $table->foreignId('request_id')->nullable()->constrained('agent_achievement_requests')->nullOnDelete();
            $table->timestamp('awarded_at');
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revocation_reason', 255)->nullable();
            $table->foreignId('correction_of_award_id')->nullable()->constrained('agent_achievement_awards')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['agent_id', 'achievement_id', 'period_key']);
            $table->index(['agent_id', 'status', 'awarded_at']);
            $table->index(['achievement_id', 'period_key']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_achievement_awards');
    }
};

