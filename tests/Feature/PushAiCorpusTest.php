<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushAiCorpusTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_exports_and_uploads_both_corpora(): void
    {
        Http::fake([
            '*/corpus/upload' => Http::response([
                'status' => 'uploaded_and_rebuilt',
                'files' => ['catalog.json', 'policies.json'],
                'contract_version' => 'v1',
                'documents' => 0,
                'by_corpus' => [],
                'took_ms' => 12,
                'source_generated_at' => null,
            ], 200),
        ]);

        config([
            'services.ai_sidecar.base_url' => 'https://sidecar.example.test',
            'services.ai_sidecar.token' => 'test-token',
        ]);

        $this->artisan('ai:push-corpus')->assertExitCode(0);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sidecar.example.test/corpus/upload'
                && $request->hasHeader('X-Sidecar-Token', 'test-token');
        });
    }

    public function test_push_fails_without_token(): void
    {
        config(['services.ai_sidecar.token' => null]);

        $this->artisan('ai:push-corpus')->assertExitCode(1);
    }

    public function test_push_fails_on_sidecar_error(): void
    {
        Http::fake([
            '*/corpus/upload' => Http::response(['error' => ['code' => 'upload_failed', 'message' => 'bad json']], 500),
        ]);

        config([
            'services.ai_sidecar.base_url' => 'https://sidecar.example.test',
            'services.ai_sidecar.token' => 'test-token',
        ]);

        $this->artisan('ai:push-corpus')->assertExitCode(1);
    }
}
