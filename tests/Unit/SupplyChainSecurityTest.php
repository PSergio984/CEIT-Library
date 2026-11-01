<?php

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SupplyChainSecurityTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    /** @test */
    #[Test]
    public function test_no_blade_view_loads_remote_scripts(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::ROOT.'/resources/views', FilesystemIterator::SKIP_DOTS)
        );

        $found = 0;

        foreach ($iterator as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $found++;

            $this->assertDoesNotMatchRegularExpression(
                '#<script[^>]+\bsrc=["\']https?://#i',
                file_get_contents($file->getPathname()),
                "{$file->getFilename()} loads a remote script — bundle it via resources/js/app.js instead"
            );
        }

        $this->assertGreaterThan(0, $found);
    }

    /** @test */
    #[Test]
    public function test_scanner_libraries_are_bundled_and_exposed_as_globals(): void
    {
        $appJs = file_get_contents(self::ROOT.'/resources/js/app.js');

        $this->assertStringContainsString('window.Html5Qrcode', $appJs);
        $this->assertStringContainsString('window.jsQR', $appJs);
    }

    /** @test */
    #[Test]
    public function test_dockerfile_never_runs_npm_install_scripts(): void
    {
        $dockerfile = file_get_contents(self::ROOT.'/Dockerfile');

        $this->assertDoesNotMatchRegularExpression(
            '/npm ci(?!\s+--ignore-scripts)/',
            $dockerfile,
            'Every npm ci in the image build must pass --ignore-scripts'
        );
    }

    /** @test */
    #[Test]
    public function test_sonar_secrets_workflow_restricts_token_permissions(): void
    {
        $workflow = file_get_contents(self::ROOT.'/.github/workflows/sonar-secrets.yml');

        $this->assertMatchesRegularExpression(
            '/^permissions:\s*\R\s+contents:\s*read\s*$/m',
            $workflow,
            'Workflow must declare a least-privilege permissions block'
        );
    }
}
