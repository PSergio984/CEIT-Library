<?php

namespace Tests\Feature;

use App\Livewire\Pages\Student\AcademicPaperIndex;
use App\Models\AcademicPaper;
use App\Models\Author;
use App\Models\Dean;
use App\Models\Inventory;
use App\Models\ResearchAdviser;
use App\Models\TechnicalAdviser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AcademicPaperIndexSimilarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->actingAs($user);
    }

    private function fixtureSearch(): array
    {
        return json_decode(file_get_contents(base_path('tests/fixtures/ai-sidecar/search.json')), true);
    }

    private function seedPapers(): void
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

        $paper78 = AcademicPaper::create([
            'title' => 'Design of a Smart Flood Monitoring System',
            'publication_year' => 2025,
            'paper_type' => 'Thesis',
            'research_adviser_id' => $researchAdviser->id,
            'technical_adviser_id' => $technicalAdviser->id,
            'department' => 'Electrical Engineering',
            'dean_id' => $dean->id,
        ]);

        // Fixture says paper-77; make the DB match fixture ids exactly.
        $paper77->forceFill(['id' => 77])->save();
        $paper78->forceFill(['id' => 78])->save();

        $paper77->authors()->attach(Author::factory()->create(['name' => 'ROXAS, Harvey Jeremy C.']));
    }

    private function twoResultSearch(): array
    {
        $fixture = $this->fixtureSearch();

        $fixture['total'] = 2;
        $fixture['results'][] = [
            'id' => 'paper-78',
            'corpus' => 'catalog',
            'title' => 'Design of a Smart Flood Monitoring System',
            'score' => 0.01,
            'bm25_rank' => 2,
            'semantic_rank' => 1,
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

    #[Test]
    public function it_enters_recommendations_mode_with_header_and_cards(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Inventory::factory()->create(['academic_paper_id' => 78, 'copy_number' => 1, 'status' => 'Available']);
        Inventory::factory()->create(['academic_paper_id' => 78, 'copy_number' => 2, 'status' => 'Available']);
        Inventory::factory()->create(['academic_paper_id' => 78, 'copy_number' => 3, 'status' => 'Unavailable']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->twoResultSearch(), 200),
        ]);

        Livewire::test(AcademicPaperIndex::class)
            ->set('search', 'water pump')
            ->call('runHybridSearch')
            ->call('showSimilar', 77)
            ->assertSet('recommendedFor', 77)
            ->assertSet('recommendations.0.id', 78)
            ->assertSee('Showing similar books to:')
            ->assertSee('Back to results')
            ->assertSee('2 of 3 available');
    }

    #[Test]
    public function it_restores_prior_state_exactly_on_back(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->twoResultSearch(), 200),
        ]);

        $test = Livewire::test(AcademicPaperIndex::class)
            ->set('search', 'water pump')
            ->set('departmentFilter', 'Civil Engineering')
            ->call('runHybridSearch')
            ->call('showSimilar', 77)
            ->assertSet('recommendedFor', 77);

        $requestsBeforeBack = count(Http::recorded());

        $test->call('backToResults')
            ->assertSet('search', 'water pump')
            ->assertSet('departmentFilter', 'Civil Engineering')
            ->assertSet('recommendedFor', null)
            ->assertSet('recommendations', null)
            ->assertSet('recommendationsUnavailable', false)
            ->assertSet('hybridResults', fn ($results) => is_array($results) && $results[0]->id === 77)
            ->assertSet('paginators.academic-papers-index', 1);

        // Restore ran no query: the recorded request count is unchanged after Back.
        $this->assertCount($requestsBeforeBack, Http::recorded());
    }

    #[Test]
    public function it_exits_mode_when_search_is_edited(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->twoResultSearch(), 200),
        ]);

        Livewire::test(AcademicPaperIndex::class)
            ->set('search', 'water pump')
            ->call('runHybridSearch')
            ->call('showSimilar', 77)
            ->assertSet('recommendedFor', 77)
            ->set('search', 'pump')
            ->assertSet('recommendedFor', null);

        Http::assertSent(function ($request) {
            return $request['query'] === 'pump';
        });
    }

    #[Test]
    public function it_exits_mode_on_status_filter_and_still_exits_hybrid(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->twoResultSearch(), 200),
        ]);

        Livewire::test(AcademicPaperIndex::class)
            ->set('search', 'water pump')
            ->call('runHybridSearch')
            ->assertSet('hybridResults', fn ($results) => is_array($results) && count($results) === 2)
            ->call('showSimilar', 77)
            ->assertSet('recommendedFor', 77)
            ->set('statusFilter', 'Available')
            ->assertSet('recommendedFor', null)
            ->assertSet('hybridResults', null);
    }

    #[Test]
    public function it_recurses_similar_on_a_recommended_card(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->twoResultSearch(), 200),
        ]);

        Livewire::test(AcademicPaperIndex::class)
            ->set('search', 'water pump')
            ->call('runHybridSearch')
            ->call('showSimilar', 77)
            ->assertSet('recommendedFor', 77)
            ->assertSet('recommendations.0.id', 78)
            ->call('showSimilar', 78)
            ->assertSet('recommendedFor', 78)
            ->assertSet('recommendations.0.id', 77);
    }

    #[Test]
    public function it_renders_similar_loading_overlay_before_entering_mode(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->twoResultSearch(), 200),
        ]);

        // The overlay lives OUTSIDE the recommendations-mode guard, so it is
        // in the DOM before the first Similar click fires (D-16/W-2).
        Livewire::test(AcademicPaperIndex::class)
            ->set('search', 'water pump')
            ->call('runHybridSearch')
            ->assertSee('Finding similar books...')
            ->assertSeeHtml('wire:target="showSimilar, backToResults"');
    }

    #[Test]
    public function it_shows_unavailable_banner_when_sidecar_is_down(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/*' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        Livewire::test(AcademicPaperIndex::class)
            ->call('showSimilar', 77)
            ->assertSet('recommendationsUnavailable', true)
            ->assertSet('recommendations', [])
            ->assertSee('Recommendations unavailable right now')
            ->assertSee('Back to results');
    }

    #[Test]
    public function it_shows_empty_state_when_no_similar_books(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response([
                'query' => 'Analysis of Groundwater Depletion Caused By Excessive Use of Water Pumps',
                'total' => 0,
                'results' => [],
            ], 200),
        ]);

        Livewire::test(AcademicPaperIndex::class)
            ->call('showSimilar', 77)
            ->assertSet('recommendationsUnavailable', false)
            ->assertSet('recommendations', [])
            ->assertSee('No similar books found')
            ->assertSee('Back to results');
    }
}
