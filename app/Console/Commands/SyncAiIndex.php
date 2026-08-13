<?php

namespace App\Console\Commands;

use App\Exceptions\AiServiceUnavailableException;
use App\Services\AiService;
use Illuminate\Console\Command;

class SyncAiIndex extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:sync-index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger a full sidecar index rebuild from the latest exported corpus';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $result = (new AiService)->rebuildIndex();
        } catch (AiServiceUnavailableException $e) {
            $this->error('Sidecar unavailable: '.$e->getMessage());

            return 1;
        }

        $this->info("Index rebuilt: {$result['documents']} documents, {$result['took_ms']}ms");

        return 0;
    }
}
