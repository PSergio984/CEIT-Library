<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminSeedConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_seeder_uses_configured_super_admin_credentials_idempotently(): void
    {
        config()->set('seeding.super_admin.email', 'configured-superadmin@plv.edu.ph');
        config()->set('seeding.super_admin.password', 'ConfiguredPwd@12345');

        $this->seed(AccountSeeder::class);
        $this->seed(AccountSeeder::class);

        $user = User::query()->where('email', 'configured-superadmin@plv.edu.ph')->first();

        $this->assertNotNull($user);
        $this->assertSame(1, User::query()->where('email', 'configured-superadmin@plv.edu.ph')->count());
        $this->assertTrue(Hash::check('ConfiguredPwd@12345', $user->password));
    }

    public function test_database_seeder_can_run_twice_without_duplicate_fixed_accounts(): void
    {
        config()->set('seeding.super_admin.email', 'configured-superadmin@plv.edu.ph');
        config()->set('seeding.super_admin.password', 'ConfiguredPwd@12345');

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'configured-superadmin@plv.edu.ph')->count());
        $this->assertSame(1, User::query()->where('email', 'admin@plv.edu.ph')->count());
        $this->assertSame(1, User::query()->where('email', 'student@plv.edu.ph')->count());
        $this->assertSame(1, User::query()->where('email', 'librarian@plv.edu.ph')->count());
    }
}
