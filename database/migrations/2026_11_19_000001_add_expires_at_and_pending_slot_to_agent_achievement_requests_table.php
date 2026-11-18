<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_achievement_requests', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('period_key');
        });

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement("
                ALTER TABLE agent_achievement_requests 
                ADD COLUMN active_request_slot VARCHAR(128) 
                GENERATED ALWAYS AS (
                    CASE WHEN status = 'pending' 
                         THEN agent_id || '-' || achievement_id || '-' || COALESCE(period_key, 'lifetime') 
                         ELSE NULL 
                    END
                ) VIRTUAL
            ");
        } else {
            DB::statement("
                ALTER TABLE agent_achievement_requests 
                ADD COLUMN active_request_slot VARCHAR(128) 
                GENERATED ALWAYS AS (
                    CASE WHEN status = 'pending' 
                         THEN CONCAT(agent_id, '-', achievement_id, '-', COALESCE(period_key, 'lifetime')) 
                         ELSE NULL 
                    END
                ) VIRTUAL
            ");
        }

        Schema::table('agent_achievement_requests', function (Blueprint $table) {
            $table->unique('active_request_slot', 'uq_pending_achievement_request');
            $table->index(['status', 'expires_at'], 'idx_requests_status_expires');
        });

        // Legacy record backfill
        if ($driver === 'sqlite') {
            DB::statement("
                UPDATE agent_achievement_requests 
                SET expires_at = datetime(created_at, '+30 days') 
                WHERE status = 'pending' AND expires_at IS NULL
            ");
        } else {
            DB::statement("
                UPDATE agent_achievement_requests 
                SET expires_at = DATE_ADD(created_at, INTERVAL 30 DAY) 
                WHERE status = 'pending' AND expires_at IS NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_achievement_requests', function (Blueprint $table) {
            $table->dropIndex('idx_requests_status_expires');
            $table->dropUnique('uq_pending_achievement_request');
            $table->dropColumn(['expires_at', 'active_request_slot']);
        });
    }
};

