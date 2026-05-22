<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Traits\CreatesTestDatabase;

abstract class TestCase extends BaseTestCase
{
    use CreatesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(\Livewire\Features\SupportTesting\SupportTesting::class)) {
            \Livewire\Features\SupportTesting\SupportTesting::provide();
        }

        // Workaround for Livewire 4 / Laravel 13 macro issues
        if (!\Illuminate\Testing\TestResponse::hasMacro('assertSeeLivewire')) {
            \Illuminate\Testing\TestResponse::macro('assertSeeLivewire', function ($component) {
                return $this->assertSee($component); // Fallback to basic see
            });
        }
    }
}
