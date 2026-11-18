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
        Schema::create('agent_sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            $table->string('target_month', 7); // Strict YYYY-MM format
            $table->decimal('sales_target', 12, 2);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Unique constraint: exactly one sales target per agent per calendar month
            $table->unique(['agent_id', 'target_month'], 'uk_ast_agent_month');

            // Covering index for company-wide monthly target rollups
            $table->index(['target_month', 'agent_id'], 'idx_ast_month_agent');
        });

        // Enforce positive sales target at DB level in MySQL 8.0+.
        // SQLite ALTER TABLE does not support ADD CONSTRAINT syntax; SQLite relies on Form Request validation.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE agent_sales_targets ADD CONSTRAINT chk_ast_sales_target_positive CHECK (sales_target > 0.00)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_sales_targets');
    }
};

