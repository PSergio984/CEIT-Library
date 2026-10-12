<?php

namespace App\Console\Commands;

use App\Models\BorrowTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportBorrowSequences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:export-borrow-sequences {--output= : Path to write the JSON export}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export anonymised per-user borrow sequences (ordered paper ids, no user identifiers) for offline recommender evaluation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = $this->option('output')
            ?: config('services.ai_sidecar.corpus_path', storage_path('app/ai-corpus')).'/borrow-sequences.json';

        $transactions = BorrowTransaction::query()
            ->select(['user_id', 'academic_paper_id'])
            ->orderBy('user_id')
            ->orderByRaw('COALESCE(time_in, created_at)')
            ->orderBy('id')
            ->get();

        $sequences = [];

        foreach ($transactions->groupBy('user_id')->values() as $index => $group) {
            $sequences[$index] = $group->pluck('academic_paper_id')->map(fn ($id) => (int) $id)->values()->all();
        }

        $payload = [
            'schema_version' => 1,
            'generated_at' => now()->utc()->toIso8601String(),
            'user_count' => count($sequences),
            'sequences' => (object) $sequences,
        ];

        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->info("Exported {$transactions->count()} borrow(s) across ".count($sequences)." anonymised sequence(s) to {$path}");

        return 0;
    }
}
