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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // student, admin, super_admin
            $table->string('display_name'); // Student, Admin, Super Admin
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert default roles
        DB::table('roles')->insert([
            [
                'name' => 'student',
                'display_name' => 'Student',
                'description' => 'Regular student user with basic library access',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'librarian',
                'display_name' => 'Librarian',
                'description' => 'Librarian with QR scanning access and read-only admin dashboard view',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'super_admin',
                'display_name' => 'Super Admin',
                'description' => 'Super administrator with complete system access and ability to manage user roles',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
