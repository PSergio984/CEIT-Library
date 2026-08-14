---
phase: 10-live-availability-similar-book-recommendations
plan: 03
subsystem: api
tags: [laravel, ai-sidecar, rrf, similar-books, fail-closed]

requires:
  - phase: 09-ai-chat-foundation
    provides: AiService with typed exceptions (AiServiceUnavailableException/AiServiceAuthException), RRF_CANDIDATES = 60
  - phase: 10-availability-hydration
    provides: AcademicPaper card shape (withCount available_copies + status transform) that recommendations reuse

provides:
  - "SimilarPapersService::for(AcademicPaper $paper, int $limit = 10): Collection<AcademicPaper> — title-as-query mechanism: verbatim AiService::search($title, [], 'catalog', $limit) with k=60, client-side self-exclusion of the seed id, rank-preserving findMany + keyBy re-map, status transform"
  - "Fail-closed contract: sidecar down/auth -> empty Collection + unavailable=true; empty/self-exclusion-emptied -> empty Collection + unavailable=false; no SQL/LLM fallback"
  - "SimilarPapersServiceTest suite (7 tests): verbatim title-query capture (exact 5-key ADR 0004 payload), self-exclusion + rank order, cross-call determinism, seed-only-empty and empty-retrieval, sidecar-down and auth fail-closed"

affects: [10-04-similar-button-ux, chat interception of 'similar to X']

tech-stack:
  added: []
  patterns:
    - "Fail-closed flag as public bool property on the service (mirrors aiSearchFailed idiom)"
    - "Sidecar rank order preserved via findMany + keyBy + re-map over ordered id list"
    - "Fixture id alignment via forceFill(['id' => N]) against tests/fixtures/ai-sidecar/search.json"

key-files:
  created:
    - app/Services/SimilarPapersService.php
    - tests/Feature/SimilarPapersServiceTest.php
  modified: []

key-decisions:
  - "Query built from title ONLY, filters = [] — no authors/advisers/department terms, no metadata filters (D-09/D-10)"
  - "Seed paper id rejected client-side BEFORE findMany; RRF pool of 60 absorbs the wasted rank slot — zero sidecar change (D-11)"
  - "unavailable flag set true ONLY in the typed-exception catch; empty/self-exclusion-emptied return collect() with flag false (D-13)"
  - "No SQL fallback, no LLM fallback anywhere in the class (D-13, SEARCH-06 deterministic list)"

patterns-established:
  - "Title-as-query recommendation mechanism: AiService::search() verbatim -> map paper-N ids -> reject seed -> findMany -> keyBy re-map (rank preserved) -> status transform"
  - "Fail-closed: typed exceptions never escape for(); caller distinguishes unavailable (banner) vs empty (empty state) via the flag"

requirements-completed: [SEARCH-06]

duration: 40min
completed: 2026-08-14
---

# Phase 10 Plan 03: Similar Papers Service Summary

**SimilarPapersService::for() — title-only deterministic sidecar query (verbatim 5-key ADR 0004 payload, k=60) with client-side seed self-exclusion, sidecar-rank-preserving model load, and a fail-closed `unavailable` flag covering all four empty/down paths — no SQL or LLM fallback.**

## Performance

- **Duration:** 40 min (incl. fresh `composer install` — empty `vendor/`)
- **Started:** 2026-08-14T22:00:00+08:00 (approx)
- **Completed:** 2026-08-14T22:30:00+08:00
- **Tasks:** 4
- **Files modified:** 2 (1 created service, 1 created test suite)

## Accomplishments

- `SimilarPapersService::for(AcademicPaper $paper, int $limit = 10)` calls `(new AiService)->search($paper->title, [], 'catalog', $limit)` verbatim — title ONLY, `filters = []`, k stays 60 via `AiService::RRF_CANDIDATES` (D-08/D-09/D-10).
- Client-side self-exclusion: the seed paper's mapped int id is rejected BEFORE `findMany`, and results are re-keyed over the ordered id list so sidecar rank order survives DB loading (D-11/D-12, `AcademicPaperIndex.php:318-330` pattern).
- Fail-closed contract (D-13): `AiServiceUnavailableException|AiServiceAuthException` → empty Collection + `unavailable === true`; empty retrieval / self-exclusion-emptied → empty Collection + `unavailable === false`. No `where()`-based SQL fallback, no `chatStream`/LLM call anywhere in the class.
- 7-test `SimilarPapersServiceTest` suite: exact payload capture (`array_keys === ['query','filters','corpus','limit','k']`, token header, `query === $paper->title`), self-exclusion + rank order (`[78]` with 77 dropped), `assertSame` determinism across calls, both empty paths, and both fail-closed paths — all with `Http::preventStrayRequests()`.

## Task Commits

Each task was committed atomically:

1. **Task 1: SimilarPapersService::for() — title query, verbatim search, self-exclusion, rank-preserving load, fail-closed** - `f69ab88d` (feat)
2. **Task 2: SimilarPapersServiceTest — verbatim title query + exact key set** - `5ebab136` (test)
3. **Task 3: SimilarPapersServiceTest — self-exclusion, rank order, determinism** - `d290e95f` (test)
4. **Task 4: SimilarPapersServiceTest — fail-closed on sidecar down and auth** - `20a21114` (test)

## Files Created/Modified

- `app/Services/SimilarPapersService.php` - Title-as-query recommendation service: `for()` calls `AiService::search($paper->title, [], 'catalog', $limit)`, maps `paper-N` ids, rejects the seed id, loads `AcademicPaper` models with `authors`/`copies` + `available_copies` withCount, re-keys over the ordered id list (sidecar rank preserved), applies the status transform, and fails closed with a `public bool $unavailable` flag.
- `tests/Feature/SimilarPapersServiceTest.php` - SEARCH-06 validation: verbatim-query capture (exact 5-key ADR 0004 payload), self-exclusion + rank order, cross-call determinism (`assertSame`), empty-retrieval and seed-only-empty fail-closed, sidecar-down (`ConnectionException`) and 401 auth fail-closed. Fixture-aligned via `forceFill(['id' => 77/78])` against `tests/fixtures/ai-sidecar/search.json`.

## Decisions Made

- Followed plan as specified — no unplanned decisions. Notable plan-conformant choices: seed data replicates `AcademicPaperIndexHybridTest::seedPapers()` (explicit `AcademicPaper::create` with adviser ids, not the factory) for deterministic titles matching the fixture; `Http::preventStrayRequests()` in every test; `unavailable` reset at entry of `for()` so callers can reuse one service instance safely.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

- **`vendor/` directory was empty** (no `vendor/autoload.php`; `composer.lock` present) — `php artisan test` could not boot. Resolved with `composer install --no-interaction --prefer-dist` (106 packages; not committed — dependency install only). All tests ran on the fresh install.
- Full-suite delta: 573 passed / 3 skipped vs the 557 passed / 3 skipped baseline in CONTEXT.md — the +16 includes this plan's 7 tests plus plans 10-01/10-02 additions; all green, nothing regressed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `SimilarPapersService::for()` is ready for 10-04's component wiring (`showSimilar`, recommendations mode, snapshot/restore): it returns the same `AcademicPaper` Collection shape (status-transformed, `available_copies` loaded) the search page renders, so the shared card path and ADR 0010 hydrator consume it unchanged.
- The `unavailable` flag gives 10-04 the exact D-16 state split: flag true → "Recommendations unavailable right now" banner; empty + flag false → "No similar books found" empty state.

---
*Phase: 10-live-availability-similar-book-recommendations*
*Completed: 2026-08-14*
