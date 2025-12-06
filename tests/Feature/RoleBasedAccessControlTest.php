<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Librarian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RoleBasedAccessControlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Get role ID by name
     */
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

    /** @test */
    public function admin_can_access_librarian_assignment_page()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);

        $response = $this->actingAs($admin)
            ->get(route('admin.librarians'));

        $response->assertStatus(200);
    }

    /** @test */
    public function librarian_cannot_access_librarian_assignment_page()
    {
        $user = User::factory()->create(['role_id' => $this->getRoleId('student')]);

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
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);

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

        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);

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

        $user = User::factory()->create(['role_id' => $this->getRoleId('student')]);

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

        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);

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

        $user = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        // Create expired librarian duty
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
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
    }

    /** @test */
    public function super_admin_can_access_admin_dashboard()
    {
        $superAdmin = User::factory()->create(['role_id' => $this->getRoleId('super_admin')]);

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
    }

    /** @test */
    public function student_cannot_access_admin_dashboard()
    {
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $response = $this->actingAs($student)
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
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);

        $this->assertTrue(Gate::forUser($admin)->allows('assign-librarian-role'));
    }

    /** @test */
    public function gate_assign_librarian_role_denies_student()
    {
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $this->assertFalse(Gate::forUser($student)->allows('assign-librarian-role'));
    }

    /** @test */
    public function gate_librarian_or_admin_access_allows_admin()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);

        $this->assertTrue(Gate::forUser($admin)->allows('librarian-or-admin-access'));
    }

    /** @test */
    public function gate_librarian_or_admin_access_allows_active_librarian()
    {
        $user = User::factory()->create(['role_id' => $this->getRoleId('student')]);

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
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $this->assertFalse(Gate::forUser($student)->allows('librarian-or-admin-access'));
    }

    // GRANULAR PERMISSION TESTS

    /** @test */
    public function librarian_can_access_admin_dashboard()
    {
        $user = User::factory()->create(['role_id' => $this->getRoleId('librarian')]);

        $response = $this->actingAs($user)
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
    }

    /** @test */
    public function librarian_can_access_borrow_logs()
    {
        $user = User::factory()->create(['role_id' => $this->getRoleId('librarian')]);

        $response = $this->actingAs($user)
            ->get(route('admin.borrow-logs'));

        $response->assertStatus(200);
    }

    /** @test */
    public function librarian_can_access_violation_logs()
    {
        $user = User::factory()->create(['role_id' => $this->getRoleId('librarian')]);

        $response = $this->actingAs($user)
            ->get(route('admin.violation-logs'));

        $response->assertStatus(200);
    }

    /** @test */
    public function librarian_can_view_rules_but_not_edit()
    {
        $user = User::factory()->create(['role_id' => $this->getRoleId('librarian')]);

        // Can view
        $response = $this->actingAs($user)
            ->get(route('admin.rules-and-regulations.index'));
        $response->assertStatus(200);

        // Check gates
        $this->assertTrue(Gate::forUser($user)->allows('view-rules'));
        $this->assertFalse(Gate::forUser($user)->allows('manage-rules'));
    }

    /** @test */
    public function librarian_can_view_academic_papers_read_only()
    {
        $user = User::factory()->create(['role_id' => $this->getRoleId('librarian')]);

        $response = $this->actingAs($user)
            ->get(route('admin.academic-paper.index'));

        // Librarians can view academic papers (read-only) according to TC014
        $response->assertStatus(200);
    }

    /** @test */
    public function librarian_can_view_attendance_logs()
    {
        $user = User::factory()->create(['role_id' => $this->getRoleId('librarian')]);

        $response = $this->actingAs($user)
            ->get(route('admin.attendance-logs'));

        // Librarians can view attendance logs according to gate definition
        $response->assertStatus(200);
    }

    /** @test */
    public function librarian_cannot_access_student_management()
    {
        $user = User::factory()->create(['role_id' => $this->getRoleId('librarian')]);

        // Note: This route might be commented out, so we'll test the gate instead
        $this->assertFalse(Gate::forUser($user)->allows('manage-students'));
    }

    /** @test */
    public function admin_can_access_all_admin_pages()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);

        // Test all admin routes
        $routes = [
            'admin.dashboard',
            'admin.borrow-logs',
            'admin.violation-logs',
            'admin.rules-and-regulations.index',
            'admin.academic-paper.index',
            'admin.attendance-logs',
            'admin.librarians',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($admin)->get(route($route));
            $response->assertStatus(200, "Admin should access {$route}");
        }
    }

    /** @test */
    public function super_admin_can_access_manage_roles()
    {
        $superAdmin = User::factory()->create(['role_id' => $this->getRoleId('super_admin')]);

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.manage-roles'));

        $response->assertStatus(200);
    }

    /** @test */
    public function admin_cannot_access_manage_roles()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);

        $response = $this->actingAs($admin)
            ->get(route('admin.manage-roles'));

        $response->assertStatus(403);
    }

    /** @test */
    public function librarian_cannot_access_manage_roles()
    {
        $librarian = User::factory()->create(['role_id' => $this->getRoleId('librarian')]);

        $response = $this->actingAs($librarian)
            ->get(route('admin.manage-roles'));

        $response->assertStatus(403);
    }

    /** @test */
    public function student_cannot_access_manage_roles()
    {
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $response = $this->actingAs($student)
            ->get(route('admin.manage-roles'));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_has_all_permissions()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);

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
            // Note: manage-advisers-deans is Super Admin only, not Admin
        ];

        foreach ($permissions as $permission) {
            $this->assertTrue(
                Gate::forUser($admin)->allows($permission),
                "Admin should have '{$permission}' permission"
            );
        }

        // Verify admin does NOT have super admin only permissions
        $this->assertFalse(
            Gate::forUser($admin)->allows('manage-advisers-deans'),
            "Admin should NOT have 'manage-advisers-deans' permission (Super Admin only)"
        );
    }

    /** @test */
    public function student_cannot_access_any_admin_page()
    {
        $student = User::factory()->create(['role_id' => $this->getRoleId('student')]);

        $routes = [
            'admin.dashboard',
            'admin.borrow-logs',
            'admin.violation-logs',
            'admin.rules-and-regulations.index',
            'admin.academic-paper.index',
            'admin.attendance-logs',
            'admin.librarians',
            'admin.manage-roles',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($student)->get(route($route));
            $response->assertStatus(403, "Student should not access {$route}");
        }
    }
}
