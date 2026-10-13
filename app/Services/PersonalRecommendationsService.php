<?php

namespace App\Services;

use App\Exceptions\AiServiceAuthException;
use App\Exceptions\AiServiceUnavailableException;
use App\Models\AcademicPaper;
use App\Models\BorrowTransaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PersonalRecommendationsService
{
    private const SEED_COUNT = 10;

    private const EXCLUDE_CAP = 200;

    private const POPULAR_WINDOW_DAYS = 90;

    private const POPULAR_POOL = 50;

    private const POPULAR_CACHE_KEY = 'recommendations.popular.v1';

    /**
     * Fail-closed flag: true only when the sidecar call itself failed
     * (unavailable or auth), so callers can render an error state instead
     * of a misleading empty list.
     */
    public bool $unavailable = false;

    /**
     * True when the returned list came from the popular list because the
     * user has no borrow history — a data state, not a failure.
     */
    public bool $coldStart = false;

    /**
     * Recommendations seeded from the user's borrow history (ADR 0028).
     * Seeds are the last 10 distinct borrowed papers newest first (any
     * status); exclusions are every borrowed paper, capped at the
     * sidecar's 200 most recent. Only `paper-N` ids cross to the sidecar
     * — no user identifier is ever sent. A user with no borrows gets the
     * popular list and no sidecar call is made. A malformed-but-200 body
     * is an empty result, not a failure, and only strict `paper-<digits>`
     * ids map back. Borrowed ids are also rejected client-side as
     * defence in depth (covers the >200 cap case).
     *
     * @return Collection<int, AcademicPaper>
     */
    public function for(User $user, int $limit = 8): Collection
    {
        $this->unavailable = false;
        $this->coldStart = false;

        $borrowedIds = BorrowTransaction::where('user_id', $user->id)
            ->orderByRaw('COALESCE(time_in, created_at) DESC')
            ->orderByDesc('id')
            ->pluck('academic_paper_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($borrowedIds->isEmpty()) {
            $this->coldStart = true;

            return $this->popular($limit);
        }

        $seeds = $borrowedIds->take(self::SEED_COUNT)
            ->map(fn ($id) => "paper-{$id}")
            ->all();
        $excludes = $borrowedIds->take(self::EXCLUDE_CAP)
            ->map(fn ($id) => "paper-{$id}")
            ->all();

        try {
            $response = (new AiService)->recommend($seeds, $excludes, $limit);
            $results = $response['results'] ?? [];
        } catch (AiServiceUnavailableException|AiServiceAuthException) {
            $this->unavailable = true;

            return collect();
        }

        $borrowed = $borrowedIds->flip();

        $ids = collect($results)
            ->map(fn ($result) => preg_match('/^paper-(\d+)$/', (string) ($result['id'] ?? ''), $match)
                ? (int) $match[1]
                : 0)
            ->filter()
            ->reject(fn ($id) => $borrowed->has($id))
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $papers = AcademicPaper::with(['authors:id,name'])->findMany($ids->all());
        $byId = $papers->keyBy('id');

        return $ids
            ->map(fn ($id) => $byId->get($id))
            ->filter()
            ->values();
    }

    /**
     * The cold-start list: most-borrowed catalog papers over the last 90
     * days, widening to all time when the window is empty, ordered by
     * borrow count then paper id so the shelf is deterministic. The
     * winning list of the top POPULAR_POOL paper ids is cached for an
     * hour under a single key covering the window-then-widen result —
     * independent of $limit, so a small-limit caller never truncates the
     * pool for everyone else; the cached ids are sliced to $limit after
     * the read. Hydration drops ids whose paper no longer exists.
     *
     * @return Collection<int, AcademicPaper>
     */
    public function popular(int $limit = 8): Collection
    {
        $ids = Cache::remember(self::POPULAR_CACHE_KEY, now()->addHour(), function () {
            $ranked = fn ($query) => $query
                ->groupBy('academic_paper_id')
                ->orderByRaw('COUNT(*) DESC')
                ->orderBy('academic_paper_id')
                ->limit(self::POPULAR_POOL)
                ->pluck('academic_paper_id');

            $ids = $ranked(BorrowTransaction::where('created_at', '>=', now()->subDays(self::POPULAR_WINDOW_DAYS)));

            if ($ids->isEmpty()) {
                $ids = $ranked(BorrowTransaction::query());
            }

            return $ids->map(fn ($id) => (int) $id)->all();
        });

        $ids = array_slice($ids, 0, $limit);

        $papers = AcademicPaper::with(['authors:id,name'])->findMany($ids);
        $byId = $papers->keyBy('id');

        return collect($ids)
            ->map(fn ($id) => $byId->get($id))
            ->filter()
            ->take($limit)
            ->values();
    }
}
