<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add role_id column
            $table->foreignId('role_id')->after('id')->default(1)->constrained('roles')->onDelete('restrict');
        });

        // Migrate existing data: users with is_admin = 1 become super_admin, others become student
        $superAdminRoleId = DB::table('roles')->where('name', 'super_admin')->value('id');
        $studentRoleId = DB::table('roles')->where('name', 'student')->value('id');

        DB::table('users')
            ->where('is_admin', 1)
            ->update(['role_id' => $superAdminRoleId]);

        DB::table('users')
            ->where('is_admin', 0)
            ->update(['role_id' => $studentRoleId]);

        // Remove the old is_admin column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add back is_admin column
            $table->boolean('is_admin')->default(false)->after('id');
        });

        // Restore data: super_admin role becomes is_admin = 1
        $superAdminRoleId = DB::table('roles')->where('name', 'super_admin')->value('id');

        DB::table('users')
            ->where('role_id', $superAdminRoleId)
            ->update(['is_admin' => 1]);

        // Remove role_id column
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};
