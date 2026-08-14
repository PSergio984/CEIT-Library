---
phase: 10-live-availability-similar-book-recommendations
plan: 02
subsystem: search
tags: [laravel, livewire, inventory, availability, hydration, sqlite]

requires:
  - phase: 10-live-availability-similar-book-recommendations
    provides: 10-01 locked the sidecar contract (closed request schemas, capture discipline) that this plan's never-LLM proofs build on
provides:
  - AvailabilityService::forPapers() grouped inventory hydration (D-01/D-02, single source of truth)
  - Search-page cards render live "X of Y available" + static "Checked just now" caption in all three card spots (D-03/D-04/D-05)
  - D-07 proofs #1 (exact-key capture on /search), #4 (hydrator unit suite) and #5 (render-level proofs)
affects: 10-04 (recommendations cards reuse the $availability computed through the shared card path), 10-05 (chat-chip hydration lands on top of this service)

tech-stack:
  added: []
  patterns:
    - "Plain service class (no DI container, `new` instantiation) exposing a single grouped `whereIn` hydration query — AvailabilityService::forPapers()"
    - "Plain `#[Computed]` (never persist/cache) for the per-render availability map"
    - "Blade accesses computed properties via `$this->availability` (Livewire 4), not bare `$availability`"

key-files:
  created:
    - app/Services/AvailabilityService.php
    - tests/Unit/AvailabilityServiceTest.php
  modified:
    - app/Livewire/Pages/Student/AcademicPaperIndex.php
    - resources/views/livewire/pages/student/academic-paper-index.blade.php
    - tests/Feature/AiServiceTest.php
    - tests/Feature/AcademicPaperIndexHybridTest.php

key-decisions:
  - "AvailabilityService::forPapers() is the single source of truth for {available, total, checked_at}; `available` counts status='Available' rows only, `total` counts all inventory rows, `checked_at` = now() at fetch, never persisted"
  - "Search page hydrates via one `#[Computed] availability` merging paginator ids + hybrid ids into a single forPapers() call; the query-level withCount stays ONLY for orderBy('status') and the status filter"
  - "Cards show static 'Checked just now' caption — the checked_at value is never rendered"

patterns-established:
  - "Grouped hydration: `Inventory::whereIn(...)->selectRaw('academic_paper_id, COUNT(*) AS total, SUM(CASE WHEN status = \"Available\" THEN 1 ELSE 0 END) AS available')->groupBy('academic_paper_id')`"
  - "Sidecar never-LLM enforcement: capture closures assert the EXACT ADR 0004 key set `['query','filters','corpus','limit','k']` plus absence of available/total/checked_at"

requirements-completed: [SEARCH-02]

duration: 3h
completed: 2026-08-14
---

# Phase 10 Plan 2: Availability Hydration Summary

**AvailabilityService::forPapers() grouped inventory hydration wired into the student search page — cards show live "X of Y available" + static "Checked just now" resolved from Inventory rows in one query per render, never from the sidecar payload (capture-proven)**

## Performance

- **Duration:** ~3h
- **Started:** 2026-08-14
- **Completed:** 2026-08-14
- **Tasks:** 6
- **Files modified:** 5 (1 new service, 1 new unit test, 1 component, 1 blade, 2 test files)

## Accomplishments
- `AvailabilityService::forPapers(array $ids): array` — one grouped `whereIn` query returning `[id => {available, total, checked_at}]`; empty input returns `[]` without issuing a query; `checked_at` = `now()` at fetch, never persisted (D-01/D-02)
- `#[Computed] availability` on `AcademicPaperIndex` merges paginator ids + hybrid-result ids into one `forPapers()` call — plain computed, NOT persist/cache; the existing `withCount` blocks stay untouched for `orderBy('status')` and the status filter (D-03)
- All three card Copies cells (mobile hybrid, mobile SQL, desktop hybrid) now render "X of Y available" + the static muted caption "Checked just now"; `checked_at` value never echoed (D-04/D-05)
- D-07 proofs: #1 exact-key-set capture on `/search` (`array_keys === ['query','filters','corpus','limit','k']` + no available/total/checked_at keys, strengthened closure + new sibling test); #4 five-test hydrator unit suite (mixed statuses 2/4·0/1·3/3, freshness <5s, no-copies omission, zero-query empty ids, never-persisted proof); #5 three render-level tests (hybrid cards, SQL cards with `Http::assertNothingSent()`, 0-of-0 fallback)

## Task Commits

Each task was committed atomically:

1. **Task 1: AvailabilityService::forPapers() grouped hydration** - `319992a2` (feat)
2. **Task 2: AvailabilityServiceTest unit suite (D-07 #4)** - `78d18a2c` (test)
3. **Task 3: #[Computed] availability map in AcademicPaperIndex** - `790699f4` (feat)
4. **Task 4: Blade 'X of Y available' + 'Checked just now'** - `11b30ca5` (feat)
5. **Task 5: D-07 #1 exact-key-set capture in AiServiceTest** - `966c1f02` (test)
6. **Task 6: D-07 #5 search-page render tests** - `ba56230d` (test)

**Plan fixes:** `9d61102c` (fix: lazy-loading test-state leak — see deviations)

## Files Created/Modified
- `app/Services/AvailabilityService.php` - New read-only service; `forPapers(array $ids): array` grouped inventory hydration (D-02 verbatim query, `checked_at = now()` captured once per call, zero-query early return on empty ids)
- `tests/Unit/AvailabilityServiceTest.php` - 5-test unit suite (D-07 #4): mixed copy statuses, checked_at freshness, papers-without-rows omission, empty-ids zero-query, never-persists proof
- `app/Livewire/Pages/Student/AcademicPaperIndex.php` - `use AvailabilityService` import + `#[Computed] availability()` merging `academicPapers` paginator ids and `hybridResults` ids into one call
- `resources/views/livewire/pages/student/academic-paper-index.blade.php` - three Copies cells: `{{ ($this->availability[$paper->id]['available'] ?? 0) }} of {{ ($this->availability[$paper->id]['total'] ?? 0) }} available` + `<p class="text-xs text-base-content/50">Checked just now</p>`
- `tests/Feature/AiServiceTest.php` - strengthened `/search` capture closure (exact key set + no availability keys) + new `it_sends_exactly_the_adr_0004_search_keys`
- `tests/Feature/AcademicPaperIndexHybridTest.php` - 3 new tests: hybrid-card hydration with payload capture, SQL-card hydration with `Http::assertNothingSent()`, 0-of-0 fallback

## Decisions Made
- None beyond the plan's locked decisions — followed D-01..D-07 as specified. All execution-time choices (service layout, computed shape, blade markup) were the plan's explicit discretion.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Bug] Blade must access the computed via `$this->availability`**
- **Found during:** Task 6 (render tests)
- **Issue:** The plan's literal markup `{{ ($availability[$paper->id]['available'] ?? 0) }}` renders "0 of 0 available" on every card — Livewire 4 does not bind computed properties to bare blade variables; only `$this->availability` works. Caught by the Task 6 render tests asserting "2 of 3 available".
- **Fix:** Changed the three cell lines to `{{ ($this->availability[$paper->id]['available'] ?? 0) }}` / `...['total'] ?? 0) }}`.
- **Files modified:** resources/views/livewire/pages/student/academic-paper-index.blade.php
- **Verification:** All 3 render tests pass; `grep available_copies|checked_at` in the view returns nothing; `php artisan view:cache` exits 0.
- **Committed in:** `ba56230d` (Task 6 commit)

**2. [Rule 2 - Bug] `whereNotNull('checked_at')` cannot prove column absence on SQLite**
- **Found during:** Task 2 (never-persists test)
- **Issue:** SQLite evaluates an unknown quoted column in an `IS NOT NULL` predicate as TRUE (returns the row), so the planned `DB::table('inventories')->whereNotNull('checked_at')->first()` assertion proved nothing. The table name is also `inventories` (Eloquent plural), not `inventory`.
- **Fix:** Replaced with `Schema::hasColumn('inventories', 'checked_at')` asserting `false` — a direct proof that the column never exists — while the before/after row-attribute equality asserts no data mutation.
- **Files modified:** tests/Unit/AvailabilityServiceTest.php
- **Verification:** All 5 unit tests pass.
- **Committed in:** `78d18a2c` (Task 2 commit)

**3. [Rule 2 - Bug] Class-level `disableLivewireLazyLoading` leaks lazy state across the suite**
- **Found during:** Plan-level `<verification>` (full-suite run)
- **Issue:** Setting `protected bool $disableLivewireLazyLoading = true` on the test class registered a sticky `flush-state` listener that flipped `SupportLazyLoading::$disableWhileTesting` for the rest of the PHPUnit process, making `FiltersTest::filter controls are consistent across all pages` render the `#[Lazy]` placeholder instead of real content in full-suite runs (reproduced with an isolated two-file PHPUnit config: hybrid → Filters order failed; baseline worktree at `5f6bbcdf` without my changes passed 556/3).
- **Fix:** Removed the class flag; the SQL render test now mounts the real component via `->set('perPage', 5)` (harmless — the other two Task 6 tests already mount via `->call('runHybridSearch')`).
- **Files modified:** tests/Feature/AcademicPaperIndexHybridTest.php
- **Verification:** Full suite 566 passed / 3 skipped / 0 failed (baseline 557+9 new tests); isolated hybrid→Filters pair run OK (13 tests, 57 assertions).
- **Committed in:** `9d61102c` (fix commit)

---

**Total deviations:** 3 auto-fixed (all Rule 2 — correctness bugs discovered by the plan's own verification gates)
**Impact on plan:** All three fixes were necessary for the tests to prove what the plan intended; no scope creep, no contract changes.

## Issues Encountered
- PowerShell `Set-Content -Encoding UTF8` corrupted multi-byte characters (em-dashes, ✕) in the blade file during a bulk replace; restored the file from the Task 4 commit and re-applied the single `$this->` fix with the Edit tool — final committed state is clean UTF-8 (verified via `git diff` showing exactly 3 intended line changes).
- The plan's acceptance criteria text for the SQL render test assumed a plain `Livewire::test()` renders real component content; the `#[Lazy]` component renders its placeholder in tests without an interaction — solved with a harmless `perPage` set (deviation #3).

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- SEARCH-02 search-page half complete: cards show live "X of Y available" + "Checked just now" resolved from Inventory in one grouped query — never in the sidecar payload (capture-proven), never from the model accessors.
- D-07 proofs #1 (search side), #4 and #5 green; #3 proven by 10-01; chat-side #1/#2 land in 10-05 on top of this service.
- Ready for 10-03 / 10-04 (recommendations reuse the same `$availability` computed through the shared card path).

---
*Phase: 10-live-availability-similar-book-recommendations*
*Completed: 2026-08-14*
