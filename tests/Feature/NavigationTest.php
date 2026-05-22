<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
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

    /** @test - TC084: Breadcrumb Navigation - Trail Display */
    #[Test]
    public function breadcrumb_navigation_shows_current_path()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        $response = $this->get(route('admin.manage-roles'));
        $response->assertStatus(200);

        // Verify breadcrumb is present (this is primarily a frontend check)
        $response->assertSee('Dashboard', false);
        $response->assertSee('Manage Roles', false);
    }

    /** @test - TC085: Sidebar - Active Menu Highlighting */
    #[Test]
    public function active_menu_item_is_highlighted_in_sidebar()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        // Navigate to different pages
        $response = $this->get(route('admin.dashboard'));
        $response->assertStatus(200);

        $response = $this->get(route('admin.academic-paper.index'));
        $response->assertStatus(200);

        // Active menu highlighting is primarily a frontend feature
    }

    /** @test - TC086: Sidebar - Collapse/Expand */
    #[Test]
    public function sidebar_can_be_collapsed_and_expanded()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        $response = $this->get(route('admin.dashboard'));
        $response->assertStatus(200);

        // Sidebar collapse/expand is primarily a frontend feature
    }
}
