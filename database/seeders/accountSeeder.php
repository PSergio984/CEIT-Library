<?php

namespace Database\Seeders;

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
        $superAdminRoleId = \App\Models\Role::query()->where('name', 'super_admin')->value('id');

        if ($superAdminRoleId === null) {
            throw new \RuntimeException('super_admin role must exist before seeding accounts.');
        }
        $superAdminEmail = (string) config('seeding.super_admin.email');
        $superAdminPassword = (string) config('seeding.super_admin.password');

        // Create the ONLY super_admin user
        $this->upsertSeedUser(['email' => $superAdminEmail], [
            'first_name' => 'Janrel',
            'last_name' => 'Motovlogs',
            'password' => $superAdminPassword ? Hash::make($superAdminPassword) : Hash::make('password'),
            'email_verified_at' => now(),
            'role_id' => $superAdminRoleId,
            'credit_score' => 100,
            'account_status' => 'active',
        ]);
    }
}
