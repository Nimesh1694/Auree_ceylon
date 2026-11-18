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
        Schema::create('agent_achievement_evaluation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_type', 32);
            $table->string('period_type', 32);
            $table->string('period_key', 32);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 32)->default('running');
            $table->unsignedInteger('agents_evaluated')->default(0);
            $table->unsignedInteger('awards_created')->default(0);
            $table->unsignedInteger('streaks_updated')->default(0);
            $table->text('error_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['period_key', 'status']);
            $table->index(['run_type', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_achievement_evaluation_runs');
    }
};

