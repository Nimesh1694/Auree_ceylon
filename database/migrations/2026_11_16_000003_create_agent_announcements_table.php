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
        Schema::create('agent_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title_en', 255);
            $table->string('title_si', 255)->nullable();
            $table->text('content_en');
            $table->text('content_si')->nullable();
            $table->enum('target_audience', ['all', 'high_level_agent', 'low_level_agent'])->default('all');
            $table->enum('priority', ['normal', 'important', 'urgent'])->default('normal');
            $table->enum('status', ['draft', 'scheduled', 'published', 'archived'])->default('draft');
            $table->enum('archived_reason', ['manual', 'system_expiry'])->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->unsignedSmallInteger('pin_order')->default(0);
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'target_audience', 'is_pinned', 'pin_order', 'published_at'], 'idx_aa_feed');
            $table->index(['status', 'scheduled_at'], 'idx_aa_scheduled');
            $table->index(['status', 'expires_at'], 'idx_aa_expiry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_announcements');
    }
};

