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
        // 1. Add address to agent_profiles
        if (!Schema::hasColumn('agent_profiles', 'address')) {
            Schema::table('agent_profiles', function (Blueprint $table) {
                $table->text('address')->nullable()->after('nic');
            });
        }

        // 2. Hierarchy Change Requests (Owner approval required)
        Schema::create('hierarchy_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('current_parent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_parent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending')->index(); // pending, approved, rejected
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // 3. Database Notifications table
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('hierarchy_change_requests');

        if (Schema::hasColumn('agent_profiles', 'address')) {
            Schema::table('agent_profiles', function (Blueprint $table) {
                $table->dropColumn('address');
            });
        }
    }
};

