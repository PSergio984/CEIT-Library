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
        // Insert the new admin role between librarian and super_admin
        DB::table('roles')->insert([
            'name' => 'admin',
            'display_name' => 'Admin',
            'description' => 'Administrator with full system access except for promoting users to admin role',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the admin role
        DB::table('roles')->where('name', 'admin')->delete();
    }
};
