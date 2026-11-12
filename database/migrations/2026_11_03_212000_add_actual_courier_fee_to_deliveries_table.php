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
        Schema::table('deliveries', function (Blueprint $table) {
            $table->decimal('actual_courier_fee', 12, 2)->nullable()->after('status');
            $table->foreignId('courier_fee_recorded_by')->nullable()->after('actual_courier_fee')->constrained('users')->nullOnDelete();
            $table->timestamp('courier_fee_recorded_at')->nullable()->after('courier_fee_recorded_by');
            $table->string('courier_invoice_reference', 100)->nullable()->after('courier_fee_recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['courier_fee_recorded_by']);
            }
            $table->dropColumn([
                'actual_courier_fee',
                'courier_fee_recorded_by',
                'courier_fee_recorded_at',
                'courier_invoice_reference',
            ]);
        });
    }
};

