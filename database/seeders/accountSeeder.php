<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AccountSeeder extends Seeder
{
    protected function upsertSeedUser(array $attributes, array $values): User
    {
        $user = User::query()->firstOrNew($attributes);
        $user->forceFill($values);
        $user->save();

        return $user;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get role IDs
        $superAdminRoleId = Role::query()->where('name', 'super_admin')->value('id');

        if ($superAdminRoleId === null) {
            throw new \RuntimeException('super_admin role must exist before seeding accounts.');
        }
        $superAdminEmail = (string) config('seeding.super_admin.email');
        $superAdminPassword = (string) config('seeding.super_admin.password');

        if (trim($superAdminEmail) === '' || trim($superAdminPassword) === '') {
            throw new \RuntimeException('Super admin email and password must be configured before seeding accounts.');
        }

        // Create the ONLY super_admin user
        $this->upsertSeedUser(['email' => $superAdminEmail], [
            'first_name' => 'Janrel',

            'password' => Hash::make($superAdminPassword),
            'email' => $superAdminEmail,
            'email_verified_at' => now(),
            'role_id' => $superAdminRoleId,

            'remember_token' => null,
            'credit_score' => 100,
            'account_status' => 'active',
        ]);
    }
}
