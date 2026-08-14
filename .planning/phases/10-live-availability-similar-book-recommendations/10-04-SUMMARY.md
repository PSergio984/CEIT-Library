---
phase: 10-live-availability-similar-book-recommendations
plan: 04
subsystem: ui
tags: [livewire, blade, mary-ui, similar-books, recommendations, seo]

requires:
  - phase: 10-live-availability-similar-book-recommendations
    provides: "SimilarPapersService (10-03) — deterministic title-query recommendations with fail-closed unavailable flag"
  - phase: 10-live-availability-similar-book-recommendations
    provides: "AvailabilityService + shared availability computed (10-02) — X-of-Y hydration on result cards"
provides:
  - "ADR 0012 Similar-button UX on the student search page: secondary Similar button on all four result surfaces"
  - "Recommendations mode with header bar ('Showing similar books to: {title}' + Back to results), hydrated rec cards, loading/unavailable/empty states with Back always visible"
  - "Snapshot-on-enter / verbatim-restore composition: 10 yield points (8 updated* hooks + clearFilters + updatingPerPage) exit the mode; recursion; no URL change"
affects: [chat-widget interception of 'similar to X' (deferred), future phase 11 agentic search]

tech-stack:
  added: []
  patterns:
    - "Recommendations mode as public Livewire state: recommendedFor/recommendations/recommendationsUnavailable/recommendedTitle + public snapshot array (public required — Livewire only serializes public props across requests)"
    - "exitRecommendationsMode() funnel injected as the first statement of every query yield point; snapshot abandoned on any change"
    - "rec-mobile-{id}/rec-desktop-{id} wire:key prefixes to avoid DOM reuse on list swap"

key-files:
  created:
    - tests/Feature/AcademicPaperIndexSimilarTest.php
  modified:
    - app/Livewire/Pages/Student/AcademicPaperIndex.php
    - resources/views/livewire/pages/student/academic-paper-index.blade.php

key-decisions:
  - "recommendationsSnapshot must be public: private props do not survive Livewire request boundaries, so a private snapshot was empty when backToResults() ran in a later request (found by the restore test)"
  - "Restore proof uses recorded-request-count equality instead of Http::assertNothingSent() (Laravel's assertion is cumulative across the whole test, so it cannot be used after prior sidecar calls)"

patterns-established:
  - "Pattern: recommendations-mode state machine — plain public props, snapshot on entry, direct-assignment restore inside backToResults() (no hooks fire server-side), exit funnel at every yield point"
  - "Pattern: blade mode branch takes precedence over the hybrid/SQL split via @if (! is_null($this->recommendedFor))"

requirements-completed: [SEARCH-06]

duration: 42min
completed: 2026-08-14
---

# Phase 10 Plan 04: Similar Button Recommendations Summary

**ADR 0012 Similar-button UX shipped on the student search page — secondary Similar button on all four result surfaces, replace-with-back recommendations mode with hydrated cards and all three states, and exact snapshot restore via a public snapshot prop with a 10-point exit funnel.**

## Performance

- **Duration:** 42 min
- **Started:** 2026-08-14T14:49:00Z
- **Completed:** 2026-08-14T15:31:42Z
- **Tasks:** 6/6
- **Files modified:** 3 (1 created, 2 modified)

## Accomplishments

- Secondary Similar button (`btn-sm btn-outline gap-2`, o-sparkles icon, auto-width) beside View Details/View on all four surfaces: mobile hybrid card, mobile SQL card, desktop hybrid card, desktop table actions scope (D-14)
- Recommendations mode: header bar ("Showing similar books to: {title}" line-clamp-1 + Back to results pinned), rec cards through the existing card grid hydrated by the shared availability computed ("X of Y available" + "Checked just now"), distinct `rec-` wire:keys (D-15/D-17)
- All three states with Back visible in each: "Finding similar books..." loading overlay, alert-warning "Recommendations unavailable right now" (fail-closed), x-empty-state "No similar books found" (D-16)
- Composition contract (D-18): snapshot of search/6 filters/hybridResults/aiSearchFailed/page/sortBy on entry; verbatim restore with zero queries re-run; exitRecommendationsMode() as first statement of all 10 yield points; status filter still force-exits hybrid; Similar on a rec card recurses
- Green `AcademicPaperIndexSimilarTest` (7 tests, 33 assertions); upstream `AcademicPaperIndexHybridTest` (9 tests) stays green; full suite 580 passed / 3 skipped

## Task Commits

Each task was committed atomically:

1. **Task 1: Recommendations-mode state + showSimilar() + backToResults()** - `4dc5214d` (feat)
2. **Task 2: exitRecommendationsMode() funneled through every yield point** - `dd1696c9` (feat)
3. **Task 3: Availability computed extended with recommendation ids** - `6b574a1f` (feat)
4. **Task 4: Blade — four Similar buttons** - `b2b87c40` (feat)
5. **Task 5: Blade — recommendations mode render: header bar, cards, three states** - `ccc0355a` (feat)
6. **Task 6: AcademicPaperIndexSimilarTest — mode entry, restore, yield, recursion, states** - `fd1e273d` (test, incl. snapshot-visibility fix)

**Plan metadata:** pending (docs commit after this summary)

## Files Created/Modified

- `tests/Feature/AcademicPaperIndexSimilarTest.php` - 7 component tests: mode entry with header + hydrated "2 of 3 available" cards, verbatim restore (no query re-ran), yield on search edit and status filter (hybrid still force-exited), recursion, unavailable banner, empty state
- `app/Livewire/Pages/Student/AcademicPaperIndex.php` - recommendations-mode props + public snapshot, showSimilar()/backToResults(), exitRecommendationsMode() at 10 yield points, availability() merges recommendation ids
- `resources/views/livewire/pages/student/academic-paper-index.blade.php` - 4 Similar buttons, mode branch with header bar + loading/banner/empty states + rec card grids (rec-mobile-/rec-desktop- keys)

## Decisions Made

- Made `recommendationsSnapshot` a public prop — Livewire only serializes public properties between requests; a private snapshot was empty when `backToResults()` ran in a subsequent request (surfaced by the restore test, fixed before commit)
- Restore test proves "no query re-ran" via recorded-request-count equality before/after Back, since `Http::assertNothingSent()` is cumulative and cannot be used after earlier sidecar calls in the same test

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Snapshot prop must be public to survive Livewire requests**
- **Found during:** Task 6 (restore test — `Undefined array key "search"` in `backToResults()`)
- **Issue:** The plan specified `private array $recommendationsSnapshot`. Livewire serializes only public component state; private props reset on every request, so `backToResults()` (a separate request) read an empty snapshot and crashed
- **Fix:** Changed to `public array $recommendationsSnapshot = []` — same visibility as the existing `hybridResults` model-array prop; no new exposure (blade never reads it)
- **Files modified:** app/Livewire/Pages/Student/AcademicPaperIndex.php
- **Verification:** restore test green; full suite green
- **Committed in:** fd1e273d (Task 6 commit)

**2. [Rule 1 - Bug] Restore proof: `Http::assertNothingSent()` cannot be used after prior sidecar calls**
- **Found during:** Task 6 (restore test design)
- **Issue:** Laravel's `assertNothingSent()` asserts the entire recorded history is empty; the restore test legitimately records 2 earlier calls (hybrid + similar), so the plan's literal assertion could never pass
- **Fix:** Captured `count(Http::recorded())` before Back and asserted the count is unchanged after — the same "no query re-ran" guarantee
- **Files modified:** tests/Feature/AcademicPaperIndexSimilarTest.php
- **Verification:** restore test green
- **Committed in:** fd1e273d (Task 6 commit)

**3. [Rule 1 - Bug] Recursion assertion impossible with the plan's own fixture**
- **Found during:** Task 6 (recursion test)
- **Issue:** Plan asserted `recommendations.0.id != 77` after `showSimilar(78)` while seeding only 77+78; self-exclusion of the new seed 78 makes the list `[77]`, so `0.id === 77` is the correct value
- **Fix:** Assert the actual replacement: entry list `0.id === 78` (seed 77 excluded), after recursion `recommendedFor === 78` and `recommendations.0.id === 77` (list re-derived for the new seed)
- **Files modified:** tests/Feature/AcademicPaperIndexSimilarTest.php
- **Verification:** recursion test green
- **Committed in:** fd1e273d (Task 6 commit)

**4. [Rule 1 - Bug] Restore test filter choice**
- **Found during:** Task 6 (restore test)
- **Issue:** Plan said "enter with search text + a filter + hybrid results" and asserted `statusFilter` — but a status filter force-exits hybrid mode (D-18), so hybrid results and a status filter are mutually exclusive
- **Fix:** Used `departmentFilter` (hybrid-compatible) for the restore test; restored verbatim and asserted both `search` and the filter
- **Files modified:** tests/Feature/AcademicPaperIndexSimilarTest.php
- **Verification:** restore test green
- **Committed in:** fd1e273d (Task 6 commit)

**5. [Rule 1 - Bug] Pagination page assertion uses Livewire 4 state shape**
- **Found during:** Task 6 (restore test)
- **Issue:** Livewire 4 stores pagination in `paginators[pageName]` (no `$page` property), so `assertSet('page', 1)` cannot work; also the snapshot uses `getPage('academic-papers-index')` which reads the same array
- **Fix:** Asserted `paginators.academic-papers-index === 1` after Back (component code itself was already correct via the WithPagination helpers)
- **Files modified:** tests/Feature/AcademicPaperIndexSimilarTest.php
- **Verification:** restore test green
- **Committed in:** fd1e273d (Task 6 commit)

---

**Total deviations:** 5 auto-fixed (1 missing critical, 4 bugs)
**Impact on plan:** All fixes necessary for the plan's own tests to pass; component behavior matches D-14..D-18 exactly. No scope creep.

## Issues Encountered

- None beyond the auto-fixed deviations above. The Imagick PHP warning (version mismatch) is pre-existing environment noise, not test-related.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- SEARCH-06 UI complete: Similar on every result surface → recommendations mode with header + Back, hydrated cards, all three states with Back visible, exact restore, immediate exit on any query change, recursion — no URL change
- 10-03's deterministic mechanism fully surfaced; 10-02's hydration feeds recommendation cards through the shared card path
- Ready for the next phase-10 plan (if any) or phase close-out

---
*Phase: 10-live-availability-similar-book-recommendations*
*Completed: 2026-08-14*
