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
        Schema::table('users', function (Blueprint $table) {
            $table->string('protected_authority', 30)->nullable()->after('role_id')->index();
        });

        // Populate explicit protected authorities for existing system accounts
        DB::table('users')
            ->where('role', 'owner')
            ->update(['protected_authority' => 'business']);

        DB::table('users')
            ->where('role', 'super_admin')
            ->orWhere('role', 'it_manager')
            ->orWhere('email', 'it@auraluxe.lk')
            ->update(['protected_authority' => 'technical']);

        // Set super_admin role to is_protected = true
        DB::table('roles')
            ->whereIn('name', ['owner', 'super_admin'])
            ->update(['is_protected' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'super_admin')
            ->update(['is_protected' => false]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['protected_authority']);
            $table->dropColumn('protected_authority');
        });
    }
};
