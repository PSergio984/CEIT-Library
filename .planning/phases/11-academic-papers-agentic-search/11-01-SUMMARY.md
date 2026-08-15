---
phase: 11-academic-papers-agentic-search
plan: 01
subsystem: api
tags: [laravel, phpunit, corpus, exporter, search, ai-sidecar]

# Dependency graph
requires:
  - phase: 8-hybrid-search-foundation
    provides: CorpusExporter::exportCatalog()/exportPolicies() + versioned atomic rebuild contract (ADR 0004)
  - phase: 9-rag-chat-policy-qa
    provides: catalog corpus tag + metadata filter/citation contract (ADR 0006)
provides:
  - Rich single-title paper corpus docs: one title occurrence + authors:/research_adviser:/technical_adviser:/dean:/department:/publication_year:/paper_type:/catalog_code: segments in doc text (D-01..D-03, ADR 0013)
  - Locked-schema feature test pinning the exact rich shape, metadata envelope, and absence of abstract/summary/keywords keys
  - Re-exported dev catalog.json with unchanged metadata keys, corpus tag `catalog`, schema_version 1 — the wave-0 contract 11-02 conftest fixtures and search.py author/adviser filters match against
affects: [11-02-sidecar-filters-agentic-loop, phase-13-evaluation-stack]

# Tech tracking
tech-stack:
  added: []
  patterns: [red-green pair across exporter + locked-schema test, observer/queue suppression via Bus::fake for CLI-proof scripts]

key-files:
  created: []
  modified:
    - app/Services/CorpusExporter.php
    - tests/Feature/ExportAiCorpusTest.php

key-decisions:
  - "Dropped the Phase 8 title-doubling: $segments = [$title.'.'] — the ONLY exporter change; metadata block, corpus tag, schema_version, and sanitize() untouched"
  - "Locked-schema test now asserts single-title prefix + all 8 text segments + unchanged metadata keys + no abstract/summary/keywords key"

patterns-established:
  - "Pattern 1: exporter doc shape = single title + labeled name/year/type/code segments; metadata block stays the filter + citation contract (ADR 0013)"
  - "Pattern 2: shape changes ride the existing versioned atomic rebuild — schema_version stays 1, no contract change (ADR 0004)"

requirements-completed: [SEARCH-05]

# Metrics
duration: 68min
completed: 2026-08-15
---

# Plan 11-01: Rich paper corpus doc shape — single-title text composition in CorpusExporter

**CorpusExporter::exportCatalog() now composes each paper doc's searchable text from a single title occurrence plus the authors/research_adviser/technical_adviser/dean/department/publication_year/paper_type/catalog_code segments (D-01..D-03, ADR 0013), with the metadata block, `catalog` corpus tag, and schema_version 1 untouched — locking the exact shape in the locked-schema feature test and re-exporting the dev corpus**

## Performance

- **Duration:** 68 min
- **Started:** 2026-08-15T17:25:00Z
- **Completed:** 2026-08-15T18:33:00Z
- **Tasks:** 3
- **Files modified:** 2

## Accomplishments
- Rich paper doc text: `$segments = [$title.'.']` (single title occurrence, no doubling) — the ONLY exporter change, all other lines verbatim
- Locked-schema test updated: single-title prefix, all 8 segments asserted in text, metadata envelope keys unchanged, no `abstract`/`summary`/`keywords` key
- Dev corpus re-exported with the new shape; proof script verified on-disk docs (single-title text + all segments + metadata.authors array + adviser/dean strings + url) then fully cleaned up; reconcile suite green with new shape

## Task Commits

Each task was committed atomically:

1. **Task 1: Rich paper doc text — single title composition in CorpusExporter::exportCatalog()** - `0067891b` (feat)
2. **Task 2: Lock the rich shape — update it_exports_catalog_documents_with_locked_schema** - `659bc8c7` (test)
3. **Task 3: Re-export the dev corpus and prove the shape on disk + no reconcile drift** - no code change, no commit

**Plan metadata:** pending (docs commit via gsd-sdk)

## Files Created/Modified
- `app/Services/CorpusExporter.php` - line 26: `$segments = [$title.'.', $title.'.']` → `$segments = [$title.'.']`; nothing else touched
- `tests/Feature/ExportAiCorpusTest.php` - `it_exports_catalog_documents_with_locked_schema`: single-title prefix assert, str_contains asserts for authors/research_adviser/technical_adviser/dean/department/publication_year/paper_type/catalog_code, `assertArrayNotHasKey` for abstract/summary/keywords

## Decisions Made
None - followed plan as specified (Task 1/2 red-green pair executed exactly as designed; suite went RED on the old doubled-title assertion after Task 1, GREEN after Task 2).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Dev DB has zero papers — Task 3 sample-doc evidence needed a seeded doc**
- **Found during:** Task 3 (Re-export the dev corpus and prove the shape on disk)
- **Issue:** Dev PostgreSQL `academic_papers` table is empty (0 rows), so the re-exported `catalog.json` had 0 docs and no sample doc could be inspected; additionally, seeding rows via the CLI fired `AiIndexRebuildImmediateJob` (observer-dispatched) against the sync queue, making real HTTP calls to the sidecar (down) and throwing ConnectionException.
- **Fix:** Wrote a throwaway proof script (temp dir, never committed) that `Bus::fake()`s the queue (same technique as ReconcileAiIndexTest), creates the exact seedPaper() fixture, exports, asserts single-title text + all 8 segments + metadata types, prints the evidence line, then deletes every created row and re-exports to restore the pre-plan state. Dev DB verified back to 0 rows in all 5 tables; `catalog.json` left on disk with a valid envelope.
- **Files modified:** none (script at `C:\Users\admin\AppData\Local\Temp\opencode\11-01-proof.php`, not in repo)
- **Verification:** `PROOF PASSED` evidence line (`id: paper-3`, text begins with one title occurrence followed by `authors:`), DB counts back to 0, reconcile + full suites green
- **Committed in:** no commit (no repo change)

**2. [Rule 3 - Blocking] `--filter 'A|B'` union pipe unusable in this PowerShell environment**
- **Found during:** Task 3 verification
- **Issue:** `php artisan test --filter 'ExportAiCorpusTest|ReconcileAiIndexTest'` splits on the pipe (`'ReconcileAiIndexTest' is not recognized`). The plan's execution context explicitly anticipated this: "run `php artisan test --filter X` as-is (single filter per call, no union pipes)".
- **Fix:** Ran the two filters separately: `ExportAiCorpusTest` 8 passed (64 assertions), `ReconcileAiIndexTest` 7 passed (10 assertions) — equivalent coverage.
- **Files modified:** none
- **Verification:** both suites exit 0
- **Committed in:** no commit (verification-only)

---

**Total deviations:** 2 auto-fixed (2 blocking)
**Impact on plan:** Both auto-fixes were environment workarounds, not scope creep. Plan content executed exactly as written.

## Issues Encountered
- Imagick version warning (1809 vs 1808) on every `php artisan` call — pre-existing environment noise, harmless.
- The full test suite's `setUp()` fixture-unlink removes `catalog.json` after tests; re-ran the export at the end so the dev corpus file is present with the new shape (pre-existing test behavior, unchanged).
- `Model::withoutEvents()` does not suppress the observer-dispatched rebuild jobs on Laravel 13 — `Bus::fake()` is the correct suppression for CLI scripts (documented in the deviation above).

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- **11-02 (sidecar filters + agentic loop):** the exporter is now the source of truth for `metadata.authors` (array) and `metadata.research_adviser`/`technical_adviser` (strings); 11-02's conftest fixtures must mirror this exact template (single title + all segments) in the same wave, and `search.py` additive `author`/`adviser` clauses match against these metadata keys.
- Wave-end eval re-run (`python -m app.eval --corpus catalog`) will re-embed the changed whole-doc text — expected vector shift, author/adviser/topic queries should improve.
- No blockers.

---
*Phase: 11-academic-papers-agentic-search*
*Completed: 2026-08-15*

## Self-Check: PASSED

Verified: 11-01-SUMMARY.md exists; both task commits present in git log (0067891b feat, 659bc8c7 test); ExportAiCorpusTest 8 passed / ReconcileAiIndexTest 7 passed / full suite 585 passed, 3 skipped; catalog.json re-exported (source: academic_papers, schema_version: 1).
