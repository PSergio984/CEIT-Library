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

    // http(s)://, protocol-relative //, quoted or unquoted
    private const REMOTE_SRC_PATTERN = '#<script[^>]+\bsrc\s*=\s*["\']?(?:https?:)?//#i';

    // dynamic src is only acceptable through a local asset helper
    private const DYNAMIC_SRC_PATTERN = '#<script[^>]+\bsrc\s*=\s*["\']?\s*(?:\{\{|\{!!)(?!\s*(?:(?:secure_)?asset|mix|url|route)\s*\(|\s*Vite::)#i';

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
            $contents = file_get_contents($file->getPathname());

            $this->assertDoesNotMatchRegularExpression(
                self::REMOTE_SRC_PATTERN,
                $contents,
                "{$file->getFilename()} loads a remote script — bundle it via resources/js/app.js instead"
            );
            $this->assertDoesNotMatchRegularExpression(
                self::DYNAMIC_SRC_PATTERN,
                $contents,
                "{$file->getFilename()} uses a dynamic script src — resolve it through asset() or Vite:: instead"
            );
        }

        $this->assertGreaterThan(0, $found);
    }

    /** @test */
    #[Test]
    public function test_remote_script_src_variants_are_detected(): void
    {
        foreach ([
            '<script src="https://unpkg.com/html5-qrcode.min.js"></script>',
            '<script src="//cdn.jsdelivr.net/npm/jsqr"></script>',
            '<script src=https://cdn.example.com/x.js></script>',
        ] as $tag) {
            $this->assertMatchesRegularExpression(self::REMOTE_SRC_PATTERN, $tag);
        }

        $this->assertMatchesRegularExpression(
            self::DYNAMIC_SRC_PATTERN,
            '<script src="{{ $cdnUrl }}/x.js"></script>'
        );
        $this->assertDoesNotMatchRegularExpression(
            self::DYNAMIC_SRC_PATTERN,
            '<script src="{{ asset(\'js/scanner.js\') }}"></script>'
        );
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
        $missing = $this->npmInstallsWithoutIgnoreScripts(
            file_get_contents(self::ROOT.'/Dockerfile')
        );

        $this->assertCount(
            0,
            $missing,
            'Every npm install-family command in the image build must pass --ignore-scripts'
        );
    }

    /** @test */
    #[Test]
    public function test_npm_install_variants_without_ignore_scripts_are_flagged(): void
    {
        $this->assertNotEmpty($this->npmInstallsWithoutIgnoreScripts('RUN npm install'));
        $this->assertNotEmpty($this->npmInstallsWithoutIgnoreScripts('RUN npm i'));
        $this->assertNotEmpty($this->npmInstallsWithoutIgnoreScripts('RUN npm ci --omit=dev'));
        $this->assertEmpty($this->npmInstallsWithoutIgnoreScripts('RUN npm ci --omit=dev --ignore-scripts'));
        $this->assertEmpty($this->npmInstallsWithoutIgnoreScripts("RUN npm ci \\\n    --ignore-scripts"));
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

    /**
     * Each npm install-family command, captured with its flags (including
     * \-continuations, stopping at shell separators), must carry the flag.
     *
     * @return string[]
     */
    private function npmInstallsWithoutIgnoreScripts(string $dockerfile): array
    {
        preg_match_all(
            '/\bnpm\s+(?:ci|install|i)\b[^&;|\n\\\\]*(?:\\\\\s*\R[^&;|\n\\\\]*)*/',
            $dockerfile,
            $matches
        );

        return array_values(array_filter(
            $matches[0],
            fn ($command) => ! str_contains($command, '--ignore-scripts'),
        ));
    }
}
