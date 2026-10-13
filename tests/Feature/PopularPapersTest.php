<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\User;
use App\Services\PersonalRecommendationsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PopularPapersTest extends TestCase
{
    use RefreshDatabase;

    private int $copyNumber = 0;

    private function borrow(User $user, AcademicPaper $paper, string $status, Carbon $at): BorrowTransaction
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
            'time_in' => $at,
            'time_out' => $status === 'completed' ? $at->copy()->addHour() : null,
            'expires_at' => $at->copy()->addHours(6),
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    #[Test]
    public function it_ranks_by_borrow_count_desc_then_paper_id_asc_within_90_days(): void
    {
        $user = User::factory()->create();
        $paperA = AcademicPaper::factory()->create();
        $paperB = AcademicPaper::factory()->create();
        $paperC = AcademicPaper::factory()->create();
        $ancient = AcademicPaper::factory()->create();

        // A and B tie at 3 recent borrows (id asc breaks the tie: A has the
        // lower id since it was created first), C has 1. `ancient` has the
        // most borrows overall but all outside the 90-day window.
        foreach (range(1, 3) as $i) {
            $this->borrow($user, $paperA, 'completed', Carbon::now()->subDays($i));
            $this->borrow($user, $paperB, 'completed', Carbon::now()->subDays($i));
        }
        $this->borrow($user, $paperC, 'started', Carbon::now()->subDay());
        foreach (range(1, 10) as $i) {
            $this->borrow($user, $ancient, 'completed', Carbon::now()->subDays(100 + $i));
        }

        $result = (new PersonalRecommendationsService)->popular();

        $this->assertSame(
            [$paperA->id, $paperB->id, $paperC->id],
            $result->pluck('id')->all()
        );
    }

    #[Test]
    public function it_widens_to_all_time_when_the_90_day_window_is_empty(): void
    {
        $user = User::factory()->create();
        $old = AcademicPaper::factory()->create();
        $older = AcademicPaper::factory()->create();

        $this->borrow($user, $old, 'completed', Carbon::now()->subDays(120));
        $this->borrow($user, $old, 'completed', Carbon::now()->subDays(110));
        $this->borrow($user, $older, 'completed', Carbon::now()->subDays(200));

        $result = (new PersonalRecommendationsService)->popular();

        $this->assertSame([$old->id, $older->id], $result->pluck('id')->all());
    }

    #[Test]
    public function it_returns_at_most_the_limit(): void
    {
        $user = User::factory()->create();
        $papers = AcademicPaper::factory()->count(10)->create();

        $papers->each(fn ($paper) => $this->borrow($user, $paper, 'completed', Carbon::now()->subDay()));

        $result = (new PersonalRecommendationsService)->popular();

        $this->assertCount(8, $result);
    }

    #[Test]
    public function it_caches_the_popular_list_for_an_hour(): void
    {
        $user = User::factory()->create();
        $paperA = AcademicPaper::factory()->create();
        $paperB = AcademicPaper::factory()->create();

        $this->borrow($user, $paperA, 'completed', Carbon::now()->subDays(2));
        $this->borrow($user, $paperA, 'completed', Carbon::now()->subDay());

        $service = new PersonalRecommendationsService;
        $this->assertSame([$paperA->id], $service->popular()->pluck('id')->all());

        // B overtakes A within the hour — the cached list must win.
        foreach (range(1, 5) as $i) {
            $this->borrow($user, $paperB, 'completed', Carbon::now()->subHours($i));
        }

        $this->assertSame([$paperA->id], $service->popular()->pluck('id')->all());

        Cache::forget('recommendations.popular.v1');

        $this->assertSame(
            [$paperB->id, $paperA->id],
            $service->popular()->pluck('id')->all()
        );
    }

    #[Test]
    public function it_drops_cached_ids_whose_paper_no_longer_exists(): void
    {
        $paper = AcademicPaper::factory()->create();

        Cache::put('recommendations.popular.v1', [$paper->id, 99999], now()->addHour());

        $result = (new PersonalRecommendationsService)->popular();

        $this->assertSame([$paper->id], $result->pluck('id')->all());
    }

    #[Test]
    public function the_cached_pool_is_not_truncated_by_the_first_callers_limit(): void
    {
        $user = User::factory()->create();
        $papers = AcademicPaper::factory()->count(10)->create();

        // Distinct borrow counts 10, 9, …, 1 in creation order — a fully
        // deterministic ranking before the id-asc tiebreak is even needed.
        $papers->each(function ($paper, $i) use ($user) {
            foreach (range(1, 10 - $i) as $daysAgo) {
                $this->borrow($user, $paper, 'completed', Carbon::now()->subDays($daysAgo));
            }
        });

        $service = new PersonalRecommendationsService;

        // A smaller-limit first caller must not poison the shared cache
        // entry for the hour.
        $this->assertCount(3, $service->popular(3));

        $ranking = $papers->pluck('id')->all();
        $this->assertSame(array_slice($ranking, 0, 8), $service->popular()->pluck('id')->all());
        $this->assertCount(8, $service->popular());

        // And a later smaller limit slices the same cached ranking.
        $this->assertSame(array_slice($ranking, 0, 3), $service->popular(3)->pluck('id')->all());
    }

    #[Test]
    public function it_returns_empty_when_nothing_has_ever_been_borrowed(): void
    {
        AcademicPaper::factory()->count(3)->create();

        $result = (new PersonalRecommendationsService)->popular();

        $this->assertTrue($result->isEmpty());
    }
}
