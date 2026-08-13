---
phase: 08-hybrid-search-foundation
plan: 02
subsystem: api
tags: [python, fastapi, hybrid-search, bm25, semantic, rrf, sqlitesearch, sentence-transformers]

requires:
  - phase: 08
    provides: "CONTEXT D-01..D-18, RESEARCH §4.2/§8.2, corpus export contract (08-01)"
provides:
  - "ceit-ai-sidecar repo (github.com/PSergio984/ceit-ai-sidecar): FastAPI hybrid search engine"
  - "HybridSearch: FTS5 BM25 + multilingual semantic + RRF k=60 + post-retrieval filters + catalog-code pin"
  - "Endpoints /health /search /index/rebuild /metrics with X-Sidecar-Token auth"
  - "Atomic full rebuild (versioned artifacts, state.json swapped last, old index served mid-rebuild)"
  - "23 pytest green + ruff clean"
affects: [08-03, 08-05, 08-06, 09]

tech-stack:
  added: [fastapi 0.141, uvicorn 0.52, sentence-transformers 5.7, sqlitesearch 0.3, pydantic-settings, numpy]
  patterns: ["Whole-document embeddings (no chunking)", "RRF k=60 fusion with post-retrieval filters", "Versioned cache artifacts + atomic state swap", "Threading lock on rebuild, test hook for embedder injection"]

key-files:
  created:
    - ceit-ai-sidecar/app/config.py
    - ceit-ai-sidecar/app/ingest.py
    - ceit-ai-sidecar/app/rebuild.py
    - ceit-ai-sidecar/app/health.py
    - ceit-ai-sidecar/app/search.py
    - ceit-ai-sidecar/app/main.py
    - ceit-ai-sidecar/tests/test_rrf.py
    - ceit-ai-sidecar/tests/test_ingest.py
    - ceit-ai-sidecar/tests/test_filters.py
    - ceit-ai-sidecar/tests/test_api.py
  modified: []

key-decisions:
  - "sqlitesearch id_field must be 'doc_id' (library reserves 'id' as its own column — 'id' collides)"
  - "np.save appends .npy when missing — tmp vector files named *.tmp.npy"
  - "FTS5 has no stemming by default: 'water pump' does NOT match 'water pumps'; tests use exact terms"
  - "Rebuild reads settings.cache_dir (configurable) so tests can point at tmp dirs"
  - "Embedder injection hook (rebuild._embed_override) keeps API tests model-free"

patterns-established:
  - "Versioned cache artifacts (docs-N.json, vectors-N.npy, index-N.db) + state.json written last via os.replace"
  - "Retrieve-all at corpus scale for both rankers (no limit*500 magic pool)"
  - "Code-exact pin: ^CEIT-[A-Z]{2}-\\d{2}(-\\d+)?$ regex, uppercased comparison, pins matching catalog_code to rank 1"

requirements-completed: [SEARCH-01, SEARCH-07]

duration: 2h 10min
completed: 2026-08-13
---

# Phase 8 Plan 2: Hybrid Search Sidecar Summary

**ceit-ai-sidecar: FastAPI hybrid search engine — FTS5 BM25 + paraphrase-multilingual-MiniLM-L12-v2 semantic + RRF k=60 fusion, token-gated endpoints, atomic full rebuild, 23 green tests, smoke-verified on the real 43-doc corpus**

## Performance

- **Duration:** 2h 10min
- **Started:** 2026-08-13T20:05:00+08:00
- **Completed:** 2026-08-13T22:15:00+08:00
- **Tasks:** 4
- **Files modified:** 17 (repo: 11 app/test files + scaffold)

## Accomplishments

- New private repo `PSergio984/ceit-ai-sidecar` (fresh, not a clone of rag-search-engine — D-16), pushed to GitHub.
- HybridSearch: sqlitesearch FTS5 BM25 + numpy cosine semantic, RRF k=60, post-retrieval filters (paper_type/department/publication_year/year_from/year_to/corpus), catalog-code pin.
- Index lifecycle: `load_documents` (strict validation, duplicate-id rejection), whole-doc embeddings, versioned artifacts + atomic state swap, threading lock, embedder test hook.
- FastAPI: `/health` (coverage + staleness), `/search`, `/index/rebuild`, `/metrics`; X-Sidecar-Token via `secrets.compare_digest`; 401 envelope per contract.
- 23 pytest tests: hand-computed RRF pins, ingest validation, filter semantics, API auth/behavior, concurrent rebuilds.
- Smoke test on real corpus: rebuild 43 docs (14s), /health ok 43/43 embedded, search "water pump" returns 3 ranked results, no-token 401.

## Task Commits

1. **Task 1: repo + uv scaffold** - `7e4402e` (chore)
2. **Task 2: index lifecycle** - `41df34d` (feat; incl. tasks 2+3 code)
3. **Task 3: HybridSearch + endpoints** - `41df34d` (same commit)
4. **Task 4: pytest suite** - `41df34d` (tests included)

**Plan metadata:** committed in CEIT-Library repo (this summary)

## Files Created/Modified

- `app/config.py` - Settings (sidecar_token, corpus_path, model_name, host, port, cache_dir)
- `app/ingest.py` - load_documents/embed_documents/embed_query/write_cache/prune
- `app/rebuild.py` - build_index (atomic), rebuild (locked), load_state, _embed_override hook
- `app/health.py` - assemble_health per §4.2
- `app/search.py` - HybridSearch.rrf_search + filters + code pin
- `app/main.py` - FastAPI app, token middleware, 4 routes, metrics
- `tests/` - conftest (deterministic embedder + corpus fixture), 4 test modules

## Decisions Made

- sqlitesearch `id_field="doc_id"` (library's own `id` column collides with doc ids).
- `np.save` tmp naming `.tmp.npy` (numpy appends extension).
- Tests use exact FTS5 terms (no stemming): "water pumps" not "water pump".
- `cache_dir` on Settings so tests point at tmp dirs; embedder injection hook for model-free tests.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] sqlitesearch id_field='id' duplicate column**
- **Found during:** Task 4 first pytest run
- **Issue:** `TextSearchIndex(id_field="id")` crashes with `duplicate column name: id` — the library reserves `id` internally.
- **Fix:** `id_field="doc_id"`; docs get `doc_id` copy at fit; `_bm25_ranks` reads `doc.get("id") or doc.get("doc_id")`.
- **Files modified:** app/rebuild.py, app/search.py
- **Verification:** 23/23 tests green.
- **Committed in:** 41df34d

**2. [Rule 1 - Bug] np.save tmp extension**
- **Found during:** Task 4 first pytest run
- **Issue:** `np.save(".vectors-1.npy.tmp", ...)` appends `.npy` → writes `.npy.tmp.npy`; `shutil.copy2` source missing → FileNotFoundError.
- **Fix:** tmp vector path ends in `.tmp.npy`.
- **Files modified:** app/ingest.py
- **Committed in:** 41df34d

**3. [Rule 1 - Bug] Test fixture texts lacked title (FTS5 no stemming)**
- **Found during:** Task 4 rrf pins
- **Issue:** conftest `text` fields omitted the title; FTS5 (stemming off) also won't match "pumps" for "pump". BM25 never ranked paper-1.
- **Fix:** fixture texts now mirror the real export contract (title twice + labels); paper-4 retitled away from "Water" to isolate single-ranker case; test queries use exact terms.
- **Files modified:** tests/conftest.py, tests/test_rrf.py
- **Committed in:** 41df34d

---

**Total deviations:** 3 auto-fixed (3 bugs, Rule 1)
**Impact on plan:** All necessary for correctness. No scope creep.

## Issues Encountered

- Rebuild endpoint initially hit HuggingFace for the real model in tests (500) — added `rebuild._embed_override` injection hook; API tests now model-free.
- `.env` Windows paths need forward slashes for dotenv parsing.
- Smoke test required regenerating the Laravel corpus export after test cleanup deleted the JSON files.

## User Setup Required

- **GitHub:** repo created `PSergio984/ceit-ai-sidecar` (private) — done.
- **Runtime:** operator must set `SIDECAR_TOKEN` in sidecar `.env` AND `SIDECAR_TOKEN`/`SIDECAR_URL` in Laravel `.env` (same value); first startup downloads the ~470 MB embedding model.

## Next Phase Readiness

- 08-03 (golden set + eval): `data/golden_dataset.json` + eval runner can now run against the real sidecar.
- 08-05 (papers-page UI): `AiService` → sidecar `/search` confirmed working end-to-end.
- 08-06 (sync): observers can POST `/index/rebuild` via `AiService::rebuildIndex()`.

---
*Phase: 08-hybrid-search-foundation*
*Completed: 2026-08-13*
