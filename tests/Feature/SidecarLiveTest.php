<?php

namespace Tests\Feature;

use App\Services\AiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('sidecar')]
class SidecarLiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (env('SIDECAR_LIVE_TEST') !== '1') {
            $this->markTestSkipped('Set SIDECAR_LIVE_TEST=1 to run live sidecar round-trip tests.');
        }
    }

    #[Test]
    public function export_rebuild_search_round_trip(): void
    {
        $this->artisan('ai:export-corpus')->assertExitCode(0);

        $rebuild = (new AiService)->rebuildIndex();
        $this->assertSame('rebuilt', $rebuild['status']);
        $this->assertGreaterThan(0, $rebuild['documents']);

        $results = (new AiService)->search('Development', [], 'catalog', 5);
        $this->assertNotEmpty($results['results']);
        $this->assertArrayHasKey('id', $results['results'][0]);
        $this->assertArrayHasKey('score', $results['results'][0]);
    }
}
