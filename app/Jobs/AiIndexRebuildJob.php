<?php

namespace App\Jobs;

use App\Services\AiService;
use App\Services\CorpusExporter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AiIndexRebuildJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function uniqueId(): string
    {
        return 'ai-index-rebuild';
    }

    public function uniqueFor(): int
    {
        return 300;
    }

    /**
     * Export the corpus and rebuild the sidecar index (full rebuild, D-12).
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
