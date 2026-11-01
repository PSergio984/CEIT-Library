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

    // dynamic src must be a single local-asset helper call (url()/route() can emit remote URLs; helper args may not contain ))
    private const DYNAMIC_SRC_PATTERN = '#<script[^>]+\bsrc\s*=\s*["\']?\s*(?:\{\{|\{!!)(?!\s*(?:(?:secure_)?asset|mix|Vite::\w+)\s*\([^)]*\)\s*(?:\}\}|!!\}))#i';

    // top-level permissions block may contain only `contents: read`
    private const PERMISSIONS_BLOCK_PATTERN = '/^permissions:\R[ \t]+contents:[ \t]*read[ \t]*(?:\R(?=\R*\S)|\z)/m';

    // no job-level permissions overrides
    private const JOB_PERMISSIONS_PATTERN = '/^[ \t]+permissions:/m';

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

        foreach ([
            '<script src="{{ $cdnUrl }}/x.js"></script>',
            '<script src="{{ url(\'https://unpkg.com/x.js\') }}"></script>',
            '<script src="{{ asset(\'\') . $cdnUrl }}"></script>',
            '<script src="{{ route(\'x\') }}{{ $remote }}"></script>',
        ] as $tag) {
            $this->assertMatchesRegularExpression(self::DYNAMIC_SRC_PATTERN, $tag);
        }

        foreach ([
            '<script src="{{ asset(\'js/scanner.js\') }}"></script>',
            '<script src="{{ mix(\'js/app.js\') }}"></script>',
            '<script src="{{ Vite::asset(\'resources/js/app.js\') }}"></script>',
        ] as $tag) {
            $this->assertDoesNotMatchRegularExpression(self::DYNAMIC_SRC_PATTERN, $tag);
        }
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
        $this->assertNotEmpty($this->npmInstallsWithoutIgnoreScripts('RUN npm ci --ignore-scripts=false'));
        $this->assertEmpty($this->npmInstallsWithoutIgnoreScripts('RUN npm ci --omit=dev --ignore-scripts'));
        $this->assertEmpty($this->npmInstallsWithoutIgnoreScripts("RUN npm ci \\\n    --ignore-scripts"));
    }

    /** @test */
    #[Test]
    public function test_sonar_secrets_workflow_restricts_token_permissions(): void
    {
        $workflow = file_get_contents(self::ROOT.'/.github/workflows/sonar-secrets.yml');

        $this->assertMatchesRegularExpression(
            self::PERMISSIONS_BLOCK_PATTERN,
            $workflow,
            'Workflow must declare only contents: read permissions'
        );
        $this->assertDoesNotMatchRegularExpression(
            self::JOB_PERMISSIONS_PATTERN,
            $workflow,
            'Job-level permissions must not override workflow permissions'
        );
    }

    /** @test */
    #[Test]
    public function test_vite_plugin_pwa_stays_on_the_2_x_line(): void
    {
        $deps = $this->packageDeps();

        $this->assertMatchesRegularExpression(
            '/^\D*2\./',
            $deps['vite-plugin-pwa'] ?? '',
            'vite-plugin-pwa must stay on the 2.x line (Node >=20.19 required)'
        );
    }

    /** @test */
    #[Test]
    public function test_fontaine_is_not_a_direct_dependency(): void
    {
        $this->assertArrayNotHasKey(
            'fontaine',
            $this->packageDeps(),
            'laravel-vite-plugin pins its own fontaine — do not re-add it as a direct dep'
        );
    }

    /** @test */
    #[Test]
    public function test_workflow_permission_escalations_are_flagged(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            self::PERMISSIONS_BLOCK_PATTERN,
            "permissions:\n  contents: read\n  pull-requests: write\njobs:"
        );
        $this->assertDoesNotMatchRegularExpression(
            self::PERMISSIONS_BLOCK_PATTERN,
            "permissions: write-all\njobs:"
        );
        $this->assertMatchesRegularExpression(
            self::JOB_PERMISSIONS_PATTERN,
            "permissions:\n  contents: read\n\njobs:\n  scan:\n    permissions: write-all"
        );
    }

    /** @return array<string, string> */
    private function packageDeps(): array
    {
        $package = json_decode(file_get_contents(self::ROOT.'/package.json'), true);

        return array_merge(
            $package['dependencies'] ?? [],
            $package['devDependencies'] ?? [],
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
            fn ($command) => ! preg_match('/(?:^|\s)--ignore-scripts(?:\s|$)/', $command),
        ));
    }
}
