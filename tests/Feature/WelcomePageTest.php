<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomePageTest extends TestCase
{
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
        $response = $this->get('/');

        // Hero section text requested by plan
        $response->assertSee('PLV CEIT Library');
        $response->assertSee('Liquid Glass'); 
        $response->assertSee('Premium');
        
        // Navigation links (checking for expected UI elements)
        $response->assertSee('Log in');
        $response->assertSee('Register');

        // Check for Liquid Glass theme indicators
        $response->assertSee('backdrop-blur-md');
        $response->assertSee('bg-slate-900/60');
    }
}
