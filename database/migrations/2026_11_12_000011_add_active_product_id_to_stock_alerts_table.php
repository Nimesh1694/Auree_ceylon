<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_alerts', function (Blueprint $table) {
            $table->unsignedBigInteger('active_product_id')
                ->nullable()
                ->unique()
                ->after('product_id');
        });

        DB::table('stock_alerts')
            ->whereIn('status', ['open', 'acknowledged'])
            ->update(['active_product_id' => DB::raw('product_id')]);
    }

    public function down(): void
    {
        Schema::table('stock_alerts', function (Blueprint $table) {
            $table->dropUnique(['active_product_id']);
            $table->dropColumn('active_product_id');
        });
    }
};

