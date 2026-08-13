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
     * Single HTTP gateway to the sidecar: token header, loopback base URL,
     * bounded timeouts, and typed failure mapping. Sanitized failure logging
     * only — never logs tokens, queries, or response bodies.
     */
    private function send(string $method, string $path, array $body, int $timeout, int $retries): array
    {
        try {
            $request = Http::withHeaders(['X-Sidecar-Token' => config('services.ai_sidecar.token')])
                ->baseUrl(config('services.ai_sidecar.base_url'))
                ->connectTimeout(3)
                ->timeout($timeout)
                ->retry($retries, 250, throw: false);

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
