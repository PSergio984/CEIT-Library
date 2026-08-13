# Architecture Research

**Domain:** AI librarian sidecar (Python RAG) integration into Laravel 12 academic library system
**Researched:** 2026-08-13
**Confidence:** HIGH

> Scope note: this research answers *how the Python RAG sidecar integrates with the existing Laravel architecture* — data pipeline, API contract, session storage, Livewire widget, index lifecycle, deployment, build order. Every design choice is tied to something seen in the reference codebases (`rag-search-engine`, `llm-zc`) or the existing app.

## Standard Architecture

### System Overview

```
┌────────────────────────────────────────────────────────────────────────────┐
│                        LARAVEL APP (CEIT-Library)                          │
├────────────────────────────────────────────────────────────────────────────┤
│  ┌──────────────────┐  ┌───────────────────┐  ┌───────────────────────┐   │
│  │ Livewire Chat    │  │ AiService         │  │ ai:export-corpus      │   │
│  │ Widget (Mary UI) │→ │ (Http facade)     │  │ artisan command        │   │
│  └────────┬─────────┘  └─────────┬─────────┘  └───────────┬───────────┘   │
│           │ render / persist     │                        │ writes         │
│           ↓                      │  X-Sidecar-Token       ↓                │
│  ┌──────────────────┐            │ (127.0.0.1 only)  ┌─────────────────┐   │
│  │ ai_conversations │            │                   │ storage/app/    │   │
│  │ ai_messages      │            │                   │ ai-corpus/      │   │
│  │ (PostgreSQL)     │            │                   │ catalog.json    │   │
│  └──────────────────┘            │                   │ policies.json   │   │
├──────────────────────────────────┼───────────────────┼─────────────────┤───┤
│                                  │                   │ ingest on sync  │   │
│                    ┌─────────────▼───────────────────▼─────────────────┐   │
│                    │            PYTHON SIDECAR (FastAPI)               │   │
│                    │  /search  /chat  /summarize  /index/rebuild       │   │
│                    │  /health  /metrics (Prometheus)                   │   │
│                    ├───────────────────────────────────────────────────┤   │
│                    │  HybridSearch = BM25 InvertedIndex + Chunked      │   │
│                    │  SemanticSearch (all-MiniLM-L6-v2), RRF fusion    │   │
│                    │  cache/: index.pkl, embeddings.npy, metadata.json │   │
│                    └──────────────────────┬────────────────────────────┘   │
│                                           │ OpenAI SDK (OpenRouter)        │
│                                           ↓                                │
│                          ┌────────────────────────────────┐                │
│                          │   LLM API (provider TBD)       │                │
│                          └────────────────────────────────┘                │
└────────────────────────────────────────────────────────────────────────────┘
     ┌─────────────────────────┐
     │  OBSERVABILITY (compose) │   Prometheus ← /metrics, Grafana (provisioned
     │  Grafana :3000           │   as code), monitoring Postgres for per-call
     │  Prometheus + pg         │   records (time, tokens, cost, feedback)
     └─────────────────────────┘
```

Two network rules define the whole architecture:
1. **Laravel → sidecar** over loopback with a shared token; the sidecar is never publicly exposed.
2. **Sidecar → Laravel DB** never happens. All corpus data flows Laravel → sidecar as exported JSON.

### Component Responsibilities

| Component | Responsibility | Typical Implementation (seen in refs) |
|-----------|----------------|----------------------------------------|
| `AiService` (Laravel) | Single gateway: calls sidecar via `Http` facade, adds token, timeouts/retries, maps sidecar errors to Laravel exceptions | No existing HTTP usage in app — introduce `app/Services/AiService.php`; mirrors `main.py` client setup pattern in llm-zc |
| `ai:export-corpus` (Laravel) | Serialize `AcademicPaper`+`Author`+`Inventory`+`RuleHeader/RuleRegulation` → JSON for the sidecar | Pattern: existing `routes/console.php` artisan commands (`attendance:check-missing-timeouts`); data shape mirrors `data/movies.json` docs from rag-search-engine |
| FastAPI sidecar | Owns retrieval + generation only: BM25/semantic search, RRF fusion, prompt building, LLM calls, index build | `cli/lib/hybrid_search.py` (HybridSearch), `cli/augmented_generation_cli.py` (rag/summarize/citations/question modes) |
| Index cache (sidecar) | Pickled inverted index, `.npy` embeddings, chunk metadata | `cache/index.pkl`, `cache/chunk_embeddings.npy`, `cache/chunk_metadata.json` in rag-search-engine; persistent-index-open-vs-build in `main.py` |
| `ai_conversations` / `ai_messages` (Laravel DB) | Chat history + citations + per-message feedback, per user, role-aware | `db_save.py` (save_conversation) + `db_feedback.py` (score ±1, source user/judge) in llm-zc — but stored Laravel-side since users/roles live there |
| Livewire `ChatWidget` | UI: message list, input, streaming render, citations, thumbs up/down | Mary UI `chat-bubble` classes (daisyUI, already in app); `app/Livewire/QrScanner.php` class-based component convention |
| Observability stack | Prometheus metrics + Grafana dashboards + monitoring Postgres for per-call records | `metrics.py` (RAGWithMetrics: response_time, prompt_tokens, completion_tokens, cost), `docker-compose.yml` grafana/postgres services + `grafana/provisioning/` in llm-zc |

## Recommended Project Structure

```
CEIT-Library/                       # existing Laravel app (modified, not restructured)
├── app/
│   ├── Services/AiService.php      # NEW: Http client wrapper for the sidecar
│   ├── Livewire/AiAssistant/
│   │   └── ChatWidget.php          # NEW: class-based component (app convention)
│   ├── Console/Commands/
│   │   └── ExportAiCorpus.php      # NEW: corpus JSON export (scheduled)
│   └── Models/AiConversation.php, AiMessage.php   # NEW
├── database/migrations/            # NEW: ai_conversations, ai_messages (+feedback cols)
├── routes/console.php              # MODIFIED: schedule ai:export-corpus + ai:sync-index
├── resources/views/livewire/ai-assistant/chat-widget.blade.php   # NEW
└── ai-sidecar/                     # NEW: Python service (own repo or subdir)
    ├── app/
    │   ├── main.py                 # FastAPI app, token middleware, routes
    │   ├── search.py               # ported HybridSearch (BM25 + semantic + RRF)
    │   ├── ingest.py               # loads catalog.json/policies.json → index
    │   ├── rag.py                  # RAGBase-style rag() + prompt templates
    │   ├── metrics.py              # RAGWithMetrics port (time/tokens/cost)
    │   └── eval_utils.py           # golden dataset runner (ported evaluation_cli)
    ├── cache/                      # index.pkl, chunk_embeddings.npy, chunk_metadata.json
    ├── data/golden_dataset.json    # CEIT ground truth ({"test_cases": [...]})
    ├── grafana/provisioning/       # datasources.yml + dashboards.yml (copied pattern)
    ├── grafana/dashboards/ceit-ai.json
    ├── Dockerfile                  # python:3.12-slim + uv, pinned model
    ├── docker-compose.yml          # sidecar + prometheus + grafana + monitoring pg
    └── tests/                      # search accuracy tests vs golden dataset
```

### Structure Rationale

- **`ai-sidecar/` as a sibling directory (not nested in Laravel `app/`):** the sidecar is an independent deployable with its own dependency graph (uv/pip, torch/sentence-transformers); keeping it out of the PHP package tree avoids Composer/Herder confusion and lets it ship as a separate Docker service. Mirrors how `llm-zc` keeps the assistant code at repo root beside compose files.
- **`app/Services/AiService.php`:** the existing app has services (`App\Services\NotificationService` referenced in `BorrowTransaction`), so a service-layer gateway fits the established pattern and gives Livewire components a testable seam (mock `AiService` in tests).
- **`cache/` on a Docker volume, not baked into the image:** rebuilds of the index must survive container restarts; rag-search-engine already treats `cache/` as mutable state, not source.
- **`data/golden_dataset.json`:** the evaluation harness (milestone scope) needs ground truth in the exact shape rag-search-engine already uses — `{"test_cases": [{"query", "relevant_docs"}]}` — so eval tooling can be ported unchanged.

## Architectural Patterns

### Pattern 1: Laravel-Exported Corpus JSON (push pipeline)

**What:** Laravel owns the source of truth (PostgreSQL). An artisan command serializes the searchable entities to JSON files; the sidecar ingests JSON and builds its index. Laravel then *pushes* a rebuild trigger to the sidecar when content changes.

**When to use:** always, for this project. The alternatives:
- *Sidecar reads PostgreSQL directly:* rejected — couples Python to the Laravel schema, requires a second DB user, and the sidecar would need to replicate Eloquent joins (authors pivot, advisers, deans).
- *Sidecar polls a Laravel API:* adds a second API surface to maintain; no benefit over files.

**Trade-offs:**
- + Matches both reference codebases: rag-search-engine loads `data/movies.json` and `llm-zc` ingest scripts load source data before indexing. The porting cost is minimal.
- + Corpus is small (one university's papers/policies — hundreds to low thousands of rows). Full rebuild is seconds, so no incremental-index complexity.
- + Golden-dataset evaluation can run against the exact exported JSON.
- − Corpus is eventually consistent (stale between export runs); mitigated by scheduled + event-driven rebuild triggers (below) and by serving *live* availability from Laravel, not the index.

**Example (export shape — one doc per searchable unit):**
```json
{
  "source": "academic_papers",
  "generated_at": "2026-08-13T09:00:00Z",
  "documents": [
    {
      "id": "paper-42",
      "title": "Automated Library Book Scanner Using Image Processing",
      "text": "Automated Library Book Scanner Using Image Processing ... authors: J. Cruz, M. Santos ... department: Information Technology ... year: 2025 ... catalog_code: CEIT-IT-25-014",
      "metadata": {
        "catalog_code": "CEIT-IT-25-014", "department": "Information Technology",
        "publication_year": 2025, "paper_type": "Research Project",
        "authors": ["J. Cruz", "M. Santos"], "url": "/academic-papers/42"
      }
    }
  ]
}
```
The flattened `text` field feeds both chunk embedding (`fixed_size_chunking`/`chunk_text` in rag-search-engine's `semantic_search.py`) and BM25 tokens; `metadata` is what `/chat` echoes back as citations.

### Pattern 2: Stateless Sidecar + Laravel-Owned Conversation State

**What:** the sidecar keeps *no* session state. Each `/chat` request carries `session_id` + the last N messages. Laravel persists full history and re-sends context. Feedback lands in Laravel tables.

**When to use:** whenever the front end and users are Laravel's, and the sidecar is a swappable service.

**Trade-offs:**
- + History is per-Laravel-user, role-aware, and survives sidecar restarts/rebuilds for free.
- + Mirrors both references: rag-search-engine is a fully stateless CLI; llm-zc's RAG pipeline is stateless too — the *frontend* (`app.py`) calls `save_conversation()` after each answer, and `db_feedback.py` stores ±1 votes.
- − Token cost: resending history per request grows context; mitigate by trimming to last N messages (e.g., 10) and noting a future summarization-compaction option.

**Example:**
```php
// AiService::chat()
$response = Http::withToken(config('services.ai_sidecar.token'))
    ->timeout(90)
    ->post("{$base}/chat", [
        'session_id' => (string) $conversation->uuid,
        'message' => $request->message,
        'history' => $conversation->messages()->latest()->limit(10)->get()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content]),
    ]);
```

### Pattern 3: One-Shot RAG First, Agentic Loop Second

**What:** v1 `/chat` = retrieve-once-then-generate (search → build context → single LLM call). The agentic variant (LLM calls a `search` tool repeatedly via the Responses API, `rag_agent()` in llm-zc's `rag_helper.py`) is a later upgrade behind the same endpoint.

**When to use:** ship one-shot now; add the tool-calling loop only after the golden-dataset metrics show single-pass retrieval underperforming on multi-hop questions.

**Trade-offs:**
- + One-shot is what `augmented_generation_cli.py` does for all four modes (rag/summarize/citations/question) — the prompts port directly.
- + Lower latency, simpler streaming, predictable cost.
- − Cannot auto-correct typos or expand queries; llm-zc's `main.py` demo question ("How do I run Olama?") exists precisely to show agentic recovery — keep that as the known upgrade path.
- − Responses-API tool calling is OpenAI-only; Groq patch in `main.py` shows the compatibility cost, another reason to defer.

### Pattern 4: Non-Streaming Chat, Then SSE Streaming

**What:** v1 chat returns the full answer in one JSON response (Livewire shows a `wire:loading` spinner during the 2–10s LLM call). v2 adds streaming: sidecar exposes SSE; Livewire widget consumes via Alpine `EventSource` and persists the finished message through a Livewire action.

**When to use:** any latency > ~1s benefits from streaming; but streaming touches three layers (FastAPI `StreamingResponse`, Laravel SSE passthrough, Livewire 4 `wire:stream`/Alpine) — defer it until the non-streaming skeleton is proven.

**Trade-offs:**
- + Matches the incremental approach in llm-zc (lessons: plain chat app → metrics → feedback → judge — each layer added after the previous works).
- + Livewire 4 supports `wire:stream` for server→client streaming, but SSE consumed by Alpine keeps the sidecar as the streaming source and lets Laravel terminate the proxy when the user navigates away.
- − Polling is the worst option (noisy, slow) — reserve for environments where SSE is blocked by the host.

## Data Flow

### Request Flow (chat message)

```
User types message in ChatWidget (Blade, Mary UI chat-bubble)
    ↓ Livewire action ask()
AiService::chat()  [adds token, history, timeout]
    ↓ HTTP POST 127.0.0.1:8xxx/chat
FastAPI /chat → HybridSearch.rrf_search(query, k=60, limit=5)
    ↓ retrieved docs → build_context → prompt template → LLM (OpenRouter)
Answer + citations JSON
    ↓
AiService maps response → Livewire renders message + [1][2] citation chips
    ↓
AiService persists AiMessage(user_id, conversation_id, content, citations, usage)
    ↓ (same request)
Widget re-renders; thumbs up/down buttons call saveFeedback()
    ↓ Livewire action → AiMessage feedback column (score ±1, source user)
```

### Corpus Refresh Flow

```
Schedule (routes/console.php) ai:export-corpus → ai:sync-index  [hourly]
Model events: AcademicPaper/Inventory/RuleRegulation created|updated|deleted
    → queued job (debounced) → export + sync   [near-real-time, batch-able]
    ↓
ai:sync-index: POST sidecar /index/rebuild {manifest: generated_at}
    ↓
sidecar ingest.py: load catalog.json + policies.json → rebuild index → re-embed
    → swap index atomically (write to temp files, rename)
```

### Key Data Flows

1. **Catalog → index:** PostgreSQL → JSON export → sidecar ingest → pickled BM25 index + `.npy` embeddings. One-way; the sidecar never connects to PostgreSQL.
2. **Policy Q&A:** `RuleHeader` (title, order) + `RuleRegulation` (content, order) flatten into policy documents with `section`-style headings — analogous to llm-zc's `build_context()` which emits `section` + `Q:` + `A:` blocks; the FAQ-specific INSTRUCTIONS ("If the search returns nothing, it's likely an off-topic question") adapts to "If you can't answer from library policies or catalog, say so."
3. **Availability:** NOT in the index. Inventory status (`Available/Reserved/Unavailable` per copy, `academic_paper_id` FK) changes constantly (borrow/return). The widget enriches citations with live counts via Eloquent (`available_copies_count` accessor already exists on `AcademicPaper`) when rendering.
4. **Feedback → eval loop:** per-message ±1 votes in Laravel; a scheduled job or eval pipeline later pairs votes + LLM-as-judge scores (`judge.py` pattern: `evaluate_relevance(question, answer)` → relevance + explanation) into the golden dataset for tuning.
5. **Usage metrics:** every LLM call records time/tokens/cost (RAGWithMetrics port) → monitoring PostgreSQL → Grafana dashboard; Prometheus scrapes `/metrics` for retrieval-side numbers (index size, search latency, hit rate).

### State Management

- **Laravel is the only stateful participant.** Conversations, messages, feedback, users, roles — all PostgreSQL.
- **Sidecar state = index cache only** (`cache/`), rebuildable at any time from the exported JSON. A wiped sidecar loses nothing.
- **Observability state** (per-call records, feedback copies) lives in the monitoring Postgres, exactly as llm-zc's compose file defines a dedicated `course-assistant` postgres beside Grafana.

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| 0–1k users (this project) | Single FastAPI worker on one host/VM; non-streaming chat is fine; full index rebuild on sync; SQLite or Postgres both acceptable for monitoring store — use Postgres for Grafana provisioning parity with llm-zc |
| 1k–100k users | Add SSE streaming; run uvicorn with 2–4 workers; move sidecar to its own host (token still required); switch index rebuild to debounced per-entity re-embed instead of full rebuild |
| 100k+ users | Shard/search-tier with pgvector (llm-zc `ingest_pgvector.py` exists as the ready-made pattern) for ANN on large corpora; LLM gateway with queue for summarization; Laravel queue for exports |

### Scaling Priorities

1. **First bottleneck: LLM latency/cost.** Each message = retrieval + a full prompt. Fix with streaming (perceived latency), history trimming (cost), and caching identical questions (optional, later).
2. **Second bottleneck: index rebuild blocking searches.** If the corpus grows, swap-with-rename (atomic) keeps serving the old index while the new builds; embeddings for unchanged docs can be reused via a doc-hash check (rag-search-engine already guards by document count — extend to content hash).
3. **Third bottleneck: export fan-out.** Model-event jobs per edit could pile up; the debounce job (unique-per-entity, delayed 30s) is the cheap fix.

## Anti-Patterns

### Anti-Pattern 1: Sidecar Reading the Laravel Database Directly

**What people do:** give the Python service a PostgreSQL connection string and let it query `academic_papers` + joins.

**Why it's wrong:** the sidecar duplicates Eloquent's join logic (authors pivot, advisers, deans), silently breaks on migrations, and creates an unmanaged second data consumer. It also violates the clean separation both references demonstrate (both ingest *from exported/data files*, never from the production DB).

**Do this instead:** Laravel exports JSON; sidecar ingests JSON. One-way data flow, one schema owner.

### Anti-Pattern 2: Duplicating Conversation History in the Sidecar

**What people do:** store sessions in sidecar SQLite "to avoid resending history."

**Why it's wrong:** two sources of truth for what the user said; sidecar storage won't respect Laravel roles/retention (student vs admin visibility); rebuilds and redeploys orphan history. llm-zc avoids this: the RAG pipeline is stateless and the frontend app (`db_save.py`) persists conversations.

**Do this instead:** Laravel tables (`ai_conversations`, `ai_messages`), sidecar gets `session_id` + trimmed history per request.

### Anti-Pattern 3: Exposing the Sidecar Publicly and Re-Authorizing in Python

**What people do:** route FastAPI on a public port and pass the user's role to the sidecar to decide what content to return.

**Why it's wrong:** access control must stay where Gates and roles live (Laravel); a public sidecar with an LLM prompt is a prompt-injection/abuse surface. The existing app already centralizes authorization in `AppServiceProvider` Gates + middleware (`LibrarianOrAdmin`, `AdminOnly`).

**Do this instead:** sidecar bound to loopback (or private network) with `X-Sidecar-Token`; Laravel enforces Gates before calling it; the sidecar treats incoming requests as pre-authorized.

### Anti-Pattern 4: Streaming from Laravel by Polling the Sidecar

**What people do:** Livewire `wire:poll` every second asking "is the answer ready?"

**Why it's wrong:** 1s polling with 5–10s LLM latency produces churn, races, and ugly partial updates; Livewire 4 has first-class streaming (`wire:stream`) and the sidecar can emit SSE natively.

**Do this instead:** v1 non-streaming with spinner; v2 SSE from sidecar, consumed by Alpine `EventSource`, finalized via a Livewire action.

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| LLM API (OpenRouter / OpenAI-compatible) | OpenAI SDK from sidecar, `base_url` configurable, model via env | rag-search-engine uses `OpenAI(base_url="https://openrouter.ai/api/v1")` + `chat.completions`; llm-zc `main.py` shows provider detection (key prefix) and a Groq compat patch — abstract the client behind env-driven factory from day one |
| Embedding model (all-MiniLM-L6-v2) | SentenceTransformer, downloaded on first build into HF cache; pin via `Dockerfile` layer + volume | rag-search-engine `semantic_search.py` default model; ~90MB download — document cold start |
| Grafana + Prometheus | Docker compose, provisioning-as-code (`grafana/provisioning/`, dashboard JSON) | Copy llm-zc `docker-compose.yml` + `grafana/` layout; Grafana reads Prometheus + monitoring Postgres |
| Monitoring Postgres | Per-call records (time, tokens, cost, feedback) | llm-zc `docker-compose.yml` `postgres` service; adapt `db_save.py`/`db_feedback.py` inserts |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| Livewire widget ↔ sidecar | **Never direct** — always through `AiService` | Keeps the Http/token/retry logic in one testable place; widget tests mock the service |
| Laravel ↔ sidecar | HTTP/JSON + SSE (later); loopback `127.0.0.1:8xxx`; `X-Sidecar-Token` shared secret | Laravel `Http` facade with `timeout(90)`, `retry(2)` for idempotent GETs only — never retry `/chat` |
| Laravel scheduler ↔ sidecar | `POST /index/rebuild` after `ai:export-corpus` | Scheduled (hourly) in `routes/console.php` + event-driven debounced job on model changes |
| Sidecar ↔ Laravel DB | None (by design) | Availability/copy status is fetched live by Laravel at render time, not indexed |
| Sidecar ↔ monitoring store | Metrics push (Prometheus client) + per-call inserts | Independent of Laravel; survives Laravel downtime for debugging |

### New Components vs Modified Existing (explicit list)

**New (Laravel):** `AiService`, `AiConversation`/`AiMessage` models + migrations, `ExportAiCorpus` command, `SyncAiIndex` command, `ChatWidget` Livewire component + Blade view, `config/services.php` entries (`ai_sidecar.base_url`, `ai_sidecar.token`).

**New (Python sidecar):** FastAPI app, ported `hybrid_search.py`/`semantic_search.py`/`keyword_search.py` (InvertedIndex + chunk embeddings + RRF), `rag.py` (RAGBase-style one-shot + the four prompt modes from `augmented_generation_cli.py`), `metrics.py` (RAGWithMetrics port), eval runner, Dockerfile + compose + Grafana provisioning.

**Modified (existing app):** `routes/console.php` (schedule the export/sync), `.env` + `config/services.php` (sidecar config). Nothing else in the existing app changes — the widget is additive, the catalog models are read-only sources for the exporter.

## Suggested Build Order (walking skeleton first)

1. **Corpus export + golden dataset** (Laravel only, zero sidecar): `ai:export-corpus` writes `storage/app/ai-corpus/{catalog,policies}.json`; hand-craft ~10 golden test cases in rag-search-engine's `{"test_cases": [{"query", "relevant_docs"}]}` format against real catalog rows. *Unblocks every later verification step.*
2. **Sidecar skeleton**: FastAPI `/health` + port `HybridSearch` (BM25 + semantic + RRF) over the exported corpus + `/search` endpoint. Verify with golden-dataset retrieval metrics (hit rate, MRR) — this is the port of `evaluation_cli.py`.
3. **Non-streaming generation**: `/chat`, `/summarize`, `/citations` with the four prompt templates from `augmented_generation_cli.py`; RAGWithMetrics wrapper capturing time/tokens/cost.
4. **Laravel integration**: `AiService` (Http + token), migrations for conversations/messages, `ChatWidget` (non-streaming, spinner), role-aware mounting via existing Gates (`@can('privileged-access')` for librarian-only answers, widget visible to all authenticated roles).
5. **Feedback + observability**: thumbs up/down → `ai_messages` feedback columns; per-call records → monitoring Postgres; compose stack (sidecar + Prometheus + Grafana) with provisioned dashboards.
6. **Refresh automation**: scheduled + debounced event-driven `ai:sync-index`.
7. **Streaming + agentic + judge**: SSE streaming, optional `rag_agent` tool-calling loop, LLM-as-judge eval pipeline feeding the golden dataset.

Dependency rationale: each step consumes the previous one's output and its verification data (golden metrics before touching prompts; prompts before wiring UI; UI before feedback instrumentation).

## Sources

- `rag-search-engine` (WSL `~/workspace/rag-search-engine`): `cli/lib/hybrid_search.py` (weighted + RRF fusion), `cli/lib/semantic_search.py` (chunk embeddings, cache paths), `cli/augmented_generation_cli.py` (four RAG modes, OpenRouter via OpenAI SDK, citation format `[1]`), `cache/` layout, `data/golden_dataset.json` shape
- `llm-zc` (D:\ai-eng\llm-zc): `rag_helper.py` (RAGBase one-shot + agentic `rag_agent`, INSTRUCTIONS/PROMPT_TEMPLATE), `main.py` (provider detection, persistent index open-vs-build), `db_save.py`/`db_feedback.py` (conversation + ±1 feedback storage), `metrics.py` (RAGWithMetrics), `app.py` (chat UI flow), `judge.py` (LLM-as-judge), `ingest_pgvector.py` (pgvector variant), `docker-compose.yml` + `grafana/provisioning/` (Grafana-as-code), `03-orchestration` YAML flows
- CEIT-Library: `docs/codebase/ARCHITECTURE.md`, `docs/codebase/STACK.md`, `.env` (pgsql, `librarydb` @ 127.0.0.1:5432), `composer.json` (Livewire ^4, Volt ^1.7, Mary ^2), `app/Models/AcademicPaper.php` (catalog + `available_copies_count`), `app/Models/Inventory.php` (copy status), `app/Models/BorrowTransaction.php` (session lifecycle), `app/Models/RuleHeader.php`/`RuleRegulation.php` (policy corpus), `app/Providers/AppServiceProvider.php` (Gates), `bootstrap/app.php` (middleware), `routes/console.php` (existing scheduled commands), `documentations/LIBRARIAN_BATCH_SYSTEM.md`

---
*Architecture research for: CEIT-Library v2.0 AI Assistant (Python RAG sidecar integration)*
*Researched: 2026-08-13*
