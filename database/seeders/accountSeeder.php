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

    public function run(): void
    {
        // Get role IDs
        $superAdminRoleId = \App\Models\Role::query()->where('name', 'super_admin')->value('id');

        if ($superAdminRoleId === null) {
            throw new \RuntimeException('super_admin role must exist before seeding accounts.');
        }

        $superAdminEmail = (string) config('seeding.super_admin.email');
        $superAdminPassword = (string) config('seeding.super_admin.password');

        // Fail fast if email is missing
        if (empty($superAdminEmail)) {
            throw new \RuntimeException('SEED_SUPER_ADMIN_EMAIL must be set in your .env file.');
        }

        // Fail fast if password is missing in production
        if (app()->isProduction() && empty($superAdminPassword)) {
            throw new \RuntimeException('SEED_SUPER_ADMIN_PASSWORD must be set for production seeding.');
        }

        // Use the configured password, or fall back to 'password' only in local/testing
        $finalPassword = !empty($superAdminPassword) 
            ? Hash::make($superAdminPassword) 
            : Hash::make('password');

        // Create or update the ONLY super_admin user
        $this->upsertSeedUser(['email' => $superAdminEmail], [
            'first_name' => 'Janrel',
            'last_name' => 'Motovlogs',
            'password' => $finalPassword,
            'email_verified_at' => now(),
            'role_id' => $superAdminRoleId,
            'credit_score' => 100,
            'account_status' => 'active',
        ]);
    }
}
