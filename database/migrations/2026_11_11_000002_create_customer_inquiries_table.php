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
        Schema::create('customer_inquiries', function (Blueprint $table) {
            $table->id();

            // Unique human-readable inquiry tracking identifier (e.g., INQ-20261111-48291)
            $table->string('inquiry_number', 32)->unique();

            // Nullable relationship to registered customer (null for guest inquiries)
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();

            // Submitter Contact Details
            $table->string('name', 100);
            $table->string('email', 255)->index();
            $table->string('phone', 30)->nullable();

            // Inquiry Content
            $table->string('subject', 200);
            $table->text('message');

            // Lifecycle & Triage
            $table->string('status', 30)->default('new')->index(); // 'new', 'in_progress', 'resolved', 'closed'
            $table->string('priority', 20)->default('normal')->index(); // 'low', 'normal', 'high', 'urgent'

            // Staff Assignment & Resolution
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            // Notification Delivery Telemetry (Email-only)
            $table->string('notification_status', 30)->default('pending')->index(); // 'pending', 'sent', 'failed', 'skipped'
            $table->text('notification_error')->nullable();

            // Submitter Client Diagnostic Metadata
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            // Compound Performance Indexes
            $table->index(['status', 'created_at'], 'idx_inquiries_status_date');
            $table->index(['customer_id', 'created_at'], 'idx_inquiries_customer_date');
            $table->index(['assigned_to', 'status'], 'idx_inquiries_assigned_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_inquiries');
    }
};

