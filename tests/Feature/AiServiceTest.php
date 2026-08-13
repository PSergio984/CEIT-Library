<?php

namespace Tests\Feature;

use App\Exceptions\AiServiceAuthException;
use App\Exceptions\AiServiceUnavailableException;
use App\Services\AiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
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
                && $request['k'] === 60;
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
        $this->assertSame('v1', $results['results'][0]['contract_version'] ?? $results['contract_version'] ?? null);
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
}
