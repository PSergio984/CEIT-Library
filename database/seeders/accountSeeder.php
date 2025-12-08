<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;

class accountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get role IDs
        $superAdminRoleId = \App\Models\Role::where('name', 'super_admin')->value('id') ?? 4;
        $adminRoleId = \App\Models\Role::where('name', 'admin')->value('id') ?? 3;
        $librarianRoleId = \App\Models\Role::where('name', 'librarian')->value('id') ?? 2;
        $studentRoleId = \App\Models\Role::where('name', 'student')->value('id') ?? 1;

        // Ensure test users are unique by deleting any existing ones
        User::where('email', 'sampleadmin@plv.edu.ph')->delete();
        User::where('email', 'librarian@plv.edu.ph')->delete();

        // Create the ONLY super_admin user
        $superAdmin = User::factory()->create([
            'first_name' => 'Janrel',
            'last_name' => 'Motovlogs',
            'email' => 'superadmin@plv.edu.ph',
            'role_id' => $superAdminRoleId,
            'password' => bcrypt('Pwd@12345'),
        ]);
    }
}