<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_supplier', function (Blueprint $table) {
            $table->unsignedBigInteger('preferred_product_id')
                ->nullable()
                ->unique()
                ->after('is_preferred');
        });

        // Preserve any existing preferred supplier rows safely.
        DB::table('product_supplier')
            ->where('is_preferred', true)
            ->update(['preferred_product_id' => DB::raw('product_id')]);
    }

    public function down(): void
    {
        Schema::table('product_supplier', function (Blueprint $table) {
            $table->dropUnique(['preferred_product_id']);
            $table->dropColumn('preferred_product_id');
        });
    }
};

