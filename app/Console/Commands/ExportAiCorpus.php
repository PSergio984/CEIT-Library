<?php

namespace App\Console\Commands;

use App\Services\CorpusExporter;
use Illuminate\Console\Command;

class ExportAiCorpus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:export-corpus {--corpus=all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export the catalog and policy corpora as JSON documents for the AI sidecar search index';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $corpus = $this->option('corpus');

        if (! in_array($corpus, ['all', 'catalog', 'policy'], true)) {
            $this->error("Invalid --corpus value '{$corpus}'. Expected one of: all, catalog, policy.");

            return 1;
        }

        $path = config('services.ai_sidecar.corpus_path', storage_path('app/ai-corpus'));

        $which = match ($corpus) {
            'all' => ['catalog', 'policies'],
            'catalog' => ['catalog'],
            'policy' => ['policies'],
        };

        $counts = (new CorpusExporter)->exportToDisk($path, $which);

        if (in_array('catalog', $which, true)) {
            $this->info("Exported {$counts['catalog']} catalog document(s) to {$path}/catalog.json");
        }

        if (in_array('policies', $which, true)) {
            $this->info("Exported {$counts['policies']} policy document(s) to {$path}/policies.json");
        }

        return 0;
    }
}
