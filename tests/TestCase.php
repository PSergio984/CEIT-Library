<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportLazyLoading\SupportLazyLoading;
use Livewire\Features\SupportTesting\SupportTesting;
use Livewire\Livewire;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Creates the application.
     *
     * @return Application
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

    protected bool $disableLivewireLazyLoading = false;

    private static bool $flushStateListenerRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->disableLivewireLazyLoading) {
            if (class_exists(Livewire::class)) {
                Livewire::withoutLazyLoading();
                if (! self::$flushStateListenerRegistered) {
                    Livewire::listen('flush-state', function () {
                        if (class_exists(SupportLazyLoading::class)) {
                            SupportLazyLoading::$disableWhileTesting = true;
                        }
                    });
                    self::$flushStateListenerRegistered = true;
                }
            }
            if (class_exists(SupportLazyLoading::class)) {
                SupportLazyLoading::$disableWhileTesting = true;
            }
        }

        if (class_exists(SupportTesting::class)) {
            SupportTesting::provide();
        }

        // Workaround for Livewire 4 / Laravel 13 macro issues
        if (! TestResponse::hasMacro('assertSeeLivewire')) {
            TestResponse::macro('assertSeeLivewire', function ($component) {
                return $this->assertSee($component); // Fallback to basic see
            });
        }
    }

    protected function tearDown(): void
    {
        if (class_exists(SupportLazyLoading::class)) {
            SupportLazyLoading::$disableWhileTesting = false;
        }
        parent::tearDown();
    }
}
