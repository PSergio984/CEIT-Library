---
phase: 08-hybrid-search-foundation
plan: 05
subsystem: ui
tags: [livewire, hybrid-search, blade, fallback, filters]

requires:
  - phase: 08
    provides: "08-04 AiService gateway (search + typed exceptions), existing AcademicPaperIndex component + blade"
provides:
  - "runHybridSearch() action on the papers page: sidecar-ordered hybrid results with forwarded filters"
  - "Hybrid card rendering (mobile + desktop) with live availability badges from the local DB"
  - "Graceful SQL fallback + 'AI search unavailable' notice when the sidecar is down"
  - "4 feature tests (ordering, filter forwarding, fallback, short-query guard)"
affects: [08-06, 09, 10, 12]

tech-stack:
  added: []
  patterns: ["Action method (not #[Computed]) for HTTP calls", "Sidecar order preserved via findMany + keyBy remap", "Filter hooks re-run hybrid search (statusFilter stays SQL-only)"]

key-files:
  created:
    - tests/Feature/AcademicPaperIndexHybridTest.php
  modified:
    - app/Livewire/Pages/Student/AcademicPaperIndex.php
    - resources/views/livewire/pages/student/academic-paper-index.blade.php

key-decisions:
  - "Filter change hooks (year/department/paperType/yearFrom/yearTo) also re-run hybrid search so filters narrow active results; statusFilter remains SQL-only (D-04 availability)"
  - "Desktop hybrid view renders cards (grid) instead of the mary-table — no pagination on sidecar results"
  - "Fallback notice uses inline SVG (blade-icons x-icon component does not know o- names)"

patterns-established:
  - "Hybrid results = id list from sidecar → findMany → keyBy(id) → remap in sidecar order"
  - "Blade branch: @if(!is_null($hybridResults)) hybrid cards @else existing SQL path @endif"

requirements-completed: [SEARCH-01]

duration: 1h 10min
completed: 2026-08-13
---

# Phase 8 Plan 5: Papers-Page Hybrid Search UI Summary

**Papers page now serves sidecar-ordered hybrid search results (BM25+semantic via AiService) with filter forwarding and live DB availability badges, degrading to the existing SQL search with a notice when the sidecar is down**

## Performance

- **Duration:** 1h 10min
- **Started:** 2026-08-13T23:55:00+08:00
- **Completed:** 2026-08-14T01:05:00+08:00
- **Tasks:** 3
- **Files modified:** 3 (1 created, 2 modified)

## Accomplishments

- `runHybridSearch()`: ≥3-char guard, forwards paper_type/department/year filters, calls `AiService::search(query, filters, 'catalog', 10)`, hydrates models via `findMany` preserving sidecar rank order.
- Mobile + desktop hybrid card rendering (identical card markup to the SQL path, escaped `{{ }}`), `wire:key="hybrid-mobile-{id}"` / `hybrid-desktop-{id}`.
- Fallback: `AiServiceUnavailableException`/`AiServiceAuthException` → `aiSearchFailed = true` → SQL path + "AI search unavailable — showing basic results" alert.
- Filter change hooks re-run hybrid search so filters narrow active results (statusFilter stays SQL-only per D-04).
- 4 tests: sidecar-ordered rendering (paper-77 before paper-78 despite higher... actually lower DB id — fixture-driven), filter forwarding assertion, ConnectionException fallback (page still renders + notice), short-query no-request guard.
- Full suite: 507 passed, 2 skipped.

## Task Commits

1. **Task 1: runHybridSearch() wiring** - `41ab4526` (feat, combined)
2. **Task 2: blade hybrid branches + notice** - `41ab4526`
3. **Task 3: AcademicPaperIndexHybridTest** - `41ab4526`

**Plan metadata:** committed in CEIT-Library repo (this summary)

## Files Created/Modified

- `app/Livewire/Pages/Student/AcademicPaperIndex.php` - hybridResults/aiSearchFailed props, runHybridSearch(), filter-hook re-runs
- `resources/views/livewire/pages/student/academic-paper-index.blade.php` - hybrid branches in mobile + desktop, fallback alert
- `tests/Feature/AcademicPaperIndexHybridTest.php` - 4 tests

## Decisions Made

- Filter hooks (5 of 6) re-run hybrid search — deviation from plan text (only updatedSearch specified) but required by must_have "filters narrow hybrid results"; documented.
- Desktop hybrid = card grid (plan: "hybrid card list in both sections").
- Inline SVG for the notice icon — `<x-icon>` is blade-icons' component (set "default"), not Mary's icon prop; `o-` names fail there.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Icon component mismatch**
- **Found during:** Task 3 test run
- **Issue:** `<x-icon name="o-exclamation-triangle">` threw SvgNotFound — blade-icons' x-icon doesn't know `o-` names (Mary's `icon=` prop does).
- **Fix:** replaced with inline SVG (repo's QR-modal pattern).
- **Files modified:** blade view
- **Committed in:** 41ab4526

**2. [Rule 1 - Bug] Tests crashed on unauthenticated Auth::user()**
- **Found during:** Task 3 test run
- **Issue:** blade low-credit branch accesses `Auth::user()->credit_score`; Livewire::test without actingAs → null.
- **Fix:** actingAs(User::factory()->create()) in setUp (page is behind auth middleware).
- **Files modified:** test file
- **Committed in:** 41ab4526

**3. [Rule 1 - Bug] Fixture has 1 result but ordering test needs 2**
- **Found during:** Task 3 test run
- **Issue:** shared search.json fixture is single-result; assertSeeInOrder needed two.
- **Fix:** test builds a two-result response locally (fixture untouched for AiServiceTest).
- **Files modified:** test file
- **Committed in:** 41ab4526

---

**Total deviations:** 3 auto-fixed (3 bugs, Rule 1)
**Impact on plan:** All necessary; no scope creep.

## Issues Encountered

- CRLF vs LF line endings broke PowerShell heredoc-based edits — used direct edit tool / python with newline='' instead.

## User Setup Required

None.

## Next Phase Readiness

- 08-06 (sync): observers dispatch `AiService::rebuildIndex()` — the UI already consumes fresh indexes.
- 10 (availability in chat): hybrid cards already show live DB availability.
- Manual UAT per RESEARCH §8.4: exact title → top result; catalog code → rank 1; Taglish → relevant hit; dropdowns narrow; kill sidecar → fallback notice.

---
*Phase: 08-hybrid-search-foundation*
*Completed: 2026-08-14*
