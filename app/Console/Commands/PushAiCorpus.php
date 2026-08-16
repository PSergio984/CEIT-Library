<?php

namespace App\Console\Commands;

use App\Services\CorpusExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PushAiCorpus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:push-corpus {--corpus=all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export and upload the corpus to the sidecar (POST /corpus/upload) for cloud deployments';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $corpus = $this->option('corpus');

        $corpora = [
            'all' => ['catalog', 'policies'],
            'catalog' => ['catalog'],
            'policy' => ['policies'],
        ];

        if (! isset($corpora[$corpus])) {
            $this->error("Invalid --corpus value '{$corpus}'. Expected one of: all, catalog, policy.");

            return 1;
        }

        $token = config('services.ai_sidecar.token');
        if (empty($token)) {
            $this->error('SIDECAR_TOKEN is not set — cannot authenticate to the sidecar.');

            return 1;
        }

        $path = config('services.ai_sidecar.corpus_path', storage_path('app/ai-corpus'));

        $which = $corpora[$corpus];

        $counts = (new CorpusExporter)->exportToDisk($path, $which);

        $request = Http::baseUrl(config('services.ai_sidecar.base_url'))
            ->withHeaders(['X-Sidecar-Token' => $token])
            ->asMultipart()
            ->timeout(120);

        if (in_array('catalog', $which, true)) {
            $request = $request->attach('catalog', file_get_contents($path.'/catalog.json'), 'catalog.json');
        }

        if (in_array('policies', $which, true)) {
            $request = $request->attach('policies', file_get_contents($path.'/policies.json'), 'policies.json');
        }

        try {
            $response = $request->post('/corpus/upload');
        } catch (\Exception $e) {
            $this->error("Upload failed: {$e->getMessage()}");

            return 1;
        }

        if ($response->failed()) {
            $this->error("Upload failed: HTTP {$response->status()} {$response->body()}");

            return 1;
        }

        $data = $response->json();

        $this->info(
            "Uploaded and rebuilt: {$data['documents']} document(s) "
            ."({$data['status']}; catalog {$counts['catalog']}, policies {$counts['policies']})"
        );

        return 0;
    }
}
