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
        Schema::create('order_notifications', function (Blueprint $table) {
            $table->id();
            
            // Order Foreign Key with Safe History Retention Strategy (nullOnDelete)
            // Even if an order record is deleted, notification history remains intact.
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            
            // Immutable snapshot of order reference
            $table->string('order_number', 50)->index();
            
            // Notification Classification & Recipient
            $table->string('notification_type', 50)->index(); // order_placed, order_confirmed, order_shipped, order_delivered, order_completed, order_cancelled, order_returned
            $table->string('recipient_type', 20)->default('customer'); // customer, agent, admin
            $table->string('recipient_email', 255)->index();
            
            // Delivery Engine & Execution Status
            $table->string('mailer', 50)->nullable(); // smtp, log, array, etc.
            $table->string('status', 30)->default('pending')->index(); // pending, sent, failed, skipped
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_details')->nullable();
            $table->string('provider_message_id', 255)->nullable();
            
            // Timestamps
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Logical Unique Identity Constraint to prevent duplicate emails
            $table->unique(['order_number', 'notification_type', 'recipient_email'], 'uk_order_email_notif');
            
            // Compound index for status queries & reporting
            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_notifications');
    }
};

