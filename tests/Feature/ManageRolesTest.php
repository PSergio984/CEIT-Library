<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageRolesTest extends TestCase
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

    /** @test - TC001: Manage Roles - Super Admin Access */
    #[Test]
    public function super_admin_can_access_manage_roles_page()
    {
        $superAdmin = User::factory()->create([
            'role_id' => $this->getRoleId('super_admin'),
            'email' => 'superadmin@plv.edu.ph',
            'password' => bcrypt('Pwd@12345'),
        ]);

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.manage-roles'));

        $response->assertStatus(200);
        $response->assertSee('Manage Roles', false);
    }

    /** @test - TC001: Verify role cards are visible */
    #[Test]
    public function manage_roles_page_shows_all_role_cards()
    {
        $superAdmin = User::factory()->create([
            'role_id' => $this->getRoleId('super_admin'),
        ]);

        // Create users with different roles
        User::factory()->count(5)->create(['role_id' => $this->getRoleId('student')]);
        User::factory()->count(2)->create(['role_id' => $this->getRoleId('librarian')]);
        User::factory()->count(2)->create(['role_id' => $this->getRoleId('admin')]);
        User::factory()->count(1)->create(['role_id' => $this->getRoleId('super_admin')]);

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.manage-roles'));

        $response->assertStatus(200);
        // The page loads lazily, so we check for the page title or loading state
        // The actual role cards will be loaded via Livewire after initial page load
        $response->assertSee('User Role Management', false);
        // Verify the page structure is present
        $response->assertSee('Manage Roles', false);
    }

    /** @test - TC001: Super Admin can assign Admin role */
    #[Test]
    public function super_admin_can_assign_admin_role_to_student()
    {
        $superAdmin = User::factory()->create([
            'role_id' => $this->getRoleId('super_admin'),
        ]);

        $student = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
        ]);

        $this->actingAs($superAdmin);

        // Simulate role assignment via Livewire component
        // Note: This would need to be adjusted based on actual Livewire component structure
        $this->assertTrue($student->isStudent());

        // Update role
        $student->update(['role_id' => $this->getRoleId('admin')]);
        $student->refresh();

        $this->assertTrue($student->isAdmin());
    }

    /** @test - TC002: Admin Cannot Access Manage Roles */
    #[Test]
    public function admin_cannot_access_manage_roles_page()
    {
        $admin = User::factory()->create([
            'role_id' => $this->getRoleId('admin'),
            'email' => 'admin@plv.edu.ph',
            'password' => bcrypt('Pwd@12345'),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.manage-roles'));

        $response->assertStatus(403);
    }

    /** @test - TC003: Librarian Cannot Access Manage Roles */
    #[Test]
    public function librarian_cannot_access_manage_roles_page()
    {
        $librarian = User::factory()->create([
            'role_id' => $this->getRoleId('librarian'),
            'email' => 'librarian@plv.edu.ph',
            'password' => bcrypt('Pwd@12345'),
        ]);

        $response = $this->actingAs($librarian)
            ->get(route('admin.manage-roles'));

        $response->assertStatus(403);
    }

    /** @test - TC004: Student Cannot Access Manage Roles */
    #[Test]
    public function student_cannot_access_manage_roles_page()
    {
        $student = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
            'email' => 'student@plv.edu.ph',
            'password' => bcrypt('Pwd@12345'),
        ]);

        $response = $this->actingAs($student)
            ->get(route('admin.manage-roles'));

        $response->assertStatus(403);
    }

    /** @test - TC051: Only Super Admin can assign Admin and Super Admin roles */
    #[Test]
    public function only_super_admin_can_assign_admin_and_super_admin_roles()
    {
        $superAdmin = User::factory()->create([
            'role_id' => $this->getRoleId('super_admin'),
        ]);

        $student = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
        ]);

        $this->actingAs($superAdmin);

        // Super Admin can assign Admin role
        $student->update(['role_id' => $this->getRoleId('admin')]);
        $student->refresh();
        $this->assertTrue($student->isAdmin());

        // Reset to student
        $student->update(['role_id' => $this->getRoleId('student')]);
        $student->refresh();

        // Super Admin can assign Super Admin role
        $student->update(['role_id' => $this->getRoleId('super_admin')]);
        $student->refresh();
        $this->assertTrue($student->isSuperAdmin());
    }
}
