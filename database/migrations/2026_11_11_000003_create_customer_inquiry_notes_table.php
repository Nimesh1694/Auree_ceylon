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
        Schema::create('customer_inquiry_notes', function (Blueprint $table) {
            $table->id();

            // Parent inquiry reference (cascade on delete cleans up notes if inquiry is purged)
            $table->foreignId('customer_inquiry_id')
                ->constrained('customer_inquiries')
                ->cascadeOnDelete();

            // Authoring staff user (null on delete preserves historical note text if staff user is deleted)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Append-only internal staff note body
            $table->text('note');

            // Timestamps (created_at establishes strict chronological order)
            $table->timestamps();

            // Compound Index for chronologically fetching a ticket's conversation thread
            $table->index(['customer_inquiry_id', 'created_at'], 'idx_inquiry_notes_inquiry_date');

            // Index for author staff activity lookup
            $table->index('user_id', 'idx_inquiry_notes_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_inquiry_notes');
    }
};

