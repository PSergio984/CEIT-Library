<?php

namespace App\Services;

use App\Exceptions\AiServiceAuthException;
use App\Exceptions\AiServiceProviderException;
use App\Exceptions\AiServiceUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    /**
     * RRF candidate pool size — part of the sidecar search contract.
     */
    public const RRF_CANDIDATES = 60;

    /**
     * Wire key for the JSON-encoded SSE chunk envelope (`{"c": "<delta>"}`)
     * — the sidecar's counterpart constant lives in `app/rag.py`.
     */
    private const SSE_CHUNK_KEY = 'c';

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
            $request = $this->request(timeout: 120, retries: 0, stream: true);
            $response = $request->post('/chat/stream', $body);
        } catch (ConnectionException $e) {
            $this->logFailure('/chat/stream', 'connection');
            throw new AiServiceUnavailableException('AI sidecar is unavailable (connection failed).', 0, $e);
        }

        $this->throwUnlessOk($response, '/chat/stream');

        return $response;
    }

    /**
     * SSE line parser over the streamed response body. Yields chunk payloads
     * in order, terminates on `data: [DONE]`, and throws the typed
     * AiServiceProviderException when an `event: error` line carries a JSON
     * error payload — the error data line is never yielded as content.
     *
     * Chunk payloads are JSON-encoded `{"c": "<delta>"}` (sidecar framing)
     * so deltas containing newlines survive the line-based transport; raw
     * text payloads are yielded as-is for compatibility. A clean EOF
     * without `[DONE]` means the provider stream was truncated — thrown,
     * never silently accepted.
     */
    public function chatStreamEvents(Response $response): \Generator
    {
        $stream = $response->resource();

        try {
            while (! feof($stream)) {
                $line = fgets($stream);

                if ($line === false) {
                    break;
                }

                $line = rtrim($line, "\r\n");

                if (str_starts_with($line, 'data: ')) {
                    $payload = substr($line, 6);

                    if ($payload === '[DONE]') {
                        return;
                    }

                    $decoded = json_decode($payload, true);
                    if (is_array($decoded) && isset($decoded[self::SSE_CHUNK_KEY])) {
                        $payload = (string) $decoded[self::SSE_CHUNK_KEY];
                    }

                    yield $payload;

                    continue;
                }

                if ($line === 'event: error') {
                    $dataLine = fgets($stream);

                    if ($dataLine !== false && str_starts_with($dataLine, 'data: ')) {
                        $decoded = json_decode(trim(substr($dataLine, 6)), true);
                        throw new AiServiceProviderException($decoded['message'] ?? 'The AI provider is temporarily unavailable.');
                    }

                    throw new AiServiceProviderException('The AI provider returned a malformed error event.');
                }
            }
        } catch (ConnectionException $e) {
            // Mid-stream transport drop — same truncation contract as EOF,
            // mapped here so callers catch only the typed AiService
            // exceptions (the connect-phase ConnectionException is already
            // wrapped by chatStream()).
            throw new AiServiceProviderException('The AI provider stream ended unexpectedly.', 0, $e);
        }

        // Every exit from the loop is either `[DONE]` (returned above) or a
        // thrown error — reaching here means the stream was truncated.
        throw new AiServiceProviderException('The AI provider stream ended unexpectedly.');
    }

    /**
     * Single HTTP gateway to the sidecar: token header, loopback base URL,
     * bounded timeouts, and typed failure mapping. Sanitized failure logging
     * only — never logs tokens, queries, or response bodies.
     */
    private function send(string $method, string $path, array $body, int $timeout, int $retries): array
    {
        try {
            $request = $this->request($timeout, $retries);

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
    private function request(int $timeout, int $retries, bool $stream = false): PendingRequest
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
            throw new AiServiceAuthException('The AI assistant is temporarily unavailable.');
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
