<?php

namespace App\Console\Commands;

use App\Exceptions\AiServiceUnavailableException;
use App\Jobs\AiIndexRebuildJob;
use App\Models\AcademicPaper;
use App\Models\RuleHeader;
use App\Models\RuleRegulation;
use App\Services\AiService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ReconcileAiIndex extends Command
{
    /**
     * An index is fresh only when its source_generated_at stamp is within
     * this many hours of now (see 08-06 Task 3).
     */
    private const FRESH_WINDOW_HOURS = 26;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:reconcile-index {--repair : Dispatch a rebuild job on mismatch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify the sidecar index matches the database (counts + freshness)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $expectedCatalog = AcademicPaper::count();
        $expectedPolicy = RuleHeader::count() + RuleRegulation::count();

        try {
            $health = (new AiService)->health();
        } catch (AiServiceUnavailableException $e) {
            $this->error('Sidecar unavailable: '.$e->getMessage());

            return 1;
        }

        $index = $health['index'] ?? null;
        if ($index === null) {
            $this->error('Sidecar has no index yet (degraded).');

            return 1;
        }

        $byCorpus = $index['by_corpus'] ?? [];
        $actualCatalog = $byCorpus['catalog'] ?? 0;
        $actualPolicy = $byCorpus['policy'] ?? 0;

        $sourceGeneratedAt = $index['source_generated_at'] ?? null;
        $stale = true;
        if ($sourceGeneratedAt !== null) {
            try {
                $generatedAt = Carbon::parse($sourceGeneratedAt);
                $stale = ! $generatedAt->between(now()->subHours(self::FRESH_WINDOW_HOURS), now());
            } catch (\Throwable) {
                Log::warning('AI index timestamp unparseable; treating index as stale', [
                    'source_generated_at' => $sourceGeneratedAt,
                ]);
                $stale = true;
            }
        }

        $inSync = $expectedCatalog === $actualCatalog
            && $expectedPolicy === $actualPolicy
            && ! $stale;

        if ($inSync) {
            $this->info('AI index in sync');

            return 0;
        }

        Log::warning('AI index out of sync', [
            'expected_catalog' => $expectedCatalog,
            'actual_catalog' => $actualCatalog,
            'expected_policy' => $expectedPolicy,
            'actual_policy' => $actualPolicy,
            'source_generated_at' => $sourceGeneratedAt,
        ]);

        if ($this->option('repair')) {
            AiIndexRebuildJob::dispatch();
            $this->info('Dispatched AI index rebuild job.');

            return 0;
        }

        $this->error('AI index out of sync (expected catalog '.$expectedCatalog.', got '.$actualCatalog
            .'; expected policy '.$expectedPolicy.', got '.$actualPolicy.').');

        return 1;
    }
}
