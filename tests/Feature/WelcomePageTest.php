<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test if the welcome page renders correctly.
     */
    public function test_welcome_page_renders_correctly(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * Test if the welcome page contains the branding text.
     */
    public function test_welcome_page_contains_branding_text(): void
    {
        $response = $this->get('/');

        $response->assertSee('PLV CEIT Library');
    }

    /**
     * Test if the welcome page contains the hero section text and links.
     */
    public function test_welcome_page_contains_hero_section_and_links(): void
    {
        // 1. Guest view
        $response = $this->get('/');
        $response->assertSee('PLV CEIT Library');
        $response->assertSee('Get Started');
        $response->assertSee('Sign In');
        $response->assertDontSee('Enter Dashboard');

        // 2. Student view — dashboard link must point to student dashboard
        $student = User::factory()->create();
        $responseAuth = $this->actingAs($student)->get('/');
        $responseAuth->assertSee('PLV CEIT Library');
        $responseAuth->assertSee('Enter Dashboard');
        $responseAuth->assertDontSee('Get Started');
        $responseAuth->assertSeeHtml('href="'.route('student.dashboard').'"');
    }

    /**
     * Test that an admin user's dashboard link points to the admin dashboard.
     */
    public function test_admin_sees_admin_dashboard_href(): void
    {
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Admin', 'description' => 'Admin']
        );

        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->get('/');

        $response->assertSee('Enter Dashboard');
        $response->assertSeeHtml('href="'.route('admin.dashboard').'"');
    }
}
