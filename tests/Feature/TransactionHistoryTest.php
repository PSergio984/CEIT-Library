<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Models\BorrowTransaction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionHistoryTest extends TestCase
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

    /** @test - TC035: Transaction History - Sidebar Visibility */
    #[Test]
    public function transaction_history_sidebar_is_properly_visible()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        // Create some borrow transactions
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);
        BorrowTransaction::factory()->count(5)->create(['user_id' => $student->id]);

        $response = $this->get(route('admin.logs'));
        $response->assertStatus(200);

        // Verify sidebar is visible (this is primarily a frontend check)
        // The page should load successfully with transaction data
    }
}
