<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Librarian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RoleBasedAccessControlTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_access_librarian_assignment_page()
    {
        $admin = User::factory()->create(['role_id' => 3]);

        $response = $this->actingAs($admin)
            ->get(route('admin.librarians'));

        $response->assertStatus(200);
    }

    /** @test */
    public function librarian_cannot_access_librarian_assignment_page()
    {
        $user = User::factory()->create(['role_id' => 1]);

        // Create active librarian duty
        Librarian::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.librarians'));

        $response->assertStatus(403);
    }

    /** @test */
    public function student_cannot_access_librarian_assignment_page()
    {
        $student = User::factory()->create(['role_id' => 1]);

        $response = $this->actingAs($student)
            ->get(route('admin.librarians'));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_access_test_qr_page()
    {
        if (config('app.env') === 'production') {
            $this->markTestSkipped('Test QR route not available in production');
        }

        $admin = User::factory()->create(['role_id' => 3]);

        $response = $this->actingAs($admin)
            ->get(route('test-qr'));

        $response->assertStatus(200);
    }

    /** @test */
    public function active_librarian_can_access_test_qr_page()
    {
        if (config('app.env') === 'production') {
            $this->markTestSkipped('Test QR route not available in production');
        }

        $user = User::factory()->create(['role_id' => 1]);

        Librarian::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)
            ->get(route('test-qr'));

        $response->assertStatus(200);
    }

    /** @test */
    public function student_cannot_access_test_qr_page()
    {
        if (config('app.env') === 'production') {
            $this->markTestSkipped('Test QR route not available in production');
        }

        $student = User::factory()->create(['role_id' => 1]);

        $response = $this->actingAs($student)
            ->get(route('test-qr'));

        $response->assertStatus(403);
    }

    /** @test */
    public function expired_librarian_cannot_access_test_qr_page()
    {
        if (config('app.env') === 'production') {
            $this->markTestSkipped('Test QR route not available in production');
        }

        $user = User::factory()->create(['role_id' => 1]);

        // Create expired librarian duty (this is for Librarian model, not BorrowTransaction)
        Librarian::factory()->create([
            'user_id' => $user->id,
            'status' => 'expired',
            'expires_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($user)
            ->get(route('test-qr'));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_access_admin_dashboard()
    {
        $admin = User::factory()->create(['role_id' => 3]);

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
    }

    /** @test */
    public function non_admin_cannot_access_admin_dashboard()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)
            ->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_redirected_to_login()
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function gate_assign_librarian_role_allows_admin()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertTrue(Gate::forUser($admin)->allows('assign-librarian-role'));
    }

    /** @test */
    public function gate_assign_librarian_role_denies_non_admin()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->assertFalse(Gate::forUser($user)->allows('assign-librarian-role'));
    }

    /** @test */
    public function gate_librarian_or_admin_access_allows_admin()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertTrue(Gate::forUser($admin)->allows('librarian-or-admin-access'));
    }

    /** @test */
    public function gate_librarian_or_admin_access_allows_active_librarian()
    {
        $user = User::factory()->create(['is_admin' => false]);

        Librarian::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('librarian-or-admin-access'));
    }

    /** @test */
    public function gate_librarian_or_admin_access_denies_student()
    {
        $student = User::factory()->create(['role_id' => 1]);

        $this->assertFalse(Gate::forUser($student)->allows('librarian-or-admin-access'));
    }

    // GRANULAR PERMISSION TESTS

    /** @test */
    public function librarian_can_access_admin_dashboard()
    {
        $user = User::factory()->create(['is_admin' => false]);

        Librarian::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
    }

    /** @test */
    public function librarian_can_access_borrow_logs()
    {
        $user = User::factory()->create(['is_admin' => false]);

        Librarian::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.borrow-logs'));

        $response->assertStatus(200);
    }

    /** @test */
    public function librarian_can_access_violation_logs()
    {
        $user = User::factory()->create(['is_admin' => false]);

        Librarian::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.violation-logs'));

        $response->assertStatus(200);
    }

    /** @test */
    public function librarian_can_view_rules_but_not_edit()
    {
        $user = User::factory()->create(['is_admin' => false]);

        Librarian::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        // Can view
        $response = $this->actingAs($user)
            ->get(route('admin.rules-and-regulations.index'));
        $response->assertStatus(200);

        // Check gates
        $this->assertTrue(Gate::forUser($user)->allows('view-rules'));
        $this->assertFalse(Gate::forUser($user)->allows('manage-rules'));
    }

    /** @test */
    public function librarian_cannot_access_academic_papers()
    {
        $user = User::factory()->create(['is_admin' => false]);

        Librarian::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.academic-paper.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function librarian_cannot_access_attendance_logs()
    {
        $user = User::factory()->create(['is_admin' => false]);

        Librarian::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.attendance-logs'));

        $response->assertStatus(403);
    }

    /** @test */
    public function librarian_cannot_access_student_management()
    {
        $user = User::factory()->create(['is_admin' => false]);

        Librarian::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.user-list'));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_access_all_admin_pages()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Test all admin routes
        $routes = [
            'admin.dashboard',
            'admin.borrow-logs',
            'admin.violation-logs',
            'admin.rules-and-regulations.index',
            'admin.academic-paper.index',
            'admin.attendance-logs',
            'admin.user-list',
            'admin.librarians',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($admin)->get(route($route));
            $response->assertStatus(200, "Admin should access {$route}");
        }
    }

    /** @test */
    public function admin_has_all_permissions()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $permissions = [
            'Admin-access',
            'assign-librarian-role',
            'manage-academic-papers',
            'view-attendance-logs',
            'manage-students',
            'view-rules',
            'manage-rules',
            'access-admin-dashboard',
            'view-borrow-logs',
            'view-violation-logs',
            'manage-advisers-deans',
        ];

        foreach ($permissions as $permission) {
            $this->assertTrue(
                Gate::forUser($admin)->allows($permission),
                "Admin should have '{$permission}' permission"
            );
        }
    }

    /** @test */
    public function student_cannot_access_any_admin_page()
    {
        $student = User::factory()->create(['is_admin' => false]);

        $routes = [
            'admin.dashboard',
            'admin.borrow-logs',
            'admin.violation-logs',
            'admin.rules-and-regulations.index',
            'admin.academic-paper.index',
            'admin.attendance-logs',
            'admin.user-list',
            'admin.librarians',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($student)->get(route($route));
            $response->assertStatus(403, "Student should not access {$route}");
        }
    }
}
