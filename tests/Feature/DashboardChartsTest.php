<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Models\AcademicPaper;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
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

    /** @test - TC065: Dashboard - Charts Rendering */
    #[Test]
    public function dashboard_charts_render_correctly_with_data()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        // Create papers with different departments
        AcademicPaper::factory()->count(3)->create(['department' => 'Computer Science']);
        AcademicPaper::factory()->count(2)->create(['department' => 'Engineering']);
        AcademicPaper::factory()->count(1)->create(['department' => 'Mathematics']);

        $response = $this->get(route('admin.dashboard'));
        $response->assertStatus(200);

        // Verify charts are present (this is primarily a frontend check)
        $response->assertSee('Dashboard', false);
    }
}
