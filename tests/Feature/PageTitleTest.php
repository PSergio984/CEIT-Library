<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTitleTest extends TestCase
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

    /** @test - TC052: Web Page Title - Dynamic Updates */
    public function browser_tab_title_updates_based_on_current_page()
    {
        $admin = User::factory()->create(['role_id' => $this->getRoleId('admin')]);
        $this->actingAs($admin);

        // Check Dashboard title
        $response = $this->get(route('admin.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Dashboard', false);

        // Check Manage Roles title
        $response = $this->get(route('admin.manage-roles'));
        $response->assertStatus(200);
        $response->assertSee('Manage Roles', false);

        // Check Borrow Logs title
        $response = $this->get(route('admin.logs'));
        $response->assertStatus(200);
        $response->assertSee('Borrow Logs', false);

        // Check Profile title
        $response = $this->get(route('profile'));
        $response->assertStatus(200);
        $response->assertSee('Profile', false);
    }
}
