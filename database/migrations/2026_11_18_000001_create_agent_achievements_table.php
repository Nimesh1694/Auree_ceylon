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
        Schema::create('agent_achievements', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 100);
            $table->text('description');
            $table->string('category', 32);
            $table->string('badge_tier', 32);
            $table->string('badge_icon', 100)->default('award');
            $table->string('metric_type', 32);
            $table->decimal('threshold_value', 15, 2)->default(0.00);
            $table->string('period_type', 32);
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_discretionary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('current_version')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_achievements');
    }
};

