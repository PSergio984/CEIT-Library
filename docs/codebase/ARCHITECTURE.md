# Architecture

## Core Sections (Required)

### 1) Architectural Style

- Primary style: Layered (presentation → services → models), with an event-driven sync path (Eloquent observers → queued jobs) toward an external search sidecar.
- Why this classification: `app/Services/` owns domain orchestration; Livewire components are thin; model `booted` events and `app/Observers/` drive side effects; the AI pipeline is an external-service call chain.
- Primary constraints:
  1. The sidecar is a separate repo/service owning all search + LLM calls; Laravel never queries the search index directly and never holds the LLM key (ADR 0001, `SIDECAR_URL`/`SIDECAR_TOKEN` in `.env.example`).
  2. Grounding: assistant answers must come only from retrieved corpus content; empty retrieval → canonical refusal (ADR 0006, enforced sidecar-side in `rag.py`/`agent.py`, echoed in `AiService`).
  3. Availability/copy counts are always hydrated from the database (`AvailabilityService`), never from the LLM or sidecar (CONTEXT.md "Availability").

### 2) System Flow

```text
browser -> Livewire component -> Service -> Eloquent/sqlite        (app flows)
                                          |
LLM chat: Livewire widget -> AiService (SSE HTTP) -> sidecar /chat/stream
          -> AgenticLoop (tool loop) -> HybridSearch -> OpenRouter LLM
          -> SSE chunks {"c": delta} -> Livewire stream()
Corpus sync: DB -> ai:export-corpus -> storage/app/ai-corpus/*.json
          -> (local) sidecar /index/rebuild | (cloud) ai:push-corpus -> /corpus/upload -> auto-rebuild
          (versioned atomic swap) -> /search, /chat/stream
Change path: model observer -> AiIndexRebuildJob (60s delay) / ImmediateJob -> sidecar rebuild
```

Flow in steps (AI chat):
1. User opens the chat widget (every authenticated page, ADR 0008); a `Message` row is created (Conversation/Message schema, ADR 0005).
2. `AiService::chatStream()` POSTs `{query, mode, corpus, top_k}` to the sidecar `/chat/stream` with `X-Sidecar-Token` (ADR 0004).
3. Sidecar `AgenticLoop` decides one-shot vs agentic (tool call in first LLM round = auto-detect, ADR 0014), runs bounded `HybridSearch` rounds (BM25 + semantic + RRF), then streams the grounded answer with `event: citations` / `event: activity` frames (ADR 0002/0006).
4. `AiService::chatStreamEvents()` parses `data: {"c": ...}` lines and Livewire `stream()`s them to the widget; `[DONE]` terminates; truncation/mid-stream drop throws (fail-closed).
5. Answer + citations persist to the `Message`; citation keys validated against `AiService::CITATION_KEYS` (mirrors sidecar `rag.CITATION_KEYS`).

Flow in steps (search + availability):
1. `HybridSearch`-backed search page calls the sidecar `/search` through `AiService::search()` (filters: paper_type/department/year/author/adviser; corpus catalog|policy).
2. Results are enriched with live copy counts by `AvailabilityService` (grouped `inventory` query + `checked_at`), and `SimilarPapersService` produces the "similar books" list by running the paper title as a query (ADR 0011, self-excluded by id).

### 3) Layer/Module Responsibilities

| Layer or module | Owns | Must not own | Evidence |
|-----------------|------|--------------|----------|
| Livewire components (`app/Livewire/`) | UI state, validation (`#[Validate]`), user interactions | Business rules, sidecar HTTP | `app/Livewire/Pages/Admin/AdminAssignLibrarians.php` |
| Services (`app/Services/`) | Domain orchestration: borrow/return, attendance, librarian batch status sync, availability hydration, similar books, AI SSE client, corpus export, notifications | SQL schema, view rendering | `app/Services/*.php` |
| Models (`app/Models/`) | Eloquent definitions, relations, casts, booted event hooks | External HTTP/SSE | `app/Models/*.php` |
| Observers + Jobs | Index rebuild triggers on paper/rulebook/people changes | Search ranking | `app/Observers/`, `app/Jobs/AiIndexRebuildJob.php` |
| Console commands | Scheduled automation (export at :05, sync at :10, reconcile 02:00, overdue checks, batch statuses) | User-request paths | `routes/console.php`, `app/Console/Commands/` |
| Middleware (`app/Http/Middleware/`) | Auth/role/account-status gates | Domain decisions | `app/Http/Middleware/AdminOnly.php` |
| Sidecar (external) | Search index, BM25+semantic fusion, RRF, LLM calls, SSE streaming | Laravel DB access (never — files only, sidecar D-17) | sidecar repo `app/search.py`, `app/ingest.py` |

### 4) Reused Patterns

| Pattern | Where found | Why it exists |
|---------|-------------|---------------|
| Service classes for domain logic | `app/Services/` | Decouple Livewire components from business rules (v1.2 refactor) |
| Eloquent observers → queued jobs | `app/Observers/`, `app/Jobs/` | Event-driven index sync without blocking the request |
| `ShouldBeUnique` queued rebuilds | `AiIndexRebuildJob` | Debounce repeated edits (single pending rebuild) |
| Shared contract constants between repos | `AiService::CITATION_KEYS` / `SSE_CHUNK_KEY` vs sidecar `CITATION_KEYS` / `CHUNK_KEY` | Prevent contract drift between Laravel and sidecar |
| Livewire `stream()` for SSE | chat widget | Native streaming without a separate SSE route (ADR 0002) |
| PiiSanitizerProcessor on log channels | `app/Logging/PiiSanitizerProcessor.php` | Global PII redaction (v1.3) |

### 5) Known Architectural Risks

- The sidecar is a single loopback instance; AI features degrade if it is down (AiService logs failures; no fallback search path). [TODO Phase 14 deployment]
- Search freshness depends on scheduler + queue worker running (observer → 60s-delayed job; hourly export/sync; nightly reconcile). If the queue isn't processed, search goes stale.
- Whole-document embeddings with 600-char prompt truncation (sidecar `MAX_DOC_CHARS`) can lose detail for long documents. [TODO]
- No rate limiting / cost guards on the LLM path yet (Phase 14 requirement OPS-02). [TODO]

### 6) Evidence

- `app/Services/AiService.php`, `app/Services/SimilarPapersService.php`, `app/Services/AvailabilityService.php`
- `routes/console.php` (schedule), `app/Observers/AcademicPaperObserver.php`, `app/Jobs/AiIndexRebuildJob.php`
- `docs/adr/0001..0014` (decision records — see index below)

## Decision Record Index (docs/adr/)

| ADR | Decision (gist) |
|-----|-----------------|
| 0001 | LLM provider: OpenRouter via the openai SDK (sidecar holds the key) |
| 0002 | Chat streaming: Livewire native `stream()`, not Reverb/raw SSE route |
| 0003 | RAG prompt modes: port `rag` + `citations` + `question`; skip `summarize` |
| 0004 | Sidecar chat endpoint contract: single streamed endpoint, three-code errors |
| 0005 | Conversation history schema: two tables, single owner, viewability-only |
| 0006 | Citation and grounding rules: companion search for sources, empty-retrieval refusal |
| 0007 | Minimal per-user chat guard: existing auth middleware, no new machinery |
| 0008 | Chat widget shape: floating launcher + drawer, on every authenticated page |
| 0009 | Conversation-list UI flow: drawer view-switch, lazy creation, title + relative time |
| 0010 | Live availability hydration: grouped query, never the LLM |
| 0011 | Similar-books mechanism: title-as-query through deterministic search |
| 0012 | Similar-button UX: replace-with-back recommendations mode on the search page |
| 0013 | Academic paper corpus shape and topic/author/year/adviser search |
| 0014 | Agentic search loop contract (function-calling over /search, capped rounds) |

## Extended Sections (Optional)

### Startup / scheduling order

Scheduler (`routes/console.php`): missing-timeout violations 00:30 daily; overdue marking + deadline warnings every 5 min; librarian batch statuses + roles hourly; librarian check-assignments 09:00 daily; borrow reminders 08:00 daily; `ai:export-corpus` hourly at :05; `ai:sync-index` hourly at :10; `ai:reconcile-index` 02:00 daily.
