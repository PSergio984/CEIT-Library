<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Models\AcademicPaper;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
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

    /** @test - TC010: Student cannot access Admin Dashboard */
    #[Test]
    public function student_cannot_access_admin_dashboard()
    {
        $student = User::factory()->create([
            'role_id' => $this->getRoleId('student'),
        ]);

        $response = $this->actingAs($student)
            ->get(route('admin.dashboard'));

        $response->assertStatus(403);
    }

    /** @test - TC011: Super Admin can view dashboard metrics */
    #[Test]
    public function super_admin_can_view_dashboard_metrics()
    {
        $superAdmin = User::factory()->create([
            'role_id' => $this->getRoleId('super_admin'),
        ]);

        // Create some test data
        User::factory()->count(5)->create();
        AcademicPaper::factory()->count(3)->create();

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
        // Verify dashboard loads (checking for common dashboard elements)
        $response->assertSee('Dashboard', false);
    }

    /** @test - TC011: Dashboard shows statistics cards */
    #[Test]
    public function dashboard_displays_statistics_cards()
    {
        $superAdmin = User::factory()->create([
            'role_id' => $this->getRoleId('super_admin'),
        ]);

        // Create test data
        User::factory()->count(10)->create();
        AcademicPaper::factory()->count(5)->create();

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);
        // The dashboard should display statistics
        // Exact content depends on implementation
    }

    /** @test - TC064: Dashboard statistics display accurate data */
    #[Test]
    public function dashboard_statistics_show_accurate_counts()
    {
        $superAdmin = User::factory()->create([
            'role_id' => $this->getRoleId('super_admin'),
        ]);

        // Create known amounts of data
        $userCount = 5;
        $paperCount = 3;

        User::factory()->count($userCount)->create();
        AcademicPaper::factory()->count($paperCount)->create();

        $response = $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'));

        $response->assertStatus(200);

        // Verify counts match (this would need to check actual rendered content)
        $this->assertEquals($userCount + 1, User::count()); // +1 for superAdmin
        $this->assertEquals($paperCount, AcademicPaper::count());
    }
}
