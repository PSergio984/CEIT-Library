---
phase: 08-hybrid-search-foundation
plan: 01
subsystem: api
tags: [laravel, corpus, export, json, academic-papers, policies]

requires:
  - phase: 08
    provides: "CONTEXT.md locked export contract (D-01..D-06, D-12, D-17), RESEARCH.md §2.2 doc shapes"
provides:
  - "ai:export-corpus artisan command writing catalog.json + policies.json to storage/app/ai-corpus/"
  - "CorpusExporter service: pure serialization of AcademicPaper (+authors/advisers/dean) and RuleHeader/RuleRegulation into sidecar doc shapes"
  - "Feature tests pinning schema, anti-PII guarantees, deletion handling, sanitization caps"
affects: [08-02, 08-03, 08-06, 08-05]

tech-stack:
  added: []
  patterns: ["Service class in app/Services (NotificationService style)", "Command auto-discovery in app/Console/Commands", "config('services.ai_sidecar.corpus_path') env-overridable path"]

key-files:
  created:
    - app/Services/CorpusExporter.php
    - app/Console/Commands/ExportAiCorpus.php
    - tests/Feature/ExportAiCorpusTest.php
  modified: []

key-decisions:
  - "Catalog documents: id paper-{id}, corpus 'catalog', text = title twice + labeled people/department/year/type/code segments; metadata carries catalog_code/department/publication_year/paper_type/authors/advisers/dean/url"
  - "Policy docs: header doc policy-h{id} + regulation doc policy-h{hid}-r{rid}, text starts 'Section: {header title}'"
  - "Regulations loaded via independent orderBy('id') query — RuleHeader::ruleRegulations() relation orders by a nonexistent `order` column (latent R10 bug, flagged, not fixed in-phase)"
  - "Field caps: title 500, names 200, content 20000; control chars stripped; whitespace collapsed (T-03)"
  - "Inventory never serialized (D-04); no Log::info of contents (T-02)"

patterns-established:
  - "Corpus export contract: JSON envelope {source, schema_version: 1, generated_at, count, documents}"
  - "Feature tests assert the real artifact path (no Storage::fake) with setUp cleanup of prior files"

requirements-completed: [SEARCH-07]

duration: 45min
completed: 2026-08-13
---

# Phase 8 Plan 1: Corpus Export Pipeline Summary

**`ai:export-corpus` command + CorpusExporter service producing catalog.json (papers+people) and policies.json (rulebook) with pinned schema, anti-PII, and sanitization tests — the contract the sidecar ingests**

## Performance

- **Duration:** 45 min
- **Started:** 2026-08-13T18:30:00+08:00
- **Completed:** 2026-08-13T19:15:00+08:00
- **Tasks:** 3
- **Files modified:** 3 created

## Accomplishments

- CorpusExporter serializes AcademicPaper (authors, research/technical advisers, dean, department, year, type, catalog_code) and the rulebook (headers + regulations) into the RESEARCH-locked doc shapes.
- `ai:export-corpus` command (with `--corpus=all|catalog|policy`), env-overridable path via `services.ai_sidecar.corpus_path`.
- 6 feature tests: catalog schema, policy schema, anti-PII (no copy_number/status/email), deletion drop, control-char strip + 500-char cap, per-corpus export.
- Full regression: 490 tests pass (2 skipped).

## Task Commits

1. **Task 1: Create CorpusExporter** - `11c5b2ff` (feat)
2. **Task 2: Create ai:export-corpus command** - `593ef661` (feat; incl. R10 workaround)
3. **Task 3: Write ExportAiCorpusTest** - `f5cb407a` (test; incl. text-join fix)

**Plan metadata:** pending

## Files Created/Modified

- `app/Services/CorpusExporter.php` - Pure serialization service; exportCatalog/exportPolicies/exportToDisk + sanitize
- `app/Console/Commands/ExportAiCorpus.php` - artisan command, auto-discovered
- `tests/Feature/ExportAiCorpusTest.php` - 6 feature tests

## Decisions Made

- Regulations ordered by `id` via independent query because `RuleHeader::ruleRegulations()` applies `orderBy('order')` on a column that doesn't exist in `rule_regulations` (R10) — workaround in exporter; model bug left flagged for a future phase (per plan scope).
- Text field: title repeated twice + labeled segments; joined with single spaces.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] RuleHeader relation orders by nonexistent `order` column**
- **Found during:** Task 2 verification (`php artisan ai:export-corpus` on PostgreSQL)
- **Issue:** Eager-loading `ruleRegulations` through the relation applied `orderBy('order')` → QueryException `column "order" does not exist`. The RESEARCH's R10 note was confirmed live.
- **Fix:** Loaded regulations with an independent `RuleRegulation::orderBy('id')->get()->groupBy('rule_header_id')` query; added a comment flagging the latent model bug for future fix.
- **Files modified:** app/Services/CorpusExporter.php
- **Verification:** `ai:export-corpus` exports 30 catalog + 13 policy docs; tests green.
- **Committed in:** 593ef661 (Task 2 commit)

**2. [Rule 1 - Bug] Double-space in exported text field**
- **Found during:** Task 3 acceptance criteria
- **Issue:** `text` joined with ' ' while first segments ended in '. ' → "title.  title. " (two spaces), failing the starts-with-title-twice assertion.
- **Fix:** Segments end in '.'; join adds the single space.
- **Files modified:** app/Services/CorpusExporter.php
- **Verification:** ExportAiCorpusTest 6/6 green.
- **Committed in:** f5cb407a (Task 3 commit)

---

**Total deviations:** 2 auto-fixed (2 bugs, Rule 1)
**Impact on plan:** Both necessary for correctness. No scope creep.

## Issues Encountered

- Test isolation: the real storage path retained files from manual runs; added setUp cleanup of both JSON files (tests intentionally assert the real artifact path per plan).

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- 08-02 (sidecar ingest): `storage/app/ai-corpus/catalog.json` + `policies.json` contract is now real and reproducible.
- 08-03 (golden set): export can be run against the seeded DB to map golden `paper-N` refs to real ids.
- 08-06 (sync): observers can now dispatch `ai:export-corpus`-equivalent rebuilds.

---
*Phase: 08-hybrid-search-foundation*
*Completed: 2026-08-13*
