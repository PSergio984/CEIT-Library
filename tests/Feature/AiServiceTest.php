<?php

namespace Tests\Feature;

use App\Exceptions\AiServiceAuthException;
use App\Exceptions\AiServiceUnavailableException;
use App\Services\AiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiServiceTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(base_path('tests/fixtures/ai-sidecar/'.$name));
    }

    #[Test]
    public function it_posts_search_with_token_and_locked_payload(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response(json_decode($this->fixture('search.json'), true), 200),
        ]);

        (new AiService)->search('water pump', ['department' => 'Civil Engineering'], 'catalog', 10);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/search')
                && $request->hasHeader('X-Sidecar-Token', 'test-token')
                && $request['query'] === 'water pump'
                && $request['filters'] === ['department' => 'Civil Engineering']
                && $request['corpus'] === 'catalog'
                && $request['limit'] === 10
                && $request['k'] === 60
                && array_keys($request->data()) === ['query', 'filters', 'corpus', 'limit', 'k']
                && ! array_key_exists('available', $request->data())
                && ! array_key_exists('total', $request->data())
                && ! array_key_exists('checked_at', $request->data());
        });
    }

    #[Test]
    public function it_sends_exactly_the_adr_0004_search_keys(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response(json_decode($this->fixture('search.json'), true), 200),
        ]);

        (new AiService)->search('water pump', [], 'catalog', 10);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/search')
                && array_keys($request->data()) === ['query', 'filters', 'corpus', 'limit', 'k']
                && ! array_key_exists('available', $request->data())
                && ! array_key_exists('total', $request->data())
                && ! array_key_exists('checked_at', $request->data());
        });
    }

    #[Test]
    public function it_passes_author_and_adviser_filter_keys_to_the_sidecar(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response(json_decode($this->fixture('search.json'), true), 200),
        ]);

        (new AiService)->search('solar', ['author' => 'Juan Dela Cruz', 'adviser' => 'Engr. Jose Santos']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/search')
                && array_keys($request->data()) === ['query', 'filters', 'corpus', 'limit', 'k']
                && $request['filters'] === ['author' => 'Juan Dela Cruz', 'adviser' => 'Engr. Jose Santos'];
        });
    }

    #[Test]
    public function it_returns_search_results_shape(): void
    {
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response(json_decode($this->fixture('search.json'), true), 200),
        ]);

        $results = (new AiService)->search('water pump');

        $this->assertSame(1, $results['total']);
        $this->assertSame('paper-77', $results['results'][0]['id']);
        $this->assertSame('CEIT-CE-15-014', $results['results'][0]['metadata']['catalog_code']);
        $this->assertSame('v1', $results['contract_version']);
    }

    #[Test]
    public function it_throws_auth_exception_on_401(): void
    {
        Http::fake([
            'http://127.0.0.1:8310/*' => Http::response([], 401),
        ]);

        $this->expectException(AiServiceAuthException::class);

        (new AiService)->search('water pump');
    }

    #[Test]
    public function it_throws_unavailable_exception_on_connection_failure(): void
    {
        Http::fake([
            'http://127.0.0.1:8310/*' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->expectException(AiServiceUnavailableException::class);

        (new AiService)->search('water pump');
    }

    #[Test]
    public function it_throws_unavailable_exception_on_500(): void
    {
        Http::fake([
            'http://127.0.0.1:8310/*' => Http::response([], 500),
        ]);

        $this->expectException(AiServiceUnavailableException::class);

        (new AiService)->search('water pump');
    }

    #[Test]
    public function it_logs_sanitized_warning_on_failure(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::fake([
            'http://127.0.0.1:8310/*' => Http::response([], 500),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('AI sidecar request failed', \Mockery::on(function ($context) {
                return is_array($context)
                    && array_keys($context) === ['endpoint', 'reason']
                    && $context['endpoint'] === '/search'
                    && $context['reason'] === 'http_500'
                    && ! str_contains(json_encode($context), 'water pump')
                    && ! str_contains(json_encode($context), 'test-token');
            }));

        try {
            (new AiService)->search('water pump');
        } catch (AiServiceUnavailableException) {
            // expected
        }
    }

    #[Test]
    public function it_logs_connection_reason_on_connection_failure(): void
    {
        Http::fake([
            'http://127.0.0.1:8310/*' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('AI sidecar request failed', \Mockery::on(function ($context) {
                return is_array($context)
                    && array_keys($context) === ['endpoint', 'reason']
                    && $context['endpoint'] === '/search'
                    && $context['reason'] === 'connection';
            }));

        try {
            (new AiService)->search('water pump');
        } catch (AiServiceUnavailableException) {
            // expected
        }
    }

    #[Test]
    public function it_logs_auth_reason_on_401(): void
    {
        Http::fake([
            'http://127.0.0.1:8310/*' => Http::response([], 401),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('AI sidecar request failed', \Mockery::on(function ($context) {
                return is_array($context)
                    && array_keys($context) === ['endpoint', 'reason']
                    && $context['endpoint'] === '/search'
                    && $context['reason'] === 'auth';
            }));

        try {
            (new AiService)->search('water pump');
        } catch (AiServiceAuthException) {
            // expected
        }
    }

    #[Test]
    public function it_retries_search_once_then_succeeds(): void
    {
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::sequence()
                ->push([], 500)
                ->push(json_decode($this->fixture('search.json'), true), 200),
        ]);

        $results = (new AiService)->search('water pump');

        $this->assertSame(1, $results['total']);
    }

    #[Test]
    public function it_rebuilds_index_with_post(): void
    {
        Http::fake([
            'http://127.0.0.1:8310/index/rebuild' => Http::response(json_decode($this->fixture('rebuild.json'), true), 200),
        ]);

        $result = (new AiService)->rebuildIndex();

        $this->assertSame('rebuilt', $result['status']);
        $this->assertSame(153, $result['documents']);

        Http::assertSent(fn ($request) => $request->url() === 'http://127.0.0.1:8310/index/rebuild' && $request->method() === 'POST');
    }

    #[Test]
    public function it_fetches_health_with_get(): void
    {
        Http::fake([
            'http://127.0.0.1:8310/health' => Http::response(json_decode($this->fixture('health.json'), true), 200),
        ]);

        $health = (new AiService)->health();

        $this->assertSame('ok', $health['status']);
        $this->assertSame('v1', $health['contract_version']);
        $this->assertSame(153, $health['index']['documents']);
    }

    #[Test]
    public function it_posts_feedback_with_the_exact_sidecar_payload(): void
    {
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/feedback' => Http::response(['status' => 'recorded', 'rating' => 'up'], 200),
        ]);

        $ok = (new AiService)->feedback('what is CEIT-IT-23-01?', 'up', 'It is a paper about…', ['paper-1', 'paper-2']);

        $this->assertTrue($ok);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/feedback')
                && $request->method() === 'POST'
                && $request->hasHeader('X-Sidecar-Token', 'test-token')
                && array_keys($request->data()) === ['query', 'rating', 'answer', 'result_ids']
                && $request['query'] === 'what is CEIT-IT-23-01?'
                && $request['rating'] === 'up'
                && $request['answer'] === 'It is a paper about…'
                && $request['result_ids'] === ['paper-1', 'paper-2'];
        });
    }

    #[Test]
    public function it_swallows_feedback_failures(): void
    {
        Http::fake([
            'http://127.0.0.1:8310/feedback' => Http::response([], 500),
        ]);

        $ok = (new AiService)->feedback('some query', 'down');

        $this->assertFalse($ok);
    }
}
