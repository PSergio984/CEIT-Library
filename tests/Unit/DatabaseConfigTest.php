<?php

namespace Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

class DatabaseConfigTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnv = $this->captureEnv([
            'DB_CONNECTION',
            'DATABASE_URL',
            'DB_URL',
            'DB_SSLMODE',
        ]);
    }

    protected function tearDown(): void
    {
        $this->restoreEnv($this->originalEnv);

        parent::tearDown();
    }

    public function test_default_connection_uses_db_connection_env(): void
    {
        $this->setEnv('DB_CONNECTION', 'pgsql');

        $config = $this->loadDatabaseConfig();

        $this->assertSame('pgsql', $config['default']);
    }

    public function test_database_url_prefers_database_url_env(): void
    {
        $this->setEnv('DATABASE_URL', 'postgresql://user:pass@localhost:5432/app');
        $this->setEnv('DB_URL', 'mysql://user:pass@localhost:3306/app');

        $config = $this->loadDatabaseConfig();

        $this->assertSame(
            'postgresql://user:pass@localhost:5432/app',
            $config['connections']['pgsql']['url']
        );
    }

    public function test_pgsql_sslmode_uses_db_sslmode_env(): void
    {
        $this->setEnv('DB_SSLMODE', 'require');

        $config = $this->loadDatabaseConfig();

        $this->assertSame('require', $config['connections']['pgsql']['sslmode']);
    }

    private function loadDatabaseConfig(): array
    {
        $basePath = dirname(__DIR__, 2);
        $previousContainer = Container::getInstance();
        $app = new Application($basePath);
        Container::setInstance($app);

        $config = require $basePath.'/config/database.php';

        if ($previousContainer !== null) {
            Container::setInstance($previousContainer);
        }

        return $config;
    }

    private function captureEnv(array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value === false) {
                $values[$key] = null;

                continue;
            }

            $values[$key] = $value;
        }

        return $values;
    }

    private function restoreEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->setEnv($key, $value);
        }
    }

    private function setEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
