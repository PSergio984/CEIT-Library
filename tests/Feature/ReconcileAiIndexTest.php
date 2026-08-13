<?php

namespace Tests\Feature;

use App\Jobs\AiIndexRebuildJob;
use App\Models\AcademicPaper;
use App\Models\RuleHeader;
use App\Models\RuleRegulation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReconcileAiIndexTest extends TestCase
{
    use RefreshDatabase;

    private function healthFixture(array $overrides = []): array
    {
        $base = json_decode(file_get_contents(base_path('tests/fixtures/ai-sidecar/health.json')), true);

        return array_replace_recursive($base, $overrides);
    }

    #[Test]
    public function it_reports_in_sync_when_counts_and_freshness_match(): void
    {
        Bus::fake();

        Model::withoutEvents(function () {
            AcademicPaper::factory()->create(['catalog_code' => 'CEIT-RC-01-01', 'title' => 'Reconcile Paper A']);
            AcademicPaper::factory()->create(['catalog_code' => 'CEIT-RC-01-02', 'title' => 'Reconcile Paper B']);
            $header = RuleHeader::factory()->create();
            RuleRegulation::factory()->count(3)->create(['rule_header_id' => $header->id]);
        });

        Http::fake([
            'http://127.0.0.1:8310/health' => Http::response($this->healthFixture([
                'index' => [
                    'by_corpus' => ['catalog' => 2, 'policy' => 4],
                    'source_generated_at' => now()->subHour()->toIso8601String(),
                ],
            ]), 200),
        ]);

        $this->artisan('ai:reconcile-index')->assertExitCode(0);

        Bus::assertNotDispatched(AiIndexRebuildJob::class);
    }

    #[Test]
    public function it_fails_when_catalog_count_mismatches(): void
    {
        Bus::fake();

        Model::withoutEvents(function () {
            AcademicPaper::factory()->create(['catalog_code' => 'CEIT-RC-02-01', 'title' => 'Mismatch Paper A']);
            AcademicPaper::factory()->create(['catalog_code' => 'CEIT-RC-02-02', 'title' => 'Mismatch Paper B']);
        });

        Http::fake([
            'http://127.0.0.1:8310/health' => Http::response($this->healthFixture([
                'index' => [
                    'by_corpus' => ['catalog' => 1, 'policy' => 0],
                    'source_generated_at' => now()->subHour()->toIso8601String(),
                ],
            ]), 200),
        ]);

        $this->artisan('ai:reconcile-index')->assertExitCode(1);

        Bus::assertNotDispatched(AiIndexRebuildJob::class);
    }

    #[Test]
    public function repair_flag_dispatches_a_rebuild_on_mismatch(): void
    {
        Bus::fake();

        Model::withoutEvents(function () {
            AcademicPaper::factory()->create(['catalog_code' => 'CEIT-RC-03-01', 'title' => 'Repair Paper A']);
            AcademicPaper::factory()->create(['catalog_code' => 'CEIT-RC-03-02', 'title' => 'Repair Paper B']);
        });

        Http::fake([
            'http://127.0.0.1:8310/health' => Http::response($this->healthFixture([
                'index' => [
                    'by_corpus' => ['catalog' => 1, 'policy' => 0],
                    'source_generated_at' => now()->subHour()->toIso8601String(),
                ],
            ]), 200),
        ]);

        $this->artisan('ai:reconcile-index', ['--repair' => true])->assertExitCode(0);

        Bus::assertDispatched(AiIndexRebuildJob::class);
    }

    #[Test]
    public function stale_source_generated_at_triggers_mismatch(): void
    {
        Bus::fake();

        Model::withoutEvents(fn () => AcademicPaper::factory()->create(['catalog_code' => 'CEIT-RC-04-01', 'title' => 'Stale Paper A']));

        Http::fake([
            'http://127.0.0.1:8310/health' => Http::response($this->healthFixture([
                'index' => [
                    'by_corpus' => ['catalog' => 1, 'policy' => 0],
                    'source_generated_at' => now()->subHours(27)->toIso8601String(),
                ],
            ]), 200),
        ]);

        $this->artisan('ai:reconcile-index')->assertExitCode(1);
    }
}
