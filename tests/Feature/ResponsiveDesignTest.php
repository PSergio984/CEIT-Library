<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResponsiveDesignTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function welcome_page_is_responsive_on_laptop_screens()
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee(asset('images/plvbg.jpg'));
    }
}
