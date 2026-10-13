<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\Author;
use App\Models\Dean;
use App\Models\ResearchAdviser;
use App\Models\TechnicalAdviser;
use App\Models\User;
use App\Services\SimilarPapersService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SimilarPapersServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->actingAs($user);
    }

    private function fixtureRecommend(): array
    {
        return json_decode(file_get_contents(base_path('tests/fixtures/ai-sidecar/recommend.json')), true);
    }

    private function seedPapers(): AcademicPaper
    {
        $researchAdviser = ResearchAdviser::factory()->create(['name' => 'Engr. Jose Rizal']);
        $technicalAdviser = TechnicalAdviser::factory()->create(['name' => 'Engr. Andres Bonifacio']);
        $dean = Dean::factory()->create(['name' => 'Dr. Emilio Aguinaldo']);

        // paper-77 is the viewed (seed) paper — the real sidecar never
        // returns it, since the service also sends it in exclude_ids.
        $paper77 = AcademicPaper::factory()->create([
            'title' => 'Analysis of Groundwater Depletion Caused By Excessive Use of Water Pumps',
            'publication_year' => 2015,
            'paper_type' => 'Thesis',
            'research_adviser_id' => $researchAdviser->id,
            'technical_adviser_id' => $technicalAdviser->id,
            'department' => 'Civil Engineering',
            'dean_id' => $dean->id,
        ]);

        // paper-78 is the fixture's single result; paper-79 exists so the
        // two-result variant can prove sidecar rank order beats DB order.
        $paper78 = AcademicPaper::factory()->create([
            'title' => 'Design of a Smart Flood Monitoring System',
            'publication_year' => 2025,
            'paper_type' => 'Thesis',
            'research_adviser_id' => $researchAdviser->id,
            'technical_adviser_id' => $technicalAdviser->id,
            'department' => 'Electrical Engineering',
            'dean_id' => $dean->id,
        ]);

        $paper79 = AcademicPaper::factory()->create([
            'title' => 'Development of a Library Management Mobile Application',
            'publication_year' => 2023,
            'paper_type' => 'Capstone',
            'research_adviser_id' => $researchAdviser->id,
            'technical_adviser_id' => $technicalAdviser->id,
            'department' => 'Information Technology',
            'dean_id' => $dean->id,
        ]);

        // Responses use paper-N ids; make the DB match them exactly.
        $paper77->forceFill(['id' => 77])->save();
        $paper78->forceFill(['id' => 78])->save();
        $paper79->forceFill(['id' => 79])->save();

        $paper77->authors()->attach(Author::factory()->create(['name' => 'ROXAS, Harvey Jeremy C.']));

        return $paper77;
    }

    private function twoResultRecommend(): array
    {
        $fixture = $this->fixtureRecommend();

        // [79, 78] — the sidecar order is NOT id order, so hydration must
        // preserve it.
        $fixture['total'] = 2;
        array_unshift($fixture['results'], [
            'id' => 'paper-79',
            'corpus' => 'catalog',
            'title' => 'Development of a Library Management Mobile Application',
            'score' => 0.9,
            'metadata' => [
                'catalog_code' => 'CEIT-IT-23-07',
                'department' => 'Information Technology',
                'publication_year' => 2023,
                'paper_type' => 'Capstone',
                'authors' => ['Pedro Reyes'],
                'url' => '/academic-papers?paper=79',
            ],
        ]);

        return $fixture;
    }

    private function emptyRecommend(): array
    {
        return ['seeds_used' => 1, 'total' => 0, 'took_ms' => 4, 'results' => []];
    }

    #[Test]
    public function it_posts_recommend_with_seed_and_locked_payload(): void
    {
        $paper77 = $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response($this->fixtureRecommend(), 200),
        ]);

        (new SimilarPapersService)->for($paper77);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/recommend')
                && $request->hasHeader('X-Sidecar-Token', 'test-token')
                && $request['seed_ids'] === ['paper-77']
                && $request['exclude_ids'] === ['paper-77']
                && $request['limit'] === 10
                && array_keys($request->data()) === ['seed_ids', 'exclude_ids', 'limit'];
        });
    }

    #[Test]
    public function it_excludes_the_seed_paper_and_keeps_rank_order(): void
    {
        $paper77 = $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response($this->twoResultRecommend(), 200),
        ]);

        $service = new SimilarPapersService;
        $result = $service->for($paper77);

        $this->assertSame([79, 78], $result->pluck('id')->all());
        $this->assertNotContains(77, $result->pluck('id')->all());
        $this->assertFalse($service->unavailable);
    }

    #[Test]
    public function it_drops_the_seed_if_the_sidecar_returns_it_anyway(): void
    {
        // Defence in depth: a contract-violating response that still
        // contains the seed id must not surface the viewed paper.
        $paper77 = $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        $response = $this->fixtureRecommend();
        $response['total'] = 2;
        array_unshift($response['results'], array_merge($response['results'][0], [
            'id' => 'paper-77',
            'title' => 'Analysis of Groundwater Depletion Caused By Excessive Use of Water Pumps',
        ]));

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response($response, 200),
        ]);

        $service = new SimilarPapersService;
        $result = $service->for($paper77);

        $this->assertSame([78], $result->pluck('id')->all());
        $this->assertFalse($service->unavailable);
    }

    #[Test]
    public function it_is_deterministic_across_calls(): void
    {
        $paper77 = $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response($this->twoResultRecommend(), 200),
        ]);

        $first = (new SimilarPapersService)->for($paper77);
        $second = (new SimilarPapersService)->for($paper77);

        $this->assertSame($first->pluck('id')->all(), $second->pluck('id')->all());
    }

    #[Test]
    public function it_returns_empty_when_only_the_seed_matches(): void
    {
        $paper77 = $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        // An all-seed response (contract violation) yields an empty list.
        $response = $this->fixtureRecommend();
        $response['results'][0] = array_merge($response['results'][0], [
            'id' => 'paper-77',
            'title' => 'Analysis of Groundwater Depletion Caused By Excessive Use of Water Pumps',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response($response, 200),
        ]);

        $service = new SimilarPapersService;
        $result = $service->for($paper77);

        $this->assertTrue($result->isEmpty());
        $this->assertFalse($service->unavailable);
    }

    #[Test]
    public function it_returns_empty_when_the_response_has_no_results_key(): void
    {
        $paper77 = $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        // A malformed-but-200 body (empty / non-JSON, or a JSON body without
        // a results key) is treated as an empty result, not a failure.
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response('', 200),
        ]);

        $service = new SimilarPapersService;
        $result = $service->for($paper77);

        $this->assertTrue($result->isEmpty());
        $this->assertFalse($service->unavailable);
    }

    #[Test]
    public function it_returns_empty_when_the_response_lacks_results_but_is_json(): void
    {
        $paper77 = $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response(['seeds_used' => 0], 200),
        ]);

        $service = new SimilarPapersService;
        $result = $service->for($paper77);

        $this->assertTrue($result->isEmpty());
        $this->assertFalse($service->unavailable);
    }

    #[Test]
    public function it_ignores_malformed_recommendation_ids(): void
    {
        $paper77 = $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        // Only strict `paper-<digits>` ids map back — a malformed id like
        // "paper-78-old" must not be coerced onto paper 78.
        $response = $this->fixtureRecommend();
        $entry = $response['results'][0];
        $response['total'] = 4;
        $response['results'] = [
            array_merge($entry, ['id' => 'paper-78-old']),
            array_merge($entry, ['id' => '78']),
            array_merge($entry, ['id' => 'paper-abc']),
            $entry,
        ];

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response($response, 200),
        ]);

        $service = new SimilarPapersService;
        $result = $service->for($paper77);

        $this->assertSame([78], $result->pluck('id')->all());
        $this->assertFalse($service->unavailable);
    }

    #[Test]
    public function it_returns_empty_when_all_recommendation_ids_are_malformed(): void
    {
        $paper77 = $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        $response = $this->fixtureRecommend();
        $entry = $response['results'][0];
        $response['total'] = 3;
        $response['results'] = [
            array_merge($entry, ['id' => 'paper-78-old']),
            array_merge($entry, ['id' => '78']),
            array_merge($entry, ['id' => 'paper-abc']),
        ];

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response($response, 200),
        ]);

        $service = new SimilarPapersService;
        $result = $service->for($paper77);

        $this->assertTrue($result->isEmpty());
        $this->assertFalse($service->unavailable);
    }

    #[Test]
    public function it_returns_empty_collection_for_empty_retrieval(): void
    {
        $paper77 = $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response($this->emptyRecommend(), 200),
        ]);

        $service = new SimilarPapersService;
        $result = $service->for($paper77);

        $this->assertTrue($result->isEmpty());
        $this->assertFalse($service->unavailable);
    }

    #[Test]
    public function it_fails_closed_when_sidecar_is_down(): void
    {
        $paper77 = $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/*' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $service = new SimilarPapersService;
        $result = $service->for($paper77);

        $this->assertTrue($result->isEmpty());
        $this->assertTrue($service->unavailable);
    }

    #[Test]
    public function it_fails_closed_on_auth_failure(): void
    {
        $paper77 = $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/*' => Http::response(['error' => ['code' => 'auth_failed', 'message' => 'missing or invalid X-Sidecar-Token']], 401),
        ]);

        $service = new SimilarPapersService;
        $result = $service->for($paper77);

        $this->assertTrue($result->isEmpty());
        $this->assertTrue($service->unavailable);
    }
}
