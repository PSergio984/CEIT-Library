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

    private function fixtureSearch(): array
    {
        return json_decode(file_get_contents(base_path('tests/fixtures/ai-sidecar/search.json')), true);
    }

    private function seedPaper77(): AcademicPaper
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

        // Fixture says paper-77; make the DB match fixture ids exactly.
        $paper77->forceFill(['id' => 77])->save();

        $paper77->authors()->attach(Author::factory()->create(['name' => 'ROXAS, Harvey Jeremy C.']));

        return $paper77;
    }

    #[Test]
    public function it_queries_with_title_only_verbatim(): void
    {
        $paper77 = $this->seedPaper77();
        config(['services.ai_sidecar.token' => 'test-token']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/search' => Http::response($this->fixtureSearch(), 200),
        ]);

        (new SimilarPapersService)->for($paper77);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/search')
                && $request->hasHeader('X-Sidecar-Token', 'test-token')
                && $request['query'] === 'Analysis of Groundwater Depletion Caused By Excessive Use of Water Pumps'
                && $request['filters'] === []
                && $request['corpus'] === 'catalog'
                && $request['limit'] === 10
                && $request['k'] === 60
                && array_keys($request->data()) === ['query', 'filters', 'corpus', 'limit', 'k'];
        });
    }
}
