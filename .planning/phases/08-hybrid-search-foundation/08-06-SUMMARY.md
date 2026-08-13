---
phase: 08-hybrid-search-foundation
plan: 06
subsystem: api
tags: [observers, jobs, scheduler, reconcile, queue, pivot]

requires:
  - phase: 08
    provides: "08-01 CorpusExporter export contract, 08-04 AiService rebuildIndex, existing commands/scheduler patterns"
provides:
  - "Three-layer index freshness: event-driven debounced rebuild (60s, unique 300s) + IMMEDIATE rebuild on deletion"
  - "Pivot model events for author attach/detach rebuilds"
  - "ai:sync-index + ai:reconcile-index (--repair) commands; hourly export+sync, nightly reconcile schedules"
  - "10 tests (6 observer matrix, 4 reconcile); full suite 518 passed"
affects: [09, 10, 11, 13, 14]

tech-stack:
  added: []
  patterns: ["ShouldBeUnique jobs with distinct uniqueIds (debounce vs immediate)", "Custom Pivot with booted() event listeners", "Queueable delay + uniqueFor(300) burst collapse"]

key-files:
  created:
    - app/Models/AcademicPaperAuthor.php
    - app/Observers/AcademicPaperObserver.php
    - app/Observers/PeopleNameObserver.php
    - app/Observers/RulebookObserver.php
    - app/Jobs/AiIndexRebuildJob.php
    - app/Jobs/AiIndexRebuildImmediateJob.php
    - app/Console/Commands/SyncAiIndex.php
    - app/Console/Commands/ReconcileAiIndex.php
    - tests/Feature/AiIndexObserverTest.php
    - tests/Feature/ReconcileAiIndexTest.php
    - tests/Feature/SidecarLiveTest.php
  modified:
    - app/Models/AcademicPaper.php
    - app/Providers/AppServiceProvider.php
    - routes/console.php
    - phpunit.xml

key-decisions:
  - "Pivot rebuild wiring uses booted() model events on AcademicPaperAuthor, NOT the plan's $touches mechanism — Model::touch() uses saveQuietly() so the parent's updated observer NEVER fires on attach/detach (verified empirically + vendor source); pivot created/deleted events DO fire (verified)"
  - "phpunit.xml QUEUE_CONNECTION sync → database: with observers active, sync would run jobs inline (real HTTP to sidecar) in every test; database queue matches production default + plan T-03 (failed_jobs visibility)"
  - "ShouldBeUnique acquires a cache lock at dispatch (PendingDispatch::shouldDispatch) — second dispatch of same uniqueId within 300s is skipped; tests release the lock between dispatches to observe subsequent ones (key format: laravel_unique_job:{Class}:{uniqueId})"

patterns-established:
  - "Observers dispatch debounced AiIndexRebuildJob (delay 60s) on created/updated; AiIndexRebuildImmediateJob on deleted"
  - "Reconcile logs counts + source_generated_at only (T-04), 26h freshness threshold, --repair dispatches rebuild"

requirements-completed: [SEARCH-07]

duration: 2h 5min
completed: 2026-08-14
---

# Phase 8 Plan 6: Index Sync Mechanics Summary

**Three-layer index freshness live: observer-driven debounced rebuilds (60s) + immediate-on-delete, hourly export→sync, nightly reconcile with --repair — SEARCH-07 complete; the pivot wiring deviates from the plan because Model::touch() silently skips events**

## Performance

- **Duration:** 2h 5min
- **Started:** 2026-08-14T01:10:00+08:00
- **Completed:** 2026-08-14T03:15:00+08:00
- **Tasks:** 4
- **Files modified:** 15 (11 created, 4 modified)

## Accomplishments

- 3 observers (AcademicPaper, PeopleName ×4 models, Rulebook ×2 models) — 7 registrations in AppServiceProvider.
- 2 jobs: `AiIndexRebuildJob` (ShouldBeUnique 'ai-index-rebuild', uniqueFor 300, delay 60s) + `AiIndexRebuildImmediateJob` ('ai-index-rebuild-immediate') — export → POST /index/rebuild, tries 3, backoff 60, sanitized error log + rethrow.
- `AcademicPaperAuthor` custom Pivot with `booted()` created/deleted listeners → author attach/detach triggers rebuilds.
- `ai:sync-index` (exit 1 on sidecar down), `ai:reconcile-index {--repair}` (counts + 26h freshness, Log::warning counts-only, dispatch on --repair).
- Scheduler: `ai:export-corpus` hourlyAt(5), `ai:sync-index` hourlyAt(10), `ai:reconcile-index` dailyAt('02:00').
- Tests: 6 observer matrix + 4 reconcile; `SidecarLiveTest` env-gated (`SIDECAR_LIVE_TEST=1`).
- Full suite: **518 passed, 3 skipped**.

## Task Commits

1. **Task 1: pivot + observers + registration** - `7c8e595d` (feat, combined)
2. **Task 2: jobs** - `7c8e595d`
3. **Task 3: commands + scheduler** - `7c8e595d`
4. **Task 4: tests** - `7c8e595d`

**Plan metadata:** committed in CEIT-Library repo (this summary)

## Files Created/Modified

- `app/Models/AcademicPaperAuthor.php` - custom Pivot, booted() created/deleted → rebuild jobs
- `app/Models/AcademicPaper.php` - authors() gains `->using(AcademicPaperAuthor::class)` (boot() untouched)
- `app/Observers/*` - 3 observers, 7 registrations
- `app/Jobs/AiIndexRebuild{Job,ImmediateJob}.php` - debounced + immediate rebuilds
- `app/Console/Commands/{SyncAiIndex,ReconcileAiIndex}.php`
- `routes/console.php` - 3 schedule entries
- `phpunit.xml` - QUEUE_CONNECTION sync → database
- 3 test files

## Decisions Made

- Pivot wiring via `booted()` events (see Deviations #1).
- Test queue switched to database (see Deviations #2).
- Unique-lock release helper in tests (see Deviations #3).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Plan's $touches pivot mechanism never fires**
- **Found during:** Task 4 (updated/attach tests failed: create dispatched 1, update/attach added 0)
- **Issue:** `Model::touch()` calls `saveQuietly()` in Laravel 12 — the parent's `updated` observer event does NOT fire on attach/detach despite the pivot `$touches = ['academicPaper']`. Verified empirically (inline listeners fire; observer doesn't) and in vendor source.
- **Fix:** Rewired to pivot `booted()` model events — `AcademicPaperAuthor::created`/`deleted` listeners (verified to fire on attach/detach via `attachUsingCustomClass` → `newPivot(...)->save()`). Kept the relation for completeness.
- **Files modified:** app/Models/AcademicPaperAuthor.php
- **Verification:** attach test green (pivot created → debounced job).
- **Committed in:** 7c8e595d

**2. [Rule 1 - Bug] Test QUEUE_CONNECTION=sync would run jobs inline (real sidecar HTTP)**
- **Found during:** Task 4 — first debug test crashed hitting the live sidecar from an observer dispatch (120s timeouts, connection refused)
- **Issue:** With observers registered, every `AcademicPaper::factory()->create()` in the ENTIRE suite dispatches rebuild jobs; sync driver executes them inline → every test would attempt real HTTP.
- **Fix:** phpunit.xml `QUEUE_CONNECTION` sync → database (jobs stored, never run inline; matches production default + plan T-03 failed_jobs visibility; verified no existing test relies on sync dispatch).
- **Files modified:** phpunit.xml
- **Committed in:** 7c8e595d

**3. [Rule 1 - Bug] ShouldBeUnique silently skips subsequent dispatches in tests**
- **Found during:** Task 4 (update/attach still showed 1 dispatch after releasing via hashed key)
- **Issue:** `PendingDispatch::shouldDispatch()` acquires a cache lock for ShouldBeUnique jobs; within uniqueFor(300) a second dispatch of the same uniqueId is SKIPPED (this IS the intended burst collapse). Tests asserting a 2nd dispatch must release the lock; the key is `laravel_unique_job:{Class}:{uniqueId}` (class name, not xxh128 as one might assume — verified from the array store's locks).
- **Fix:** `releaseUniqueLock()` helper — `Cache::lock('laravel_unique_job:'.$jobClass.':'.$uniqueId)->forceRelease()` between dispatches.
- **Files modified:** tests/Feature/AiIndexObserverTest.php
- **Verification:** 6/6 observer tests green.
- **Committed in:** 7c8e595d

---

**Total deviations:** 3 auto-fixed (3 bugs, Rule 1)
**Impact on plan:** All three necessary for correctness; the pivot fix changes HOW attach/detach triggers rebuilds but preserves the plan's intent (index freshness on author changes).

## Issues Encountered

- Extended debugging chain: observer updated not firing → event listener inspection → PendingDispatch::shouldDispatch → UniqueLock → array-store lock key format. Root causes documented above.
- SidecarLiveTest skipped in CI (env-gated per plan).

## User Setup Required

None — queue is database (default); `php artisan queue:work` needed in production for observers/jobs to run (was already required by the existing notification jobs).

## Next Phase Readiness

- SEARCH-07 complete: catalog/policy data syncs into the index automatically (success criterion 2 covered: within-minutes debounce + immediate delete + nightly reconciliation).
- Phase 8 complete pending verification: all 6 plans done — walking skeleton, search UI, eval, sync.
- Manual UAT: edit a paper → searchable within ~60-120s; delete → gone fast; `ai:reconcile-index` → "AI index in sync".

---
*Phase: 08-hybrid-search-foundation*
*Completed: 2026-08-14*
