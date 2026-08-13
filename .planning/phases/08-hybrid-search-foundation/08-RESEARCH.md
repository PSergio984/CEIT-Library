# Phase 8 Research: Hybrid Search Foundation

**Researched:** 2026-08-13
**Feeds:** `08-PLAN.md` (planner) + pattern mapper
**Confidence:** HIGH (schema/contract verified against migrations, models, routes, and the two reference codebases)

Scope anchor (locked in `08-CONTEXT.md` D-01..D-21): walking skeleton — Laravel exports corpus JSON → Python FastAPI sidecar (separate repo) serves hybrid BM25 + semantic search with RRF → minimal search box + filter dropdowns on the existing papers page → three-layer sync (event-driven debounced, hourly, nightly reconcile). No chat, no availability in index, no agentic loop.

---

## 1. Verified Ground Truth (what exists today)

| Fact | Source | Consequence for Phase 8 |
|---|---|---|
| Laravel ^13, PHP ^8.4, Livewire ^4, Volt 1.7, `robsontenorio/mary` | `composer.json` | Phase 8 needs NO new Composer packages — `Http` facade covers the sidecar |
| PHPUnit (no Pest), 73 Feature tests, 8 Unit tests | `phpunit.xml`, `tests/` | Feature tests follow existing style (see `tests/Feature/ExportTest.php`, `CatalogSequenceTest.php`) |
| Queue = `database` driver (`QUEUE_CONNECTION=database`); `jobs` table migration exists; existing job pattern: `app/Jobs/SendPushNotificationJob.php` | `.env.example`, `config/queue.php`, migrations | Debounced rebuild jobs follow `SendPushNotificationJob` conventions; queue worker assumed running |
| `academic_papers`: `id, catalog_code (unique), title, publication_year (year), paper_type, research_adviser_id? FK, technical_adviser_id? FK, department (indexed), dean_id? FK, timestamps` + `fullText(title)` (non-sqlite) | `database/migrations/2025_09_09_072226_...` | Exact field list for export; `publication_year` is a `year` type cast to int on the model |
| `authors`: `id, name`; pivot `academic_paper_authors`: `id, academic_paper_id, author_id, unique(pair)` | `2025_09_16_000001/2_...` | Authors pivot HAS an `id` column → custom Pivot model with events/touches is possible |
| `research_advisers` / `technical_advisers` / `deans`: `id, name` only | migrations + models | People names are single `name` strings, format "LASTNAME, FIRSTNAME M." or "ENGR. FIRST LAST" |
| `rule_headers`: `id, title, order`; `rule_regulations`: `id, rule_header_id FK, content (text), timestamps` | `2025_10_02_105414/5_...` | ⚠️ `RuleHeader::ruleRegulations()` calls `->orderBy('order')` but the regulations migration has NO `order` column — **verify at plan time** (export must not crash; fall back to `->orderBy('id')`) |
| Catalog code auto-generated: `CEIT-{DEPT}-{YY}-{SEQ}` (atomic sequence) | `AcademicPaper::boot()` + `generateUniqueCatalogCode()` | Real codes look like `CEIT-IT-25-01` (matches xlsx guide pattern) |
| Student browse page: `app/Livewire/Pages/Student/AcademicPaperIndex.php`, route `GET /academic-papers` (name `academic-paper.index`), Blade uses `<x-academic-paper-filters>` component; page already has `$search`, `$departmentFilter`, `$paperTypeFilter`, `$yearFilter`, `$yearFromFilter`, `$yearToFilter`, `$statusFilter`, cached `availableDepartments/availablePaperTypes/availableYears` | `routes/web.php`, Livewire + Blade | D-07/D-08 mount here; filter dropdowns ALREADY exist — Phase 8 wires the search box to hybrid search and keeps availability/status filters local (DB) |
| Route middleware includes an existing `throttle:search` group for papers routes | `routes/web.php` | Rate limiting for the search surface already exists; sidecar gets its own guard later (Phase 14) |
| Admin delete path: `AdminAcademicPaperIndex::performDelete()` → `$academicPaper->delete()` | `app/Livewire/Pages/Admin/AdminAcademicPaperIndex.php` | D-11 (immediate rebuild on delete) hooks the Eloquent `deleted` observer — no UI change needed |
| No `app/Observers/` dir yet; observers not registered anywhere | provider scan | Phase 8 creates `app/Observers/` and registers in `AppServiceProvider::boot()` |
| `config/services.php` has no AI block; `.env.example` has no sidecar keys | read directly | Add `ai_sidecar` block + `SIDECAR_URL`, `SIDECAR_TOKEN`, `AI_CORPUS_PATH` |
| Factories exist for ALL export models (`AcademicPaperFactory` uses depts `Civil Engineering`, `Information Technology`, `Electrical Engineering`; paper types `Thesis, Feasib, Capstone, Research, Practicum, Report`) | `database/factories/` | Feature tests can seed a realistic corpus; paper_type/dept values pinned |
| rag-search-engine: `HybridSearch.rrf_search(query, k=60, limit)` — expanded pool `limit*500` per retriever, rank maps, union of IDs, `rrf = Σ 1/(k+rank)`; golden set shape `{"test_cases":[{"query","relevant_docs"}]}` matching on TITLES; eval = P@k/R@k/F1 via `evaluation_cli.py --limit k` | WSL `~/workspace/rag-search-engine` (read 2026-08-13) | Port fusion math + eval CLI; **change title-matching to id-matching** (titles can collide; catalog_code is unique) |

---

## 2. Corpus Export Contract (Laravel side)

### 2.1 Serialization source map

| Export doc type | Eloquent source | Free-text (`text` field) content | Metadata |
|---|---|---|---|
| Catalog paper | `AcademicPaper::with(['authors','researchAdviser','technicalAdviser','dean'])` | title (first, weighted), people names (authors + 3 adviser/dean names, labeled), `catalog_code`, department, paper_type, year | `catalog_code, department, publication_year, paper_type, authors[], research_adviser, technical_adviser, dean, url` |
| Policy header | `RuleHeader::with('ruleRegulations')` | `Section: {title}` | `policy_type: "header", header_id, header_title, order, url` |
| Policy regulation | each `RuleRegulation` | `Section: {header_title}\n{content}` (llm-zc FAQ pattern, D-05) | `policy_type: "regulation", header_id, regulation_id, header_title, url` |

- **NOT serialized:** `Inventory` (`copy_number`, `status`) — D-04 (anti-hallucination: availability resolved live in Phase 10). Export tests must assert inventories never appear.
- **NOT serialized:** users, borrows, attendance, scores — nothing outside the two corpora.

### 2.2 JSON schema (mirrors rag-search-engine `data/movies.json` doc shape: `id/title/description` → ours: `id/title/text/metadata`)

`storage/app/ai-corpus/catalog.json`:

```json
{
  "source": "academic_papers",
  "schema_version": 1,
  "generated_at": "2026-08-13T02:00:00Z",
  "count": 112,
  "documents": [
    {
      "id": "paper-42",
      "corpus": "catalog",
      "title": "Development and Evaluation of Pigeon: A Web-Based Lost and Found System",
      "text": "Development and Evaluation of Pigeon: A Web-Based Lost and Found System. Development and Evaluation of Pigeon: A Web-Based Lost and Found System. authors: DE GUZMAN, Samantha J.; DE LARA, Jason H.; ECHAVEZ, Dave Vincent M.; NOBLE, Aliyyah Gayle G. research_adviser: Kenmar C. Bernardino. department: Information Technology. publication_year: 2025. paper_type: Capstone. catalog_code: CEIT-IT-25-02.",
      "metadata": {
        "catalog_code": "CEIT-IT-25-02",
        "department": "Information Technology",
        "publication_year": 2025,
        "paper_type": "Capstone",
        "authors": ["DE GUZMAN, Samantha J.", "DE LARA, Jason H.", "ECHAVEZ, Dave Vincent M.", "NOBLE, Aliyyah Gayle G."],
        "research_adviser": "Kenmar C. Bernardino",
        "technical_adviser": null,
        "dean": "ENGR. JORDAN N. VELASCO",
        "url": "/academic-papers/42"
      }
    }
  ]
}
```

`storage/app/ai-corpus/policies.json`:

```json
{
  "source": "rulebook",
  "schema_version": 1,
  "generated_at": "2026-08-13T02:00:00Z",
  "count": 41,
  "documents": [
    {
      "id": "policy-h3",
      "corpus": "policy",
      "title": "II. Borrowing Policies",
      "text": "Section: II. Borrowing Policies",
      "metadata": { "policy_type": "header", "header_id": 3, "header_title": "II. Borrowing Policies", "order": 2, "url": "/policies" }
    },
    {
      "id": "policy-h3-r12",
      "corpus": "policy",
      "title": "II. Borrowing Policies",
      "text": "Section: II. Borrowing Policies\nAcademic papers may be borrowed for a maximum of three (3) days within the library premises.",
      "metadata": { "policy_type": "regulation", "header_id": 3, "regulation_id": 12, "header_title": "II. Borrowing Policies", "url": "/policies" }
    }
  ]
}
```

Rules:
- Title repeated twice at the head of `text` for catalog docs (title-dominant embedding; PITFALLS §5).
- `id` = `paper-{id}` / `policy-h{id}` / `policy-h{id}-r{id}` — string IDs, stable, used by the golden set.
- `generated_at` (UTC ISO) is the index-freshness stamp; sidecar `/health` echoes it back for reconciliation.
- Strip control chars, collapse whitespace, cap field lengths (title ≤ 500, names ≤ 200, regulation content ≤ 20k) — prompt-injection hygiene at ingest (PITFALLS §11).

### 2.3 PII stance (explicit)

Student names **are** the catalog (D-02, D-21: the thesis record is public library data) — indexing them is correct. Guardrails: export lives in `storage/app/ai-corpus/` (never web-served); sidecar bound to loopback with `X-Sidecar-Token`; export contains NO emails, user accounts, borrow data, or score data. `PiiSanitizerProcessor` covers logs — the export command must never `Log::info()` document contents (token hygiene, PITFALLS §6).

### 2.4 Commands, jobs, observers

New files (all follow existing conventions):

```
app/Services/CorpusExporter.php            # pure serialization service (testable without console)
app/Services/AiService.php                 # sidecar HTTP gateway (see §4.5)
app/Console/Commands/ExportAiCorpus.php    # ai:export-corpus [--corpus=all|catalog|policy]
app/Console/Commands/SyncAiIndex.php       # ai:sync-index  (POST /index/rebuild)
app/Console/Commands/ReconcileAiIndex.php  # ai:reconcile-index --check|--repair
app/Observers/AcademicPaperObserver.php
app/Observers/PeopleNameObserver.php       # Author, ResearchAdviser, TechnicalAdviser, Dean (same behavior)
app/Observers/RulebookObserver.php         # RuleHeader, RuleRegulation
app/Jobs/AiIndexRebuildJob.php             # debounced (unique), delay = 60s (agent discretion)
app/Jobs/AiIndexRebuildImmediateJob.php    # no delay, own unique key (deletions, D-11)
config/services.php                        # add 'ai_sidecar' block
routes/console.php                         # schedule hourly + nightly
```

Trigger matrix (D-10, D-11):

| Event | Model(s) | Action |
|---|---|---|
| created/updated | `AcademicPaper`, `Author`, `ResearchAdviser`, `TechnicalAdviser`, `Dean`, `RuleHeader`, `RuleRegulation` | dispatch `AiIndexRebuildJob` (delay 60s, `ShouldBeUnique` `uniqueId: ai-index-rebuild`, `uniqueFor: 300`) |
| deleted | same + note `academic_papers` cascade deletes pivot rows | dispatch `AiIndexRebuildImmediateJob` (`uniqueId: ai-index-rebuild-immediate`) — removed papers must vanish fast (trust killer) |
| pivot attach/detach (author membership) | `academic_paper_authors` | Custom Pivot model `AcademicPaperAuthor` (`use AsPivot`… no — plain `Pivot` subclass with `$touches = ['academicPaper']` on the pivot class via `belongsToMany()->using(AcademicPaperAuthor::class)->withTimestamps()` already matches) — pivot writes touch the parent → `AcademicPaper` `updated` fires → debounced job covers it. Fallback safety net: hourly export + nightly reconcile catch anything missed |
| hourly | scheduler | `ai:export-corpus` then `ai:sync-index` (`Schedule::command(...)->hourly()` at e.g. `->hourlyAt(5)`; register next to existing `librarian:update-batch-statuses`) |
| nightly | scheduler | `ai:reconcile-index` (`->dailyAt('02:00')`, after the 12:30 AM batch) |

Job internals: `AiIndexRebuildJob::handle()` = `CorpusExporter::exportAll()` → `AiService::rebuildIndex()`. Sidecar rebuild is idempotent full-rebuild (D-12), so a debounced job racing an immediate job is safe — the sidecar serializes rebuilds with a lock (§3.5).

---

## 3. Sidecar Search Design (separate repo, D-15/D-16)

### 3.1 Repo layout

```
ceit-ai-sidecar/                    # fresh repo (NOT a clone of rag-search-engine)
├── app/
│   ├── main.py                     # FastAPI app: token middleware, routes, uvicorn run
│   ├── config.py                   # pydantic-settings: SIDECAR_TOKEN, CORPUS_PATH, MODEL_NAME, HOST, PORT
│   ├── ingest.py                   # catalog.json + policies.json → in-memory doc store (persisted docs.json)
│   ├── search.py                   # HybridSearch: BM25 (FTS5) + semantic (numpy cosine) + RRF k=60
│   ├── rebuild.py                  # full rebuild, atomic swap, threading.Lock serialization
│   ├── eval.py                     # golden-set runner (ported evaluation_cli.py, id-based)
│   └── health.py                   # index state assembly for /health
├── cache/                          # index.sqlite3, vectors.npy, docs.json  (gitignored, rebuildable)
├── data/golden_dataset.json        # D-20 (see §6)
├── tests/
│   ├── test_rrf.py                 # fusion math + dominance tests (PITFALLS §4)
│   ├── test_ingest.py              # JSON parsing, corpus tags, dedupe, PII-free asserts
│   ├── test_filters.py             # post-retrieval filter behavior
│   └── test_api.py                 # endpoints, auth, rebuild atomicity (TestClient)
├── pyproject.toml / uv.lock        # Python 3.13, uv (STACK.md)
└── Dockerfile                      # Phase 14 (D-18: dev loop is venv/uv now)
```

### 3.2 Port map — what changes vs rag-search-engine

| Reference piece | Phase 8 port | Change + why |
|---|---|---|
| `InvertedIndex` (pickled, BM25, nltk) | sqlitesearch `TextSearchIndex` (SQLite FTS5 BM25) | **Resolution of the STACK.md nltk question:** default path = FTS5 for BM25 (llm-zc approach, persistent, zero infra) + port only the RRF fusion from `hybrid_search.py`. The pickled `InvertedIndex` is dropped entirely (its build-if-missing bug is Pitfall 3). |
| `ChunkedSemanticSearch` (sentence-chunked embeddings, `cache/chunk_embeddings.npy`) | `SemanticIndex` — **one embedding per document**, `cache/vectors.npy` + doc ids in `docs.json` | PITFALLS §5: catalog + policy docs are short micro-documents — chunking fragments correlated fields (author chunk loses title). Whole-doc embedding of the `text` field; the chunk-best-score aggregation step is dropped. |
| `all-MiniLM-L6-v2` (384-dim) | `paraphrase-multilingual-MiniLM-L12-v2` (384-dim, sentence-transformers 5.7) | D-13: English + Filipino/Taglish. Same API — one-line model-name change; ~470 MB download on first build (document cold start; pre-warm at ingest — PITFALLS §8). |
| `rrf_search(query, k=60, limit)` | unchanged math: per-retriever expanded candidates → rank maps → union → `Σ 1/(k+rank)` → sort desc | Keep k=60. **Drop the magic `limit*500` pool** — corpus is hundreds of docs; retrieve-all per retriever (PITFALLS §4: decide the pool explicitly; document it). |
| `weighted_search` (alpha min-max) | NOT ported in Phase 8 | PITFALLS §4: RRF default; weighted fusion only if tuning demands it, and then with alpha-dominance unit tests. |
| `movies.json` load | `ingest.py` loading the Laravel export (files, no HTTP) | D-17: configured `CORPUS_PATH` (local dev: relative/`../ceit-library/storage/app/ai-corpus`; prod: Docker volume). |
| `evaluation_cli.py` | `eval.py` matching on doc **ids**, not titles | Titles can collide in a library catalog; ids are stable and unique. Same P@k/R@k/F1 math. |

### 3.3 Filters (D-03) — post-retrieval

Structured filters (`paper_type`, `department`, `publication_year` — exact dropdown values) are applied **after** retrieval on the expanded candidate lists, before fusion:

```
search(query, filters, corpus, limit):
  bm25 = fts5.search(query, pool=ALL)            # ranked doc ids
  sem = cosine(query_embedding, vectors, pool=ALL)
  for each retriever list: drop docs whose metadata fails filters / corpus tag
  rank maps → RRF (k=60) → top-{limit}
```

Rationale: with hundreds of docs the filtered-pool cost is trivial; post-filtering keeps semantic recall intact (a paraphrase of a dept's topic still embeds near it) and avoids FTS5 SQL complexity. If `filters` is empty or `corpus` is null, no filtering — union of `catalog`+`policy` (Phase 8 UI sends `corpus: "catalog"`; policy corpus is exercised via eval only until Phase 9). `publication_year` supports single value or `year_from`/`year_to` range to mirror the existing UI's `yearFromFilter`/`yearToFilter`.

### 3.4 BM25 field behavior for code lookups (D-02)

`catalog_code` is inside `text` (FTS5 tokenizes `CEIT-IT-25-02` into tokens `ceit`, `it`, `25`, `02`) — exact code queries like "CEIT-IT-25-02" rank top by BM25 term overlap. Add an FTS5 prefix edge case: queries that look like codes (`^CEIT-[A-Z]{2}-\d{2}`) should additionally do an exact `metadata.catalog_code == query` pre-check and pin that doc to rank 1 (belt-and-braces for code lookup UX).

### 3.5 Rebuild semantics (D-12)

`POST /index/rebuild` → synchronous full rebuild (corpus is seconds-scale): load both JSONs → validate (`schema_version`, `generated_at`, required fields per doc; fail loudly on malformed docs) → build FTS5 index into temp file + vectors into temp `.npy` + docs.json temp → **atomic swap** (os.replace) → update `index_state` (`built_at`, `source_generated_at`, counts per corpus, `model_name`, `contract_version`). A `threading.Lock` serializes concurrent rebuilds; concurrent rebuild requests return the result of the in-flight rebuild (idempotent). Searches during a rebuild keep serving the old index (no downtime; PITFALLS §8).

---

## 4. API Contract

### 4.1 Transport & auth

- Base: `http://127.0.0.1:8310` (configurable via `SIDECAR_URL`; loopback-only bind — PITFALLS "Exposing the Sidecar Publicly" anti-pattern).
- Auth: `X-Sidecar-Token: {SIDECAR_TOKEN}` on every route (FastAPI middleware; 401 otherwise; constant-time compare). Laravel enforces Gates before calling; sidecar treats requests as pre-authorized.
- Response envelope errors: `{"error": {"code": "unavailable|invalid_request|rebuild_failed", "message": "..."}}` with 4xx/5xx.
- Every response includes `contract_version: "v1"` where useful (PITFALLS §10: contract drift).

### 4.2 Endpoints

**`GET /health`**
```json
{
  "status": "ok",
  "contract_version": "v1",
  "model": "paraphrase-multilingual-MiniLM-L12-v2",
  "index": {
    "built_at": "2026-08-13T02:05:11Z",
    "source_generated_at": "2026-08-13T02:00:00Z",
    "documents": 153,
    "embedded": 153,
    "by_corpus": { "catalog": 112, "policy": 41 }
  }
}
```
Health = index loaded AND `documents == embedded` (embedding coverage, PITFALLS §8); UI/Laravel degrades when stale or absent.

**`POST /search`**
Request:
```json
{
  "query": "may thesis ba kayo tungkol sa water pump?",
  "filters": { "paper_type": null, "department": "Civil Engineering", "publication_year": null, "year_from": null, "year_to": null },
  "corpus": "catalog",
  "limit": 10,
  "k": 60
}
```
Response:
```json
{
  "query": "may thesis ba kayo tungkol sa water pump?",
  "total": 1,
  "took_ms": 41,
  "results": [
    {
      "id": "paper-77",
      "corpus": "catalog",
      "title": "Analysis of Groundwater Depletion Caused By Excessive Use of Water Pumps",
      "score": 0.0317,
      "bm25_rank": 1,
      "semantic_rank": 2,
      "metadata": {
        "catalog_code": "CEIT-CE-15-014",
        "department": "Civil Engineering",
        "publication_year": 2015,
        "paper_type": "Thesis",
        "authors": ["ROXAS, Harvey Jeremy C.", "RIVERA, John Patrick G."],
        "url": "/academic-papers/77"
      }
    }
  ]
}
```

**`POST /index/rebuild`** — body `{}` (no params; sidecar re-reads its configured corpus path).
```json
{ "status": "rebuilt", "documents": 153, "by_corpus": {"catalog": 112, "policy": 41}, "took_ms": 4210, "source_generated_at": "2026-08-13T02:00:00Z" }
```

**`GET /metrics`** (minimal hand-rolled JSON now; prometheus-client in Phase 14):
```json
{ "searches_total": 421, "rebuilds_total": 7, "last_rebuild_at": "...", "search_avg_ms": 38, "index_documents": 153 }
```

### 4.3 Laravel `AiService` (new `app/Services/AiService.php`)

```php
AiService::search(string $query, array $filters = [], ?string $corpus = 'catalog', int $limit = 10): array
// Http::withToken(config('services.ai_sidecar.token'))
//   ->baseUrl(config('services.ai_sidecar.base_url'))
//   ->connectTimeout(3)->timeout(10)
//   ->retry(2, 250, throw: false)          // SEARCH ONLY — idempotent; never retry /chat (Phase 9 note)
//   ->post('/search', [...])

AiService::rebuildIndex(): array          // timeout(120), retry(1)
AiService::health(): array                // timeout(5), retry(1)
```

- Throws `AiServiceUnavailableException` (connection refused/timeout/5xx) and `AiServiceAuthException` (401 → config misconfig, log sanitized).
- Error mapping keeps the Livewire layer clean: widget catches `AiServiceUnavailableException` → **graceful fallback to the existing local LIKE search** (`AcademicPaperIndex::academicPapers()` already implements it) and renders a subtle "AI search unavailable — showing basic results" notice (PITFALLS integration gotchas: hung sidecar must not wedge Livewire/Octane).
- Config (`config/services.php` + `.env.example`):
  ```php
  'ai_sidecar' => [
      'base_url' => env('SIDECAR_URL', 'http://127.0.0.1:8310'),
      'token' => env('SIDECAR_TOKEN'),
      'corpus_path' => env('AI_CORPUS_PATH', storage_path('app/ai-corpus')),
  ],
  ```

### 4.4 Papers-page wiring (D-07/D-08)

In `app/Livewire/Pages/Student/AcademicPaperIndex.php`:
- Keep existing filter dropdowns + availability/status logic untouched (availability is live DB — D-04; the page already computes it per row).
- Add `public ?array $hybridResults = null;` and an action (NOT inside `#[Computed]` — no HTTP in computed props): when `$search` length ≥ 3 and `updatedSearch` debounce fires (blade `wire:model.live.debounce.400ms`), call `AiService::search($search, filters from dropdowns, corpus: 'catalog', limit: 10)` → map results to paper ids → hydrate `AcademicPaper::with('authors','copies')->findMany(ids)` → render cards ordered by sidecar score (reuse existing Mary UI card patterns).
- Sidecar down → fallback to current SQL path (no UX regression).

---

## 5. Sync Mechanics (SEARCH-07)

### 5.1 Layered freshness (D-10/D-11/D-12)

```
Model events (created/updated) ──► AiIndexRebuildJob (debounce 60s, unique) ─┐
Model events (deleted)       ──► AiIndexRebuildImmediateJob (no delay)      ─┤
Hourly scheduler             ──► ai:export-corpus → ai:sync-index            ─┼─► POST /index/rebuild
Nightly 02:00                ──► ai:reconcile-index (compare counts)         ─┘
```

- **Debounce mechanics:** `ShouldBeUnique(uniqueId: 'ai-index-rebuild', uniqueFor: 300)` + `->delay(now()->addSeconds(60))` on dispatch. Bursts of edits collapse to one rebuild. The immediate job uses a different unique id so a pending debounced job can't swallow a deletion rebuild (see §2.4).
- **Idempotence:** both jobs export fresh state then POST rebuild; sidecar lock serializes; duplicate rebuilds are harmless (D-12: always full rebuild, no upserts — the Pitfall-3 "build-once" reference bug is designed out).
- **Nightly reconciliation (`ai:reconcile-index --check|--repair`):**
  1. Run fresh counts: `AcademicPaper::count()`, `RuleHeader::count()`, `RuleRegulation::count()`.
  2. `AiService::health()` → compare `by_corpus.catalog` vs paper count, `by_corpus.policy` vs header+regulation count, and `source_generated_at` freshness (< 26h).
  3. Mismatch/stale → log a sanitized `warning` + `--repair` (or auto) dispatches `AiIndexRebuildJob` (PITFALLS §3: count-reconciliation alert).
- **Rebuild endpoint semantics:** sidecar reads files from its configured path (never HTTP-pulls from Laravel, D-17); Laravel writes files first, then triggers. A rebuild with missing/stale files fails loudly (sidecar returns `rebuild_failed`; job fails visibly, retry via queue backoff).

---

## 6. Golden Set (D-19/D-20) + Eval CLI

Lives at `ceit-ai-sidecar/data/golden_dataset.json`, shape `{"version": 1, "catalog_snapshot": "<generated_at>", "test_cases": [{"query": "...", "corpus": "catalog", "filters": {...}, "relevant_docs": ["paper-42", ...], "negative": false}]}` — id-based (see §3.2), snapshot-stamped (PITFALLS §9: golden-set rot).

**Draft — 25 cases using realistic, REPRESENTATIVE values guided by `CEIT ACADEMIC PAPER.xlsx` (D-21: guide only, never production data).** The planner MUST replace `paper-N` refs with real DB ids from a real export before finalizing; user reviews/adjusts (D-19).

Representative fixture docs (fictionalized from xlsx patterns):

| Ref | Title (pattern) | Code | Dept | Year | Type | People |
|---|---|---|---|---|---|---|
| P1 | Development and Evaluation of Pigeon: A Web-Based Lost and Found System | CEIT-IT-25-02 | IT | 2025 | Capstone | authors DE GUZMAN/DELARA/ECHAVEZ/NOBLE; RA Kenmar C. Bernardino |
| P2 | Development of a Web-based Transaction Management System with Online Payment | CEIT-IT-25-01 | IT | 2025 | Capstone | RA Kenmar C. Bernardino |
| P3 | Development and Evaluation of VCC: A Mobile-based Valenzuela Commuting Companion | CEIT-IT-25-03 | IT | 2025 | Capstone | RA Kenmar C. Bernardino |
| P4 | FRIAS: Flood Risk Indicator and Alert System with Arduino and Wi-Fi Monitoring | CEIT-EE-25-07 | EE | 2025 | Research | RA ENGR. ALEX J. MONSANTO; TA ENGR. PATRICIA EMMANUELLE RAGANIT |
| P5 | DALOY: Integration of a Hydraulic Ram Pump and Water Turbine System | CEIT-EE-25-05 | EE | 2025 | Research | RA ENGR. ALEX J. MONSANTO |
| P6 | Bamboo Fibers as Reinforcement for Concrete | CEIT-CE-14-02 | CE | 2014 | Thesis | TA Engr. Albert C. San Diego; dean Dr. Yolanda G. Gadon |
| P7 | Analysis of Groundwater Depletion Caused By Excessive Use of Water Pumps | CEIT-CE-15-04 | CE | 2015 | Thesis | RA Engr. Joe Louise Garcia |
| H2-R1..R3 | Policy: "II. Borrowing Policies" regulations (borrow rules, loan period, overdue) | — | policy | — | — | — |
| H3-R1..R2 | Policy: "III. Library Hours and Conduct" regulations | — | policy | — | — | — |

Golden case table (draft):

| # | Category | Query | Corpus/filters | Expect (top-5) | Negative? |
|---|---|---|---|---|---|
| 1 | Exact title | Development and Evaluation of Pigeon: A Web-Based Lost and Found System | catalog | P1 @ rank 1 | |
| 2 | Exact title (short) | Bamboo Fibers as Reinforcement for Concrete | catalog | P6 @ rank 1 | |
| 3 | Catalog code | CEIT-IT-25-02 | catalog | P1 @ rank 1 | |
| 4 | Code family | CEIT-IT-25 | catalog | P1, P2, P3 present | |
| 5 | Paraphrase | lost and found web app para sa school | catalog | P1 | |
| 6 | Paraphrase | mobile app for commuting in Valenzuela | catalog | P3 | |
| 7 | Paraphrase (topic) | flood monitoring system with early warning | catalog | P4 | |
| 8 | Paraphrase (topic) | sustainable alternative materials for concrete reinforcement | catalog | P6 | |
| 9 | People (adviser) | papers by Kenmar Bernardino | catalog | P1, P2, P3 | |
| 10 | People (adviser, EN) | theses advised by Engr. Alex Monsanto | catalog | P4, P5 | |
| 11 | People (author) | Samantha De Guzman thesis | catalog | P1 | |
| 12 | People (dean) | papers under Dean Velasco | catalog | P1..P5 (any present) | |
| 13 | Taglish | may thesis ba kayo tungkol sa water pump? | catalog | P5, P7 | |
| 14 | Taglish | hanap ko yung project na flood alert para sa barangay | catalog | P4 | |
| 15 | Taglish | pwedeng gamitin na materyal para mas matibay ang semento? | catalog | P6 | |
| 16 | Topic + filter | transaction management system | catalog + dept=IT | P2 | |
| 17 | Filter narrowing | Valenzuela | catalog + paper_type=Capstone | P3 | |
| 18 | Year + dept | web system | catalog + year=2025 + dept=IT | P1, P2, P3 | |
| 19 | Policy EN | What are the rules for borrowing academic papers? | policy | H2-R1..R3 | |
| 20 | Policy EN | What are the library hours? | policy | H3-R1..R2 | |
| 21 | Policy Taglish | pwede ba magdala ng pagkain sa library? | policy | relevant H3-R if exists | |
| 22 | Negative | Harry Potter novel | catalog | — | ✓ |
| 23 | Negative | recipes for adobo | catalog+policy | — | ✓ |
| 24 | Negative policy | how much is the tuition fee | policy | — | ✓ |
| 25 | Acronym | FRIAS | catalog | P4 | |

**Eval CLI** (`app/eval.py`, ported `evaluation_cli.py`): `uv run eval --limit 5 [--corpus catalog|policy|all]` → per-case `precision@5`, `recall@5`, `F1`, retrieved/relevant lists; summary row; negatives scored as **pass = zero relevant-ids retrieved** (separate "negative pass rate" metric, not P/R). Add `--json` output for CI gates.

**Phase 8 quality gates (draft):** catalog exact-title/code cases P@5 = 1.0; paraphrase/topic cases P@5 ≥ 0.6; Taglish ≥ 0.6; policy P@5 ≥ 0.7; negative pass rate = 1.0; held-out split: hold cases {8, 12, 15, 21, 25} out of any k/pool tuning (PITFALLS §9 — no tuning on the full set).

---

## 7. Risks to Plan Around (PITFALLS.md mapped)

| # | Risk | Phase 8 mitigation (concrete) |
|---|---|---|
| R1 | RRF fusion confusion (P4 in PITFALLS — reference needed `debug_hybrid.py`) | Unit tests pinning: k extreme behavior, `Σ1/(k+rank)` values, rank-1-both dominance, empty union → []. Only RRF — no alpha path at all in Phase 8 |
| R2 | Index drift — build-once index bug in both references (P3) | Full-rebuild-by-trigger design (no build-if-missing anywhere); delete → immediate rebuild; nightly count reconciliation; `/health` staleness surfaced |
| R3 | Availability in index (P2) | `Inventory` never serialized — enforced by an export feature test asserting no `copy_number`/`status` keys in JSON |
| R4 | Token hygiene (P6/P10) | Token only in `.env`; loopback bind; constant-time compare; no token in logs; `PiiSanitizerProcessor` redact keys extended with `X-Sidecar-Token` if the value ever appears in logs |
| R5 | Eval rot + overfitting (P9) | Golden set versioned with `catalog_snapshot: generated_at`; id-based matching (stable ids); held-out split; regenerated at plan time against a real export |
| R6 | Chunking micro-docs (P5) | Document-level embeddings only; no sentence chunking for catalog/policy (policy is already chunked BY ROW via D-05) |
| R7 | Cold start / lazy embedding (P8) | Embed at ingest (rebuild), persist to `cache/`; `/health` reports embedding coverage; first search never pays embed-all cost |
| R8 | Contract drift (P10) | `contract_version` in responses; Laravel feature tests run against recorded sidecar fixtures; CI runs sidecar pytest + ruff on both repos |
| R9 | Prompt injection via metadata (P11) | Field truncation + control-char stripping at export; framing prompt is Phase 9, but the index must never carry untruncated librarian-editable text |
| R10 | Local finding: `RuleRegulation` has no `order` column but model `orderBy('order')` | Verify before implementing export; fallback `orderBy('id')`; flag to user (possible latent bug outside Phase 8 scope) |
| R11 | Local finding: `fullText(title)` guard skips SQLite (tests use SQLite) | Export/serialization is DB-agnostic — no impact; but don't write export tests that depend on Postgres FTS |
| R12 | Octane/long-lived client gotcha | `AiService` uses `Http::baseUrl(...)->timeout(...)` per call (fresh client each request, no pooled state); test under Octane in CI (PITFALLS integration gotchas) |

---

## 8. Validation Architecture (Nyquist dimension)

Four independent layers — each catches what the others structurally cannot.

### 8.1 Laravel side — PHPUnit Feature tests (SQLite fixtures, factories already exist)

| Test | Asserts |
|---|---|
| `tests/Feature/ExportAiCorpusTest.php` | `ai:export-corpus` writes both files; JSON schema matches §2.2 exactly (`id`, `corpus`, `title`, `text`, `metadata.*` present, types correct); `generated_at` is valid ISO; `count == documents.length`; **no inventory/status keys anywhere**; author names + adviser + dean appear in `text`; policy regulation `text` starts with `Section: <header title>`; deleted papers absent; control chars stripped |
| `tests/Feature/AiIndexObserverTest.php` | create/update `AcademicPaper` → `AiIndexRebuildJob` queued with 60s delay (Bus::fake); delete → `AiIndexRebuildImmediateJob` queued without delay; adviser rename → job queued; `RuleRegulation` delete → immediate job |
| `tests/Feature/AiServiceTest.php` | Http::fake: correct URL + `X-Sidecar-Token` header + body; timeout/retry configured; 401 → `AiServiceAuthException`; connection refused → `AiServiceUnavailableException`; retries happen for `/search` only |
| `tests/Feature/ReconcileAiIndexTest.php` | count mismatch (Http::fake health vs seeded DB) → `--repair` dispatches rebuild job; match → no-op; stale `source_generated_at` → repair |
| `tests/Feature/AcademicPaperIndexHybridTest.php` | with Http::fake sidecar results → page renders sidecar-ordered cards; sidecar down → falls back to existing SQL search, page still renders (no 500) |
| Regression | full existing suite (73 Feature + 8 Unit) stays green — `tests/Feature/ExportTest.php`, `CatalogSequenceTest.php` etc. untouched |

### 8.2 Sidecar — pytest (uv, Python 3.13)

| Test | Asserts |
|---|---|
| `test_rrf.py` | RRF score math against hand-computed ranks; both-rankers-tie behavior; doc in one ranker only gets `1/(k+rank)`; monotonic ordering; empty union → empty results (R1) |
| `test_ingest.py` | parses §2.2 fixtures; per-corpus tags; malformed/missing `generated_at` → loud failure; duplicate ids rejected; multilingual model loads and encodes a Taglish string to 384-dim |
| `test_filters.py` | department/paper_type/year/range filters applied post-retrieval; filter+corpus combination; code-exact pin (R: §3.4) |
| `test_api.py` | `/search` response schema; 401 without token / 200 with; `/health` coverage fields; rebuild atomicity (old index served mid-rebuild — simulate with slow embed); concurrent rebuild serialization |
| `eval.py` run vs `data/golden_dataset.json` | §6 gates: P@5/R@5/F1 per category + negative pass rate; printed report + `--json` for CI |

### 8.3 Integration — Laravel ↔ sidecar

- **Contract fixtures:** recorded sidecar JSON responses committed in `tests/fixtures/ai-sidecar/*.json` — feature tests never hit a live service (PITFALLS integration gotchas: slow/flaky CI).
- **Live integration test group:** `tests/Feature/SidecarLiveTest.php` tagged `@group sidecar`, skipped unless `SIDECAR_LIVE_TEST=1` — manual or CI-nightly run with both services up: export → rebuild → search → assert round-trip (SEARCH-07 end-to-end).
- CI: Laravel job (existing) + sidecar job (`uv run pytest`, `uvx ruff check`) on the separate repo.

### 8.4 Manual UAT (walking-skeleton demo script)

1. `php artisan ai:export-corpus` → inspect both JSON files (spot-check one paper + one regulation).
2. Start sidecar (`uv run uvicorn app.main:app --port 8310`); `curl -H "X-Sidecar-Token: ..." 127.0.0.1:8310/health` → ok + counts; curl without token → 401.
3. Papers page: type exact title → top result; catalog code → rank 1; Taglish paraphrase → relevant hit; dropdown filters narrow; kill sidecar → page falls back to normal search with notice.
4. Admin: edit a paper title → within ~60–120s new title searchable; delete a paper → gone from results fast (D-11); check `/health` `source_generated_at` advances.
5. Nightly: run `ai:reconcile-index --check` manually → "in sync" output; fudge a count in the sidecar fixture → `--repair` dispatches rebuild.
6. `uv run eval --limit 5` → print report; confirm gates in §6.

---

## 9. Build Order Suggestion (for the planner)

1. **Export + golden set** (Laravel only): `CorpusExporter` + `ExportAiCorpus` + feature tests + draft golden set against a real export (zero sidecar) — unblocks everything.
2. **Sidecar skeleton**: repo scaffold (uv 3.13), `config.py`, `ingest.py`, FTS5 + vectors + RRF `search.py`, `/health`, `/index/rebuild`, pytest RRF/ingest tests.
3. **Search quality**: `/search` + filters + `eval.py` + golden set → hit §6 gates before any UI work.
4. **Laravel integration**: `AiService` + papers-page wiring + fallback + contract-fixture feature tests.
5. **Sync automation**: observers/jobs, scheduler entries, reconcile command + tests; live integration test group.
6. **Polish**: code-pin behavior, `/metrics` JSON, UAT script run-through.

## 10. Sources

- Repo: `database/migrations/2025_09_09_072226`, `2025_09_16_000001/2`, `2025_10_02_105414/5`; `app/Models/{AcademicPaper,Author,ResearchAdviser,TechnicalAdviser,Dean,RuleHeader,RuleRegulation}.php`; `app/Livewire/Pages/Student/AcademicPaperIndex.php`; `routes/web.php`, `routes/console.php`; `config/services.php`, `config/queue.php`; `phpunit.xml`, `database/factories/*`; `.env.example` — read 2026-08-13.
- `CEIT ACADEMIC PAPER.xlsx` — header rows + sample rows only (GUIDE per D-21; values in §6 are fictionalized patterns, not production rows).
- `rag-search-engine` (WSL) — `cli/lib/hybrid_search.py`, `semantic_search.py`, `cli/evaluation_cli.py`, `data/golden_dataset.json`, `data/movies.json` — read 2026-08-13.
- `.planning/research/{STACK,ARCHITECTURE,PITFALLS,FEATURES,SUMMARY}.md`; `.planning/phases/08-hybrid-search-foundation/08-CONTEXT.md` (D-01..D-21 locked); `.planning/REQUIREMENTS.md` (SEARCH-01, SEARCH-07).
