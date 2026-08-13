<?php

namespace App\Jobs;

use App\Services\AiService;
use App\Services\CorpusExporter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AiIndexRebuildImmediateJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function uniqueId(): string
    {
        return 'ai-index-rebuild-immediate';
    }

    public function uniqueFor(): int
    {
        return 300;
    }

    /**
     * Immediate full rebuild on deletion — deleted papers must vanish from
     * search results fast (D-11). Separate unique id so a pending debounced
     * job can never swallow this rebuild.
     */
    public function handle(): void
    {
        try {
            (new CorpusExporter)->exportToDisk(
                config('services.ai_sidecar.corpus_path', storage_path('app/ai-corpus'))
            );

            (new AiService)->rebuildIndex();
        } catch (\Throwable $e) {
            logger()->error('AI index rebuild failed', ['exception' => $e->getMessage()]);

            throw $e;
        }
    }
}
