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

class AcademicPaperIndexHybridTest extends TestCase
{
    use RefreshDatabase;

    protected bool $disableLivewireLazyLoading = true;

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

        // A second paper that sorts BEFORE paper-77 by DB id — proves sidecar
        // ordering wins over DB ordering.
        $paperOther = AcademicPaper::create([
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
        $paperOther->forceFill(['id' => 78])->save();

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
                'url' => '/academic-papers/78',
            ],
        ];

        return $fixture;
    }

    #[Test]
    public function it_renders_hybrid_results_in_sidecar_order(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->twoResultSearch(), 200),
        ]);

        Livewire::test(AcademicPaperIndex::class)
            ->set('search', 'water pump')
            ->call('runHybridSearch')
            ->assertSet('aiSearchFailed', false)
            ->assertSet('hybridResults', function ($results) {
                $this->assertNotNull($results);

                return $results[0]->id === 77;
            })
            ->assertSeeInOrder(['Analysis of Groundwater Depletion', 'Design of a Smart Flood Monitoring System']);
    }

    #[Test]
    public function it_forwards_filter_values_to_the_sidecar(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->fixtureSearch(), 200),
        ]);

        Livewire::test(AcademicPaperIndex::class)
            ->set('search', 'water pump')
            ->set('departmentFilter', 'Information Technology')
            ->call('runHybridSearch');

        Http::assertSent(function ($request) {
            return $request['filters']['department'] === 'Information Technology'
                && $request['corpus'] === 'catalog'
                && $request['limit'] === 10;
        });
    }

    #[Test]
    public function it_falls_back_to_sql_search_when_sidecar_is_down(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::fake([
            'http://127.0.0.1:8310/*' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        Livewire::test(AcademicPaperIndex::class)
            ->set('search', 'water pump')
            ->call('runHybridSearch')
            ->assertSet('aiSearchFailed', true)
            ->assertSet('hybridResults', null)
            ->assertSee('AI search unavailable')
            ->assertSee('showing basic results')
            ->assertSee('Analysis of Groundwater Depletion'); // SQL path still renders
    }

    #[Test]
    public function it_skips_sidecar_requests_for_short_queries(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::fake([
            'http://127.0.0.1:8310/*' => Http::response([], 200),
        ]);

        Livewire::test(AcademicPaperIndex::class)
            ->set('search', 'ab')
            ->call('runHybridSearch')
            ->assertSet('hybridResults', null)
            ->assertSet('aiSearchFailed', false);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_exits_hybrid_mode_when_the_status_filter_changes(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Inventory::factory()->create([
            'academic_paper_id' => 77,
            'copy_number' => 1,
            'status' => 'Available',
        ]);

        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->twoResultSearch(), 200),
        ]);

        Livewire::test(AcademicPaperIndex::class)
            ->set('search', 'water pump')
            ->call('runHybridSearch')
            ->assertSet('hybridResults', function ($results) {
                return is_array($results) && count($results) === 2;
            })
            ->set('search', '')
            ->set('statusFilter', 'Unavailable')
            ->assertSet('hybridResults', null)
            ->assertSet('aiSearchFailed', false)
            ->assertDontSee('AI search unavailable')
            ->assertSee('Design of a Smart Flood Monitoring System')
            ->assertDontSee('Analysis of Groundwater Depletion');
    }

    #[Test]
    public function it_stays_out_of_hybrid_mode_while_a_status_filter_is_active(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::fake([
            'http://127.0.0.1:8310/*' => Http::response($this->fixtureSearch(), 200),
        ]);

        Livewire::test(AcademicPaperIndex::class)
            ->set('statusFilter', 'Unavailable')
            ->set('search', 'water pump')
            ->assertSet('hybridResults', null)
            ->assertSet('aiSearchFailed', false);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_renders_hydrated_availability_on_hybrid_cards(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Inventory::factory()->create(['academic_paper_id' => 77, 'copy_number' => 1, 'status' => 'Available']);
        Inventory::factory()->create(['academic_paper_id' => 77, 'copy_number' => 2, 'status' => 'Available']);
        Inventory::factory()->create(['academic_paper_id' => 77, 'copy_number' => 3, 'status' => 'Unavailable']);
        Inventory::factory()->create(['academic_paper_id' => 78, 'copy_number' => 1, 'status' => 'Unavailable']);
        Inventory::factory()->create(['academic_paper_id' => 78, 'copy_number' => 2, 'status' => 'Unavailable']);

        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->twoResultSearch(), 200),
        ]);

        Livewire::test(AcademicPaperIndex::class)
            ->set('search', 'water pump')
            ->call('runHybridSearch')
            ->assertSee('2 of 3 available')
            ->assertSee('0 of 2 available')
            ->assertSee('Checked just now');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/search')
                && ! array_key_exists('available', $request->data())
                && ! array_key_exists('total', $request->data());
        });
    }

    #[Test]
    public function it_renders_hydrated_availability_on_sql_cards(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Inventory::factory()->create(['academic_paper_id' => 77, 'copy_number' => 1, 'status' => 'Available']);
        Inventory::factory()->create(['academic_paper_id' => 77, 'copy_number' => 2, 'status' => 'Available']);
        Inventory::factory()->create(['academic_paper_id' => 77, 'copy_number' => 3, 'status' => 'Unavailable']);

        Livewire::test(AcademicPaperIndex::class)
            ->assertSee('2 of 3 available')
            ->assertSee('Checked just now');

        Http::assertNothingSent();
    }

    #[Test]
    public function it_renders_zero_of_zero_for_papers_without_inventory_rows(): void
    {
        $this->seedPapers();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->twoResultSearch(), 200),
        ]);

        Livewire::test(AcademicPaperIndex::class)
            ->set('search', 'water pump')
            ->call('runHybridSearch')
            ->assertSee('0 of 0 available');
    }
}
