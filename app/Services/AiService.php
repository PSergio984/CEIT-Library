<?php

namespace App\Services;

use App\Exceptions\AiServiceAuthException;
use App\Exceptions\AiServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * RRF candidate pool size — part of the sidecar search contract.
     */
    public const RRF_CANDIDATES = 60;

    public function search(string $query, array $filters = [], ?string $corpus = 'catalog', int $limit = 10): array
    {
        return $this->send('POST', '/search', [
            'query' => $query,
            'filters' => $filters,
            'corpus' => $corpus,
            'limit' => $limit,
            'k' => self::RRF_CANDIDATES,
        ], timeout: 10, retries: 2);
    }

    public function rebuildIndex(): array
    {
        return $this->send('POST', '/index/rebuild', [], timeout: 120, retries: 1);
    }

    public function health(): array
    {
        return $this->send('GET', '/health', [], timeout: 5, retries: 1);
    }

    /**
     * Streamed chat endpoint: POST /chat/stream with the ADR 0004 payload,
     * no retries (a retry would re-issue the POST and duplicate LLM
     * generation), throwUnlessOk before touching the body, and the raw
     * streamed Response back to the caller.
     */
    public function chatStream(string $query, ?string $mode = 'citations', ?string $corpus = null, int $topK = 5): Response
    {
        $body = ['query' => $query, 'mode' => $mode, 'top_k' => $topK];

        if ($corpus !== null) {
            $body['corpus'] = $corpus;
        }

        try {
            $request = $this->request('POST', '/chat/stream', $body, timeout: 120, retries: 0, stream: true);
            $response = $request->post('/chat/stream', $body);
        } catch (ConnectionException $e) {
            $this->logFailure('/chat/stream', 'connection');
            throw new AiServiceUnavailableException('AI sidecar is unavailable (connection failed).', 0, $e);
        }

        $this->throwUnlessOk($response, '/chat/stream');

        return $response;
    }

    /**
     * Single HTTP gateway to the sidecar: token header, loopback base URL,
     * bounded timeouts, and typed failure mapping. Sanitized failure logging
     * only — never logs tokens, queries, or response bodies.
     */
    private function send(string $method, string $path, array $body, int $timeout, int $retries): array
    {
        try {
            $request = $this->request($method, $path, $body, $timeout, $retries);

            $response = $method === 'POST'
                ? $request->post($path, $body)
                : $request->get($path);
        } catch (ConnectionException $e) {
            $this->logFailure($path, 'connection');
            throw new AiServiceUnavailableException('AI sidecar is unavailable (connection failed).', 0, $e);
        }

        $this->throwUnlessOk($response, $path);

        return $response->json() ?? [];
    }

    /**
     * Shared request builder: token header, loopback base URL, bounded
     * timeouts, and per-call retry policy. `$stream` opts into an
     * incremental response body (read via `$response->resource()`).
     */
    private function request(string $method, string $path, array $body, int $timeout, int $retries, bool $stream = false): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::withHeaders(['X-Sidecar-Token' => config('services.ai_sidecar.token')])
            ->baseUrl(config('services.ai_sidecar.base_url'))
            ->connectTimeout(3)
            ->timeout($timeout)
            ->retry($retries, 250, throw: false);

        if ($stream) {
            $request = $request->withOptions(['stream' => true]);
        }

        return $request;
    }

    private function throwUnlessOk(Response $response, string $path): void
    {
        if ($response->status() === 401) {
            $this->logFailure($path, 'auth');
            throw new AiServiceAuthException('Sidecar authentication failed: invalid SIDECAR_TOKEN.');
        }

        if ($response->failed()) {
            $this->logFailure($path, 'http_'.$response->status());
            throw new AiServiceUnavailableException('AI sidecar is unavailable (HTTP '.$response->status().').');
        }
    }

    private function logFailure(string $path, string $reason): void
    {
        Log::warning('AI sidecar request failed', ['endpoint' => $path, 'reason' => $reason]);
    }
}
