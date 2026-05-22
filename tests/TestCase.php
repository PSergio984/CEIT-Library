<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        // Force SQLite in-memory for testing to avoid using the real database
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_DATABASE'] = ':memory:';

        $app = parent::createApplication();

        // Also force it in config just in case
        $app->make('config')->set('database.default', 'sqlite');
        $app->make('config')->set('database.connections.sqlite.database', ':memory:');

        return $app;
    }

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
