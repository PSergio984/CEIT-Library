<?php

namespace Tests\Feature;

use App\Livewire\Pages\Student\RecommendedForYou;
use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecommendedForYouTest extends TestCase
{
    use RefreshDatabase;

    protected bool $disableLivewireLazyLoading = true;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ai_sidecar.token' => 'test-token']);
    }

    private function fixtureRecommend(): array
    {
        return json_decode(file_get_contents(base_path('tests/fixtures/ai-sidecar/recommend.json')), true);
    }

    private function recommendResponseFor(array $paperIds): array
    {
        $fixture = $this->fixtureRecommend();
        $entry = $fixture['results'][0];

        $fixture['total'] = count($paperIds);
        $fixture['results'] = array_map(
            fn ($id) => array_merge($entry, ['id' => "paper-{$id}"]),
            $paperIds,
        );

        return $fixture;
    }

    private int $copyNumber = 0;

    private function borrow(User $user, AcademicPaper $paper, string $status, Carbon $timeIn): BorrowTransaction
    {
        $inventory = Inventory::factory()->create([
            'academic_paper_id' => $paper->id,
            'copy_number' => ++$this->copyNumber,
            'status' => 'Unavailable',
        ]);

        return BorrowTransaction::factory()->create([
            'user_id' => $user->id,
            'academic_paper_id' => $paper->id,
            'inventory_id' => $inventory->id,
            'status' => $status,
            'time_in' => $timeIn,
            'time_out' => $status === 'completed' ? $timeIn->copy()->addHour() : null,
            'expires_at' => $timeIn->copy()->addHours(6),
        ]);
    }

    #[Test]
    public function it_renders_the_populated_state_with_live_availability(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $seedPaper = AcademicPaper::factory()->create();
        $paperA = AcademicPaper::factory()->create();
        $paperB = AcademicPaper::factory()->create();

        $this->borrow($user, $seedPaper, 'completed', Carbon::now()->subDay());

        Inventory::factory()->create(['academic_paper_id' => $paperA->id, 'copy_number' => 1, 'status' => 'Available']);
        Inventory::factory()->create(['academic_paper_id' => $paperA->id, 'copy_number' => 2, 'status' => 'Unavailable']);
        Inventory::factory()->create(['academic_paper_id' => $paperB->id, 'copy_number' => 1, 'status' => 'Unavailable']);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response(
                $this->recommendResponseFor([$paperA->id, $paperB->id]),
                200,
            ),
        ]);

        Livewire::test(RecommendedForYou::class)
            ->assertSee('Recommended for you')
            ->assertSee($paperA->title)
            ->assertSee($paperB->title)
            ->assertSee('1 of 2 available')
            ->assertSee('0 of 1 available')
            ->assertSee('/academic-papers?paper='.$paperA->id)
            ->assertDontSee('Popular right now');
    }

    #[Test]
    public function it_renders_the_cold_start_state_as_popular_without_calling_the_sidecar(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $other = User::factory()->create();

        $popular = AcademicPaper::factory()->create();
        $this->borrow($other, $popular, 'completed', Carbon::now()->subDay());

        Http::preventStrayRequests();

        Livewire::test(RecommendedForYou::class)
            ->assertSee('Popular right now')
            ->assertSee($popular->title)
            ->assertDontSee('Recommendations unavailable right now');

        Http::assertNothingSent();
    }

    #[Test]
    public function it_renders_the_empty_state_when_there_is_nothing_to_recommend(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Http::preventStrayRequests();

        Livewire::test(RecommendedForYou::class)
            ->assertSee('No recommendations yet')
            ->assertDontSee('Recommendations unavailable right now');

        Http::assertNothingSent();
    }

    #[Test]
    public function it_renders_the_unavailable_state_when_the_sidecar_fails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $paper = AcademicPaper::factory()->create();
        $this->borrow($user, $paper, 'completed', Carbon::now()->subDay());

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/*' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        Livewire::test(RecommendedForYou::class)
            ->assertSee('Recommendations unavailable right now')
            ->assertDontSee('Popular right now');
    }

    #[Test]
    public function it_renders_the_shelf_on_the_dashboard_without_breaking_other_sections(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student);

        $paper = AcademicPaper::factory()->create();
        $this->borrow($student, $paper, 'completed', Carbon::now()->subDay());

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/*' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Student Dashboard')
            ->assertSee('Recommendations unavailable right now')
            ->assertSee('Recently Added Papers');
    }
}
