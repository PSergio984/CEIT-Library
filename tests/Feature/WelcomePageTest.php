<?php

namespace Tests\Feature;

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

        // 2. Authenticated view
        $student = User::factory()->create();
        $responseAuth = $this->actingAs($student)->get('/');
        $responseAuth->assertSee('PLV CEIT Library');
        $responseAuth->assertSee('Enter Dashboard');
        $responseAuth->assertDontSee('Get Started');
    }
}
