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
        Schema::create('agent_achievement_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('achievement_id')->constrained('agent_achievements')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('name', 100);
            $table->text('description');
            $table->string('category', 32);
            $table->string('badge_tier', 32);
            $table->string('metric_type', 32);
            $table->decimal('threshold_value', 15, 2)->default(0.00);
            $table->string('period_type', 32);
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_discretionary')->default(false);
            $table->json('qualification_rules_snapshot')->nullable();
            $table->string('change_reason', 255)->default('Initial definition');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['achievement_id', 'version']);
            $table->index('achievement_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_achievement_versions');
    }
};

