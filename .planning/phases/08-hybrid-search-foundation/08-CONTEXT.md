# Phase 8: Hybrid Search Foundation - Context

**Gathered:** 2026-08-13
**Status:** Ready for planning

## Phase Boundary

Walking skeleton: Laravel exports the catalog + policy corpus via `ai:export-corpus`; the Python FastAPI sidecar (separate repo) serves hybrid BM25 + semantic search (RRF fusion) over it; a minimal in-app search box on the existing academic papers page calls it through a new `AiService`; event-driven + scheduled sync with nightly reconciliation keeps the index fresh; a bootstrap golden set makes search quality measurable from day one.

**Scope anchor:** search + index + sync only. No chat, no availability join, no papers-specific corpus (the catalog IS academic papers), no agentic loop — those are Phases 9-11.

## Implementation Decisions

### Corpus scope & fields
- **D-01:** The catalog **is** the academic papers table (`academic_papers` + `authors` pivot). There is no separate books table. Phase 8 indexes: catalog + policy corpus (`rule_headers` + `rule_regulations`).
- **D-02:** Free-text searchable fields: `title`, people names (authors via `academic_paper_authors`, `research_adviser`, `technical_adviser`, `dean`), and `catalog_code` (exact-match — BM25 wins code lookups).
- **D-03:** Query-time FILTERS (not free-text indexed semantics): `paper_type`, `department`, `publication_year`. Filters must be supported by the `/search` API and rendered in the Phase 8 UI.
- **D-04:** Excluded from the index: `inventories.copy_number` and `inventories.status` — availability is resolved live in Phase 10, never from the index (anti-hallucination rule).
- **D-05:** Policy chunking: each `RuleHeader` becomes one index document (header title + section context); each `RuleRegulation` becomes one document with its header title as section context (llm-zc FAQ pattern). Rules form a third corpus in the same index, tagged by corpus type.
- **D-06:** Index documents carry a `corpus` tag (`catalog` | `policy`) and filterable metadata so `/search` can scope queries per corpus.

### Search surface in Phase 8
- **D-07:** Minimal in-app search: a search box wired into the existing academic papers browse page (where users already look for theses). Not headless, not a standalone page.
- **D-08:** Filters (`paper_type`, `department`, `publication_year`) rendered as dropdowns alongside the search box in Phase 8 — filter UI ships now, not in Phase 11.
- **D-09:** Chat widget, streaming, history are NOT in this phase (Phase 9).

### Index freshness semantics
- **D-10:** Three-layer sync: (1) model observers on catalog/policy edits queue a debounced `POST /index/rebuild`; (2) hourly scheduled `ai:export-corpus`; (3) nightly reconciliation verifying index ↔ DB agreement.
- **D-11:** Deletions trigger an IMMEDIATE rebuild — removed/withdrawn papers must vanish from results fast (trust killer otherwise).
- **D-12:** Always FULL rebuild from exported JSON. Corpus is small (hundreds of papers); full rebuild avoids incremental-index bugs. No upserts.

### Language support
- **D-13:** Multilingual embedding model — `paraphrase-multilingual-MiniLM-L12-v2` (handles English + Filipino/Taglish queries). Users may query in Taglish.
- **D-14:** Policy corpus is English. Thesis titles are mostly English with localized proper nouns (e.g., Valenzuela, Maysan) — BM25 covers those regardless of the embedding model.

### Sidecar repo & dev loop
- **D-15:** The Python sidecar lives in its OWN separate GitHub repo — NOT `ai-sidecar/` in this repo. (Mirrors the user's rag-search-engine/llm-zc split.)
- **D-16:** Fresh repo; port the hybrid-search pieces from `rag-search-engine` as needed (do NOT clone the repo — no movie-data baggage).
- **D-17:** Corpus delivery: configured shared path. Laravel exports to `storage/app/ai-corpus/` (this repo); the sidecar reads from a configured path (local dev: relative path; prod: Docker volume). No HTTP pull from Laravel.
- **D-18:** Dev loop: local venv/uv (not Docker-only). Docker comes in Phase 14.

### Bootstrap golden set
- **D-19:** Agent drafts the golden set from the real catalog; the user reviews/adjusts. 20-30 queries covering: exact title, `catalog_code`, paraphrase, people ("papers by Prof. Santos"), Taglish phrasing, policy Q, and negative "should not match" cases.
- **D-20:** Golden data lives in the sidecar repo at `data/golden_dataset.json` (rag-search-engine convention).
- **D-21:** `CEIT ACADEMIC PAPER.xlsx` (repo root) is a GUIDE for realistic values — real catalog data for the library. Use it to infer realistic titles, name formats, departments, paper types, and adviser fields when building the golden set and export contract. NEVER use its rows as production data (data protection).

### Agent's Discretion
- Exact debounce window for event-driven rebuilds (minutes-scale; not a user decision).
- RRF parameters (k=60) and BM25 tuning — port from rag-search-engine.
- Search result card layout on the papers page (reuse existing Mary UI card patterns).
- Export JSON schema details beyond the fields decided above.

## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Milestone & phase
- `.planning/ROADMAP.md` — Phase 8 goal, success criteria, dependency chain
- `.planning/REQUIREMENTS.md` — SEARCH-01, SEARCH-07 definitions; traceability
- `.planning/PROJECT.md` — milestone v2.0 goals, reference codebases

### Research (written for this milestone — architecture decisions already analyzed)
- `.planning/research/ARCHITECTURE.md` — sidecar architecture, data pipeline, API contract, recommended project structure, build order
- `.planning/research/STACK.md` — FastAPI/uv/sqlitesearch/OpenRouter/sentence-transformers recommendations with versions
- `.planning/research/PITFALLS.md` — index drift (P1), availability-in-index trap, RRF fusion traps (P1), eval rot
- `.planning/research/FEATURES.md` — table stakes for hybrid search, filter UX, eval expectations
- `.planning/research/SUMMARY.md` — consolidated decisions + build order

### Schema ground truth
- `database/migrations/2025_09_09_072226_create_academic_paper_table.php` — catalog fields (catalog_code, title, publication_year, paper_type, department, adviser/dean FKs)
- `database/migrations/2025_09_09_093456_create_inventories_table.php` — copies (copy_number, status) — NOT indexed
- `database/migrations/2025_09_16_000001_create_authors_table.php` + `2025_09_16_000002_create_academic_paper_authors_table.php` — author pivot
- `database/migrations/2025_10_02_105414_create_rule_headers_table.php` + `2025_10_02_105415_create_rule_regulations_table.php` — policy corpus
- `app/Models/Inventory.php` — status semantics (Available/Reserved/Unavailable)

### User-provided data guide
- `CEIT ACADEMIC PAPER.xlsx` — GUIDE ONLY (never production data). Sheets: Electrical Engineering, Civil Engineering, Information Technology, Documents. Columns: TYPE OF ACADEMIC PAPER, CATALOG NUMBER, APPROVED OFFICIAL LONG TITLE, COPIES, MEMBER 1-7, TECHNICAL ADVISER, RESEARCH PROJECT ADVISER, DEPARTMENT, DEAN. ~137 EE papers; real departments = Electrical Engineering, Civil Engineering, Information Technology; titles are long official engineering titles; names in LASTNAME, FIRSTNAME MIDDLE format.

### Reference codebases (outside this repo)
- WSL `~/workspace/rag-search-engine` — HybridSearch (BM25 + semantic + RRF), chunked embeddings with disk cache, RAG CLI modes, evaluation CLI. Access via `wsl -e bash -c '...'`.
- `D:\ai-eng\llm-zc` — ingest patterns, FAQ shape, metrics wrapper, golden-set generation lessons.

## Existing Code Insights

### Reusable Assets
- `App\Services\*` convention (e.g., NotificationService) — `AiService` fits the established service-layer pattern for the sidecar gateway.
- Mary UI component library — result cards and filter dropdowns can reuse existing Mary components already used across admin/student pages.
- Artisan command pattern in `routes/console.php` (e.g., `attendance:check-missing-timeouts`) — `ai:export-corpus` and `ai:sync-index` follow it.
- Existing Livewire class-based components (e.g., QrScanner) — the papers-page search wiring follows the same convention.

### Established Patterns
- Gates-based role authorization in `AppServiceProvider` — Phase 8 search respects existing role gates even though deeper role-awareness is Phase 12.
- Laravel scheduler in `routes/console.php` — hourly export + nightly reconciliation register there.
- `PiiSanitizerProcessor` (v1.3) — corpus export must not leak PII into JSON (names of students are part of the catalog; export is internal-only, loopback sidecar).

### Integration Points
- Academic papers browse page (`app/Livewire/` papers listing component) — search box + filters mount here.
- `AcademicPaper`, `Author`, `ResearchAdviser`, `TechnicalAdviser`, `Dean` models + relations — corpus serialization sources.
- `.env` — new sidecar config keys (`SIDECAR_URL`, `SIDECAR_TOKEN`, `AI_CORPUS_PATH`).

## Specific Ideas

- The golden set should feel like a real student: someone looking for their department's past theses, someone who knows a catalog code, someone asking in Taglish, someone asking a policy question.
- Search UX expectation: type → see matching theses with their paper_type/department/year visible; filters narrow by dropdown.

## Deferred Ideas

- None — discussion stayed within phase scope.

---

*Phase: 8-Hybrid Search Foundation*
*Context gathered: 2026-08-13*
