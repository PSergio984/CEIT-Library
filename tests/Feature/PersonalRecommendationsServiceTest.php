<?php

namespace Tests\Feature;

use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\Inventory;
use App\Models\User;
use App\Services\PersonalRecommendationsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonalRecommendationsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.ai_sidecar.token' => 'test-token']);
    }

    private function fixtureRecommend(): array
    {
        return json_decode(file_get_contents(base_path('tests/fixtures/ai-sidecar/recommend.json')), true);
    }

    private function emptyRecommend(): array
    {
        return ['seeds_used' => 1, 'total' => 0, 'took_ms' => 4, 'results' => []];
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
    public function it_seeds_from_the_last_10_distinct_borrows_newest_first_regardless_of_status(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response($this->emptyRecommend(), 200),
        ]);

        $user = User::factory()->create();
        $papers = AcademicPaper::factory()->count(11)->create();

        // 11 distinct papers borrowed oldest → newest, then paper[0] again
        // most recently (a repeat). Statuses vary: every status counts.
        $statuses = ['completed', 'started', 'overdue'];
        $base = Carbon::now()->subDays(30);
        $papers->each(function ($paper, $i) use ($user, $statuses, $base) {
            $this->borrow($user, $paper, $statuses[$i % 3], $base->copy()->addDays($i));
        });
        $this->borrow($user, $papers[0], 'started', Carbon::now());

        (new PersonalRecommendationsService)->for($user);

        // Distinct newest-first is [p0 (re-borrowed newest), p10, p9, …, p1];
        // the seed cap drops the oldest one (p1), leaving exactly 10 seeds.
        $expected = collect([$papers[0]->id])
            ->merge($papers->slice(1)->reverse()->pluck('id'))
            ->take(10)
            ->map(fn ($id) => "paper-{$id}")
            ->all();

        Http::assertSent(
            fn ($request) => str_contains($request->url(), '/recommend')
                && $request['seed_ids'] === $expected
        );
    }

    #[Test]
    public function it_excludes_every_borrowed_paper_and_nothing_else(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response($this->emptyRecommend(), 200),
        ]);

        $user = User::factory()->create();
        $papers = AcademicPaper::factory()->count(15)->create();
        $otherPaper = AcademicPaper::factory()->create();

        $base = Carbon::now()->subDays(20);
        $papers->each(function ($paper, $i) use ($user, $base) {
            $this->borrow($user, $paper, 'completed', $base->copy()->addDays($i));
        });

        (new PersonalRecommendationsService)->for($user);

        $expected = $papers->pluck('id')->map(fn ($id) => "paper-{$id}")->all();

        Http::assertSent(function ($request) use ($expected, $otherPaper) {
            return count($request['exclude_ids']) === 15
                && collect($request['exclude_ids'])->sort()->values()->all() === collect($expected)->sort()->values()->all()
                && ! in_array("paper-{$otherPaper->id}", $request['exclude_ids'], true);
        });
    }

    #[Test]
    public function it_caps_exclusions_at_the_200_most_recent_and_never_returns_a_borrowed_paper(): void
    {
        $user = User::factory()->create();
        $fresh = AcademicPaper::factory()->create();

        // 205 catalog rows via bulk insert — the factory's unique-title
        // faker exhausts long before 205 rows.
        $now = Carbon::now();
        DB::table('academic_papers')->insert(
            collect(range(1, 205))->map(fn ($i) => [
                'catalog_code' => sprintf('CEIT-BULK-%03d', $i),
                'title' => "Bulk Catalog Paper {$i}",
                'publication_year' => 2020,
                'paper_type' => 'Thesis',
                'department' => 'Civil Engineering',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
        $paperIds = AcademicPaper::where('catalog_code', 'like', 'CEIT-BULK-%')
            ->orderBy('id')
            ->pluck('id')
            ->values();

        // 205 distinct borrows, oldest → newest, via bulk insert (the
        // transaction only needs its own columns; model events are skipped).
        $base = Carbon::now()->subDays(300);
        $rows = $paperIds->map(function ($paperId, $i) use ($user, $base) {
            $inventory = Inventory::factory()->create([
                'academic_paper_id' => $paperId,
                'copy_number' => ++$this->copyNumber,
                'status' => 'Unavailable',
            ]);
            $at = $base->copy()->addMinutes($i + 1);

            return [
                'user_id' => $user->id,
                'academic_paper_id' => $paperId,
                'inventory_id' => $inventory->id,
                'time_in' => $at->toDateTimeString(),
                'time_out' => $at->copy()->addHour()->toDateTimeString(),
                'status' => 'completed',
                'expires_at' => $at->copy()->addHours(6)->toDateTimeString(),
                'session_token' => Str::random(64),
                'created_at' => $at->toDateTimeString(),
                'updated_at' => $at->toDateTimeString(),
            ];
        })->all();
        DB::table('borrow_transactions')->insert($rows);

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response(
                $this->recommendResponseFor([$paperIds[0], $fresh->id]),
                200,
            ),
        ]);

        $service = new PersonalRecommendationsService;
        $result = $service->for($user);

        $this->assertFalse($service->coldStart);
        $this->assertSame(205, BorrowTransaction::where('user_id', $user->id)->count());

        // The sidecar got only the 200 most recent borrowed ids (papers
        // 205..6 newest-first); paper[0] — borrowed but beyond the cap —
        // was never sent as an exclusion…
        $expected = $paperIds->reverse()->take(200)
            ->map(fn ($id) => "paper-{$id}")->values()->all();

        Http::assertSent(
            fn ($request) => $request['exclude_ids'] === $expected
                && count($request['exclude_ids']) === 200
        );

        // …yet the client-side reject still drops it when the sidecar
        // (contract violation) returns it anyway.
        $this->assertSame([$fresh->id], $result->pluck('id')->all());
        $this->assertFalse($service->unavailable);
        $this->assertFalse($service->coldStart);
    }

    #[Test]
    public function it_sends_only_paper_ids_and_a_limit_never_any_user_field(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Zxqyprivate',
            'last_name' => 'Qvwxsurname',
            'email' => 'zxqy-private@example.com',
        ]);

        $papers = AcademicPaper::factory()->count(2)->create();
        $this->borrow($user, $papers[0], 'completed', Carbon::now()->subDays(2));
        $this->borrow($user, $papers[1], 'started', Carbon::now()->subDay());

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response($this->emptyRecommend(), 200),
        ]);

        (new PersonalRecommendationsService)->for($user);

        Http::assertSent(function ($request) use ($user) {
            $body = $request->body();
            $data = json_decode($body, true);

            return array_keys($data) === ['seed_ids', 'exclude_ids', 'limit']
                && $data['limit'] === 8
                && collect($data['seed_ids'])->every(fn ($id) => preg_match('/^paper-\d+$/', $id) === 1)
                && collect($data['exclude_ids'])->every(fn ($id) => preg_match('/^paper-\d+$/', $id) === 1)
                && ! str_contains($body, $user->email)
                && ! str_contains($body, 'Zxqyprivate')
                && ! str_contains($body, 'Qvwxsurname');
        });
    }

    #[Test]
    public function it_maps_results_back_in_sidecar_rank_order(): void
    {
        $user = User::factory()->create();
        $seedPaper = AcademicPaper::factory()->create();
        $paperA = AcademicPaper::factory()->create();
        $paperB = AcademicPaper::factory()->create();
        $paperC = AcademicPaper::factory()->create();

        $this->borrow($user, $seedPaper, 'completed', Carbon::now()->subDay());

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response(
                $this->recommendResponseFor([$paperC->id, $paperA->id, $paperB->id]),
                200,
            ),
        ]);

        $service = new PersonalRecommendationsService;
        $result = $service->for($user);

        $this->assertSame(
            [$paperC->id, $paperA->id, $paperB->id],
            $result->pluck('id')->all()
        );
        $this->assertTrue($result->first()->relationLoaded('authors'));
        $this->assertFalse($service->unavailable);
        $this->assertFalse($service->coldStart);
    }

    #[Test]
    public function it_ignores_malformed_recommendation_ids(): void
    {
        $user = User::factory()->create();
        $seedPaper = AcademicPaper::factory()->create();
        $paperA = AcademicPaper::factory()->create();

        $this->borrow($user, $seedPaper, 'completed', Carbon::now()->subDay());

        $fixture = $this->fixtureRecommend();
        $entry = $fixture['results'][0];
        $fixture['total'] = 4;
        $fixture['results'] = [
            array_merge($entry, ['id' => "paper-{$paperA->id}-old"]),
            array_merge($entry, ['id' => (string) $paperA->id]),
            array_merge($entry, ['id' => 'paper-abc']),
            array_merge($entry, ['id' => "paper-{$paperA->id}"]),
        ];

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response($fixture, 200),
        ]);

        $service = new PersonalRecommendationsService;
        $result = $service->for($user);

        $this->assertSame([$paperA->id], $result->pluck('id')->all());
        $this->assertFalse($service->unavailable);
    }

    #[Test]
    public function it_drops_borrowed_papers_the_sidecar_returns_anyway(): void
    {
        // Defence in depth: even though borrowed ids are sent as excludes,
        // a contract-violating response must never surface them.
        $user = User::factory()->create();
        $seedPaper = AcademicPaper::factory()->create();
        $otherBorrowed = AcademicPaper::factory()->create();
        $fresh = AcademicPaper::factory()->create();

        $this->borrow($user, $seedPaper, 'completed', Carbon::now()->subDays(2));
        $this->borrow($user, $otherBorrowed, 'started', Carbon::now()->subDay());

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response(
                $this->recommendResponseFor([$otherBorrowed->id, $fresh->id]),
                200,
            ),
        ]);

        $result = (new PersonalRecommendationsService)->for($user);

        $this->assertSame([$fresh->id], $result->pluck('id')->all());
    }

    #[Test]
    public function it_fails_closed_when_the_sidecar_is_down_with_no_fallback_request(): void
    {
        $user = User::factory()->create();
        $paper = AcademicPaper::factory()->create();
        $this->borrow($user, $paper, 'completed', Carbon::now()->subDay());

        // Only /recommend is faked — a stray fallback call to any other
        // endpoint would blow up under preventStrayRequests.
        $attempts = 0;
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => function () use (&$attempts) {
                $attempts++;

                throw new ConnectionException('Connection refused');
            },
        ]);

        $service = new PersonalRecommendationsService;
        $result = $service->for($user);

        $this->assertTrue($result->isEmpty());
        $this->assertTrue($service->unavailable);

        // The client retried within its configured posture, still to
        // /recommend only — nothing fell back to another endpoint.
        $this->assertSame(2, $attempts);
    }

    #[Test]
    public function it_fails_closed_on_auth_failure(): void
    {
        $user = User::factory()->create();
        $paper = AcademicPaper::factory()->create();
        $this->borrow($user, $paper, 'completed', Carbon::now()->subDay());

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response(['error' => ['code' => 'auth_failed', 'message' => 'missing or invalid X-Sidecar-Token']], 401),
        ]);

        $service = new PersonalRecommendationsService;
        $result = $service->for($user);

        $this->assertTrue($result->isEmpty());
        $this->assertTrue($service->unavailable);
    }

    #[Test]
    public function it_returns_empty_when_the_sidecar_returns_no_results(): void
    {
        $user = User::factory()->create();
        $paper = AcademicPaper::factory()->create();
        $this->borrow($user, $paper, 'completed', Carbon::now()->subDay());

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response(
                ['seeds_used' => 0, 'total' => 0, 'took_ms' => 2, 'results' => []],
                200,
            ),
        ]);

        $service = new PersonalRecommendationsService;
        $result = $service->for($user);

        $this->assertTrue($result->isEmpty());
        $this->assertFalse($service->unavailable);
        $this->assertFalse($service->coldStart);
    }

    #[Test]
    public function it_returns_empty_for_a_malformed_but_200_response(): void
    {
        $user = User::factory()->create();
        $paper = AcademicPaper::factory()->create();
        $this->borrow($user, $paper, 'completed', Carbon::now()->subDay());

        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response('', 200),
        ]);

        $service = new PersonalRecommendationsService;
        $result = $service->for($user);

        $this->assertTrue($result->isEmpty());
        $this->assertFalse($service->unavailable);
    }

    #[Test]
    public function it_uses_the_popular_list_and_makes_no_sidecar_call_when_the_user_has_no_borrows(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $other = User::factory()->create();
        $popular = AcademicPaper::factory()->create();
        $also = AcademicPaper::factory()->create();

        $this->borrow($other, $popular, 'completed', Carbon::now()->subDays(2));
        $this->borrow($other, $popular, 'started', Carbon::now()->subDay());
        $this->borrow($other, $also, 'completed', Carbon::now()->subDay());

        $service = new PersonalRecommendationsService;
        $result = $service->for($user);

        $this->assertTrue($service->coldStart);
        $this->assertFalse($service->unavailable);
        $this->assertSame([$popular->id, $also->id], $result->pluck('id')->all());
        Http::assertNothingSent();
    }

    #[Test]
    public function it_ignores_other_users_borrows(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://127.0.0.1:8310/recommend' => Http::response($this->emptyRecommend(), 200),
        ]);

        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $paperA = AcademicPaper::factory()->create();
        $paperB = AcademicPaper::factory()->create();

        $this->borrow($userB, $paperB, 'completed', Carbon::now());
        $this->borrow($userA, $paperA, 'completed', Carbon::now()->subDay());

        (new PersonalRecommendationsService)->for($userA);

        Http::assertSent(
            fn ($request) => $request['seed_ids'] === ["paper-{$paperA->id}"]
                && $request['exclude_ids'] === ["paper-{$paperA->id}"]
        );
    }
}
