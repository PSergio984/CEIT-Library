---
phase: 8
slug: hybrid-search-foundation
status: passed
verified: 2026-08-14
requirements: [SEARCH-01, SEARCH-07]
---

# Phase 8 — Verification Report

**Phase Goal:** Walking skeleton: Laravel exports the catalog + policy corpus (ai:export-corpus), the FastAPI sidecar serves hybrid BM25+semantic search (RRF fusion), and event-driven + scheduled sync with nightly reconciliation keeps the index fresh.

## Success Criteria — must_haves verification

| # | Success criterion | Verified by | Result |
|---|---|---|---|
| 1 | User can find catalog records with natural-language queries (paraphrase or exact ISBN/title) via hybrid BM25 + semantic search | `AcademicPaperIndexHybridTest` (sidecar-ordered rendering, filter forwarding); sidecar smoke test on real corpus ("water pump" → 3 ranked results); golden-set eval (paraphrase P@5 0.72, taglish 0.8) | ✓ |
| 2 | Catalog changes appear in search results within minutes of an edit, and nightly reconciliation confirms the index matches the database | `AiIndexObserverTest` (create/update → debounced 60s job; delete → immediate job; pivot attach → job); `ReconcileAiIndexTest` (match = exit 0, mismatch = exit 1, --repair dispatches, stale >26h flagged); scheduler entries (hourlyAt 5/10, dailyAt 02:00) | ✓ |
| 3 | `ai:export-corpus` reproduces the corpus JSON files from the database on demand, and the sidecar `/health` reports index coverage | `ExportAiCorpusTest` (schema/anti-PII/deletion/sanitization, 7 tests); sidecar `/health` returns documents == embedded (43/43 on real corpus) | ✓ |
| 4 | Search quality is measurable against a bootstrap golden set from day one | 25-case golden set (user-approved) + `app/eval.py` (P@5/R@5/F1/top1/negative-pass-rate); gate table recorded in 08-03-SUMMARY | ✓ |

## Requirements Traceability

| REQ-ID | Requirement | Status |
|---|---|---|
| SEARCH-01 | User can search the library catalog with natural-language queries (hybrid BM25 + semantic, RRF fusion) | ✓ Complete |
| SEARCH-07 | Catalog, paper, and policy data sync from the Laravel database into the search index automatically (export + rebuild) | ✓ Complete |

## Codebase Checks

- `php artisan test` — **518 passed, 3 skipped** (SidecarLiveTest env-gated + 2 pre-existing skips). Full regression green.
- `vendor/bin/pint --dirty` — clean.
- Sidecar: `uv run pytest -q` — 23 passed; `uvx ruff check .` — clean.
- Sidecar smoke: rebuild 43 docs (real embeddings), /health ok 43/43, /search returns results, 401 without token.
- Scheduler: `php artisan schedule:list` shows ai:export-corpus (:05 hourly), ai:sync-index (:10 hourly), ai:reconcile-index (02:00 daily).

## Known Limitations (documented, non-blocking)

- Golden-set exact-title/code P@5 is mathematically capped by single-relevant-doc cases; measured via top1 rate (0.67/0.50) with faker-corpus artifacts documented in 08-03-SUMMARY. Re-map golden set when real catalog data lands.
- `RuleHeader::ruleRegulations()` relation orders by a nonexistent `order` column (R10 latent bug) — worked around in CorpusExporter; model fix deferred (out of phase scope).
- Sidecar live round-trip test requires `SIDECAR_LIVE_TEST=1` (skipped in CI).

## Human Verification Items (UAT)

1. With sidecar running: exact title query → top result; catalog code → rank 1 (code pin); Taglish paraphrase → relevant hit; dropdown filters narrow results.
2. Kill the sidecar → papers page falls back to SQL search with "AI search unavailable — showing basic results" notice (no 500).
3. Edit a paper in admin → appears in search within ~60-120s; delete a paper → gone fast.
4. `php artisan ai:reconcile-index` → "AI index in sync" when fresh; `--repair` after drift.
