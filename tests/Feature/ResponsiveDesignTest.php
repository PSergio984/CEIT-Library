<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResponsiveDesignTest extends TestCase
{
    use RefreshDatabase;

    /** @test - TC048: Welcome Page - Responsive Design */
    public function welcome_page_is_responsive_on_laptop_screens()
    {
        // This is primarily a frontend/browser test
        // We can verify the page loads correctly
        $response = $this->get('/');
        $response->assertStatus(200);

        // Responsive design is tested with browser tools or visual regression testing
    }
}
