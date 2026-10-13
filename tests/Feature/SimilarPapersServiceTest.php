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

        // paper-77 in the fixture is "Analysis of Groundwater Depletion..." CE
        $paper77 = AcademicPaper::create([
            'title' => 'Analysis of Groundwater Depletion Caused By Excessive Use of Water Pumps',
            'publication_year' => 2015,
            'paper_type' => 'Thesis',
            'research_adviser_id' => $researchAdviser->id,
            'technical_adviser_id' => $technicalAdviser->id,
            'department' => 'Civil Engineering',
            'dean_id' => $dean->id,
        ]);

        // paper-78 sorts after 77 in the two-result fixture.
        $paper78 = AcademicPaper::create([
            'title' => 'Design of a Smart Flood Monitoring System',
            'publication_year' => 2025,
            'paper_type' => 'Thesis',
            'research_adviser_id' => $researchAdviser->id,
            'technical_adviser_id' => $technicalAdviser->id,
            'department' => 'Electrical Engineering',
            'dean_id' => $dean->id,
        ]);

        // Fixture says paper-77/paper-78; make the DB match fixture ids exactly.
        $paper77->forceFill(['id' => 77])->save();
        $paper78->forceFill(['id' => 78])->save();

        $paper77->authors()->attach(Author::factory()->create(['name' => 'ROXAS, Harvey Jeremy C.']));

        return $paper77;
    }

    private function twoResultRecommend(): array
    {
        $fixture = $this->fixtureRecommend();

        $fixture['total'] = 2;
        $fixture['results'][] = [
            'id' => 'paper-78',
            'corpus' => 'catalog',
            'title' => 'Design of a Smart Flood Monitoring System',
            'score' => 0.01,
            'metadata' => [
                'catalog_code' => 'CEIT-EE-25-01',
                'department' => 'Electrical Engineering',
                'publication_year' => 2025,
                'paper_type' => 'Thesis',
                'authors' => ['Maria Santos'],
                'url' => '/academic-papers?paper=78',
            ],
        ];

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

        $this->assertSame([78], $result->pluck('id')->all());
        $this->assertNotContains(77, $result->pluck('id')->all());
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

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response($this->fixtureRecommend(), 200),
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
