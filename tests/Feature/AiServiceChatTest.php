<?php

namespace Tests\Feature;

use App\Exceptions\AiServiceAuthException;
use App\Exceptions\AiServiceProviderException;
use App\Exceptions\AiServiceUnavailableException;
use App\Services\AiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiServiceChatTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(base_path('tests/fixtures/ai-sidecar/'.$name));
    }

    #[Test]
    public function it_posts_chat_stream_with_locked_payload(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/chat/stream' => Http::response($this->fixture('chat-stream.txt'), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        (new AiService)->chatStream('what are the borrowing rules?', 'citations', 'policy', 3);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/chat/stream')
                && $request->hasHeader('X-Sidecar-Token', 'test-token')
                && $request['query'] === 'what are the borrowing rules?'
                && $request['mode'] === 'citations'
                && $request['corpus'] === 'policy'
                && $request['top_k'] === 3;
        });
    }

    #[Test]
    public function it_omits_corpus_when_null(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/chat/stream' => Http::response($this->fixture('chat-stream.txt'), 200),
        ]);

        (new AiService)->chatStream('x');

        Http::assertSent(fn ($request) => ! array_key_exists('corpus', $request->data()));
    }

    #[Test]
    public function it_reads_sse_chunks_in_order_and_stops_at_done(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/chat/stream' => Http::response($this->fixture('chat-stream.txt'), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $response = (new AiService)->chatStream('x');
        $chunks = iterator_to_array((new AiService)->chatStreamEvents($response));

        $this->assertSame(['CEIT ', 'Library '], $chunks);
    }

    #[Test]
    public function it_throws_provider_exception_on_error_event(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/chat/stream' => Http::response(
                "event: error\ndata: {\"code\": \"provider_error\", \"message\": \"The AI provider is temporarily unavailable. Please try again.\"}\n\ndata: [DONE]\n\n",
                200,
                ['Content-Type' => 'text/event-stream']
            ),
        ]);

        $response = (new AiService)->chatStream('x');

        $this->expectException(AiServiceProviderException::class);
        $this->expectExceptionMessage('The AI provider is temporarily unavailable. Please try again.');

        iterator_to_array((new AiService)->chatStreamEvents($response));
    }

    #[Test]
    public function it_throws_auth_exception_on_401(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/*' => Http::response([], 401),
        ]);

        $this->expectException(AiServiceAuthException::class);

        (new AiService)->chatStream('x');
    }

    #[Test]
    public function it_throws_unavailable_on_connection_failure(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/*' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->expectException(AiServiceUnavailableException::class);

        (new AiService)->chatStream('x');
    }

    #[Test]
    public function it_does_not_retry_chat_stream(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/chat/stream' => Http::response([], 500),
        ]);

        try {
            (new AiService)->chatStream('x');
            $this->fail('Expected AiServiceUnavailableException was not thrown.');
        } catch (AiServiceUnavailableException) {
            // expected
        }

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_preserves_newlines_inside_streamed_chunks(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/chat/stream' => Http::response($this->fixture('chat-stream-newlines.txt'), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $response = (new AiService)->chatStream('x');
        $chunks = iterator_to_array((new AiService)->chatStreamEvents($response));

        $this->assertSame(["Line one\n", "\nLine three"], $chunks);
        $this->assertSame("Line one\n\nLine three", implode('', $chunks));
    }

    #[Test]
    public function it_throws_provider_exception_when_stream_ends_without_done(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/chat/stream' => Http::response($this->fixture('chat-stream-truncated.txt'), 200, ['Content-Type' => 'text/event-stream']),
        ]);

        $response = (new AiService)->chatStream('x');

        $this->expectException(AiServiceProviderException::class);
        $this->expectExceptionMessage('The AI provider stream ended unexpectedly.');

        iterator_to_array((new AiService)->chatStreamEvents($response));
    }

    #[Test]
    public function it_throws_on_malformed_error_event(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/chat/stream' => Http::response(
                "event: error\n\n",
                200,
                ['Content-Type' => 'text/event-stream']
            ),
        ]);

        $response = (new AiService)->chatStream('x');

        $this->expectException(AiServiceProviderException::class);
        $this->expectExceptionMessage('The AI provider returned a malformed error event.');

        iterator_to_array((new AiService)->chatStreamEvents($response));
    }

    #[Test]
    public function each_typed_exception_exposes_its_error_taxonomy_code(): void
    {
        $this->assertSame('auth_failed', (new AiServiceAuthException)->errorCode());
        $this->assertSame('unavailable', (new AiServiceUnavailableException)->errorCode());
        $this->assertSame('provider_error', (new AiServiceProviderException)->errorCode());
    }
}
