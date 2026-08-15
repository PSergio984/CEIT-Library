---
phase: 11-academic-papers-agentic-search
plan: 04
subsystem: ui
tags: [livewire, blade, daisyui, alpine, hybrid-search, sidecar, mary-ui]

# Dependency graph
requires:
  - phase: 11-02 (sidecar filters)
    provides: author/adviser filter clauses in rrf_search.passes() + rebuilt rich corpus index
  - phase: 11-01 (corpus shape)
    provides: rich single-title catalog docs the hybrid search hydrates against
provides:
  - Browse / Paper Search tab strip on the student AcademicPaperIndex page (D-04)
  - authorFilter/adviserFilter props, availableAuthors/availableAdvisers computeds, conditional filter passthrough (D-05)
  - Browse-mode byte-identical payloads (five Phase 9/10 keys only) + force-exit + snapshot composition (D-06)
affects: [11-05 (chat agentic loop), phase 13 eval, any future page reusing x-academic-paper-filters]

# Tech tracking
tech-stack:
  added: []
  patterns: [mode-composition (prop flag + snapshot), Http::fake payload capture, gated shared-component controls]

key-files:
  created: []
  modified:
    - app/Livewire/Pages/Student/AcademicPaperIndex.php
    - resources/views/livewire/pages/student/academic-paper-index.blade.php
    - resources/views/components/academic-paper-filters.blade.php
    - tests/Feature/AcademicPaperIndexHybridTest.php
    - tests/Feature/AiServiceTest.php

key-decisions:
  - "author/adviser appended to the sidecar filters array ONLY when paperTabActive — browse-mode payloads stay byte-identical (T-11-15)"
  - "Tab switches are pure $set('paperTabActive', ...) with no reset/requery; recommendationsSnapshot extended with the three new props (T-11-17)"
  - "New controls gated with @if ($wire->paperTabActive ?? false) — null-safe form of the UI-SPEC verbatim gate that would 500 the admin page (Rule 1 auto-fix)"
  - "updatedAuthorFilter/updatedAdviserFilter use the updatedYearFilter-style refine-only handlers (no force-exit); updatedStatusFilter force-exit untouched"

patterns-established:
  - "Mode-composition: paper tab mirrors the similar-books mode — flag prop + snapshot/restore, status-filter force-exit honored inside the tab"
  - "Payload-key capture: exact array_keys assertions on $request['filters'] lock the seven-key tab contract and the five-key browse contract"

requirements-completed: [SEARCH-05]

# Metrics
duration: 40min
completed: 2026-08-15
---

# Plan 11-04: Paper Tab on the Student Academic Paper Page — Summary

**Browse / Paper Search tab strip on the student AcademicPaperIndex page with author + adviser selects gated to the tab, conditional sidecar filter passthrough (`author`/`adviser` keys), and browse-mode payloads locked byte-identical to Phase 9/10**

## Performance

- **Duration:** 40 min
- **Started:** 2026-08-15T17:40:00
- **Completed:** 2026-08-15T18:20:14
- **Tasks:** 3 (Task 1 TDD: test + feat commits)
- **Files modified:** 5

## Accomplishments
- Paper search mode live: Browse / Paper Search tabs (pure prop sets, zero state loss), topic + author + adviser + year-range controls, results in sidecar RRF order with the "Results ranked by relevance" caption, no sort control
- Payload contract locked both ways: tab mode posts exactly the five legacy keys PLUS `author`/`adviser`; browse mode posts exactly the five legacy keys even with the new props set (T-11-14/T-11-15)
- Mode composition honored: status-filter force-exit drops to the SQL path inside the tab; author/adviser refine-only; similar-mode round-trips restore tab state with zero re-queries; shared admin page unaffected (selects gated off)

## Task Commits

Each task was committed atomically:

1. **Task 1: AcademicPaperIndex — props, computeds, handlers, conditional passthrough, snapshot** - `f7057b8f` (test, RED) + `924fff3a` (feat, GREEN)
2. **Task 2: Blade — tab strip, idle empty state, relevance caption, wire:targets + author/adviser selects** - `6417e258` (feat)
3. **Task 3: Feature tests — tab-mode forwarding, browse back-compat, force-exit, state preservation + AiServiceTest passthrough** - `5384b793` (test)

**Plan metadata:** pending final commit (docs(11-04))

## Files Created/Modified
- `app/Livewire/Pages/Student/AcademicPaperIndex.php` - authorFilter/adviserFilter/paperTabActive props, availableAuthors/availableAdvisers computeds, updatedAuthorFilter/updatedAdviserFilter refine-only handlers, conditional filters passthrough, clearFilters + recommendationsSnapshot extension
- `resources/views/livewire/pages/student/academic-paper-index.blade.php` - tabs-boxed Browse/Paper Search strip, idle "Search the paper collection" empty states (mobile sm / desktop default), relevance caption, extended wire:target lists, filters component wiring
- `resources/views/components/academic-paper-filters.blade.php` - Alpine availableAuthors/availableAdvisers props + init, gated author/adviser selects, hasActiveFilters + Author/Adviser badges
- `tests/Feature/AcademicPaperIndexHybridTest.php` - five new tests: tab-mode filter forwarding, browse back-compat, force-exit in tab, tab-switch state preservation, similar-mode restore
- `tests/Feature/AiServiceTest.php` - `it_passes_author_and_adviser_filter_keys_to_the_sidecar` (top-level keys exactly query/filters/corpus/limit/k)

## Decisions Made
- Followed plan as specified. One deviation auto-fixed (below) and one UI-SPEC nicety (per-mode search placeholder swap) deliberately left out of scope — the plan's Task 2 action list does not include it and the plan is the execution authority.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Blocking] Null-safe paperTabActive gate in the shared filters component**
- **Found during:** Task 2 (Blade — author/adviser selects in shared component)
- **Issue:** Plan/UI-SPEC verbatim markup `@if ($wire->paperTabActive)` is a server-side PHP property read. The filters component is shared with the admin page (`admin-academic-paper-index.blade.php`), whose component `AdminAcademicPaperIndex` has NO `paperTabActive` property — an undefined-property read on the admin page becomes an ErrorException under Laravel's error handler → 500.
- **Fix:** `@if ($wire->paperTabActive ?? false)` — the null-coalescing form evaluates identically on the student page and cleanly to false on admin (no warning, no exception).
- **Files modified:** resources/views/components/academic-paper-filters.blade.php
- **Verification:** `php artisan test --filter FiltersTest` (4 passed) and `--filter AcademicPaperFormTest` (5 passed) render AdminAcademicPaperIndex green after the change; HybridTest stays green.
- **Committed in:** 6417e258 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 blocking)
**Impact on plan:** Necessary to prevent an admin-page 500 while keeping student-page semantics identical. No scope creep.

## Issues Encountered
- Initial `AcademicPaperIndexSimilarTest` run showed 1 failure while a parallel HybridTest run shared the test DB; the suite is green when run sequentially (8/8). Parallel phpunit invocations against the shared sqlite test database contend — run suite commands one at a time.
- PowerShell 5.1 cannot pass `--filter 'A|B|C'` union pipes — each filter run separately, per the documented quirk.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- SEARCH-05 complete: users can search papers by topic, author, year, or adviser and open a paper's catalog page (detail modal) from paper-tab results
- 11-05 (agentic chat loop) can reference the paper tab's live `/search` payload contract: `author`/`adviser` keys inside the permissive `filters` dict
- Optional follow-up (out of plan scope): per-mode search placeholder swap ("Search papers by topic, author, or title..." when the paper tab is active) from UI-SPEC Copywriting

---
*Phase: 11-academic-papers-agentic-search · Plan 11-04*
*Completed: 2026-08-15*

## Self-Check: PASSED

- All task artifacts exist: `app/Livewire/Pages/Student/AcademicPaperIndex.php`, `resources/views/livewire/pages/student/academic-paper-index.blade.php`, `resources/views/components/academic-paper-filters.blade.php`, `tests/Feature/AcademicPaperIndexHybridTest.php` (5 new tests), `tests/Feature/AiServiceTest.php` (1 new test)
- All task commits exist in `git log`: `f7057b8f` (test), `924fff3a` (feat), `6417e258` (feat), `5384b793` (test)
- Full suite: 591 passed / 3 skipped (585 baseline + 6 new), `php artisan view:cache` compiles, Pint clean on touched files
- Threat register: T-11-14/T-11-15 locked by exact payload-key assertions; T-11-16 by `string|max:100|nullable` validation; T-11-17 by state-preservation + no-re-query assertions
