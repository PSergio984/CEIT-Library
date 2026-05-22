<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BorrowLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function getRoleId(string $roleName): int
    {
        return Role::where('name', $roleName)->value('id') ?? match ($roleName) {
            'student' => 1,
            'librarian' => 2,
            'admin' => 3,
            'super_admin' => 4,
            default => 1,
        };
    }

    /** @test - TC008: Borrow Logs - Super Admin Full Access */
    #[Test]
    public function super_admin_can_access_borrow_logs_with_full_functionality()
    {
        $superAdmin = User::factory()->create([
            'role_id' => $this->getRoleId('super_admin'),
        ]);

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.borrow-logs'));

        $response->assertStatus(200);
        // Super admin should have full access to borrow logs
        $this->assertTrue($response->status() === 200);
    }
}
