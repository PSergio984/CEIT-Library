<?php

namespace App\Services;

use App\Exceptions\AiServiceAuthException;
use App\Exceptions\AiServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AiService
{
    public function search(string $query, array $filters = [], ?string $corpus = 'catalog', int $limit = 10): array
    {
        try {
            $response = Http::withHeaders(['X-Sidecar-Token' => config('services.ai_sidecar.token')])
                ->baseUrl(config('services.ai_sidecar.base_url'))
                ->connectTimeout(3)
                ->timeout(10)
                ->retry(2, 250, throw: false)
                ->post('/search', [
                    'query' => $query,
                    'filters' => $filters,
                    'corpus' => $corpus,
                    'limit' => $limit,
                    'k' => 60,
                ]);
        } catch (ConnectionException $e) {
            throw new AiServiceUnavailableException('AI sidecar is unavailable (connection failed).', 0, $e);
        }

        $this->throwUnlessOk($response);

        return $response->json() ?? [];
    }

    public function rebuildIndex(): array
    {
        try {
            $response = Http::withHeaders(['X-Sidecar-Token' => config('services.ai_sidecar.token')])
                ->baseUrl(config('services.ai_sidecar.base_url'))
                ->connectTimeout(3)
                ->timeout(120)
                ->retry(1, 250, throw: false)
                ->post('/index/rebuild', []);
        } catch (ConnectionException $e) {
            throw new AiServiceUnavailableException('AI sidecar is unavailable (connection failed).', 0, $e);
        }

        $this->throwUnlessOk($response);

        return $response->json() ?? [];
    }

    public function health(): array
    {
        try {
            $response = Http::withHeaders(['X-Sidecar-Token' => config('services.ai_sidecar.token')])
                ->baseUrl(config('services.ai_sidecar.base_url'))
                ->connectTimeout(3)
                ->timeout(5)
                ->retry(1, 250, throw: false)
                ->get('/health');
        } catch (ConnectionException $e) {
            throw new AiServiceUnavailableException('AI sidecar is unavailable (connection failed).', 0, $e);
        }

        $this->throwUnlessOk($response);

        return $response->json() ?? [];
    }

    private function throwUnlessOk(Response $response): void
    {
        if ($response->status() === 401) {
            throw new AiServiceAuthException('Sidecar authentication failed: invalid SIDECAR_TOKEN.');
        }

        if ($response->failed() || $response->status() >= 500) {
            throw new AiServiceUnavailableException('AI sidecar is unavailable (HTTP '.$response->status().').');
        }
    }
}
