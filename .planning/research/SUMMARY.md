# Project Research Summary

**Project:** CEIT-Library v2.0 — AI Assistant (AI librarian RAG agent)
**Domain:** RAG sidecar + LLM integration for an academic library management system
**Researched:** 2026-08-13
**Confidence:** HIGH

## Executive Summary

CEIT-Library adds an AI librarian as a **Python FastAPI sidecar** next to the existing Laravel 13 / Livewire 4 / PostgreSQL app. The sidecar owns retrieval and generation only: hybrid BM25+semantic search fused with RRF (k=60) over a persisted SQLite index (`sqlitesearch`), one-shot RAG chat with numbered citations and strict grounding rules, and local `all-MiniLM-L6-v2` embeddings (CPU-only). Laravel remains the only stateful participant and the only DB owner: it exports the corpus (catalog, papers, policies) as JSON, persists conversations/feedback, enforces Gates, and resolves live availability.

The recommended approach is a **walking skeleton with evaluation from day one**: export + golden dataset → search verified against retrieval metrics → non-streaming chat + Livewire widget → eval/tuning → monitoring → streaming + hardening. Everything is validated by two reference codebases (`rag-search-engine`, `llm-zc`) whose proven patterns we port — and whose bugs (build-once indexes, fusion ambiguity, golden-set rot) we explicitly avoid.

The key risks are hallucinated answers and stale availability (the #1 and #2 trust killers for a library), index drift, prompt injection via librarian-editable metadata, and PII leaving the existing Laravel-log-only sanitizer perimeter. All are addressable by design choices made now: a citation gate, a static/dynamic field split (availability never indexed, always live), event-driven index sync with reconciliation, context framing of untrusted catalog text, and boundary sanitization before queries leave PHP.

## Key Findings

### Recommended Stack

Python 3.13 sidecar managed with **uv**, on **FastAPI 0.141 + Uvicorn** with Pydantic 2 models as the Laravel↔Python contract. Retrieval: `sqlitesearch` 0.3 (SQLite FTS5 for BM25 keyword + brute-force vector store — zero infra, survives restarts) with `sentence-transformers` 5.7 and `all-MiniLM-L6-v2` (384-dim, 80 MB, no GPU). LLM: **openai SDK pinned `~=2.54`** (not 3.0.0, which shipped 2026-08-12 with breaking changes) via **OpenRouter** (`base_url` override, one key for chat + judge models). Monitoring: `prometheus-client` → Prometheus 3.13 → Grafana 13.1 (provisioned as code). No new Composer dependencies — Laravel's `Http` facade + Livewire 4 cover the PHP side.

**Core technologies:**
- **Python 3.13 + uv**: matches `llm-zc` (>=3.13); 3.14 too new for torch wheels — one version satisfies both reference codebases
- **FastAPI 0.141**: async non-blocking LLM calls, native SSE for later streaming, Pydantic contract, auto OpenAPI docs, trivial `/metrics`
- **sqlitesearch 0.3 (SQLite FTS5 + vectors)**: BM25 without hand-rolled nltk porting; persistent single file; pgvector only at ~100k+ chunks
- **`all-MiniLM-L6-v2`**: proven default in both references; local = free, consistent, offline-eval-able; swap-in one-liner if recall disappoints
- **openai SDK ~=2.54 + OpenRouter**: every provider via `base_url`; avoids Groq's model-decommission churn; strong judge model (`openai/gpt-oss-120b`) separate from cheap chat model
- **Prometheus + Grafana**: live system metrics per PROJECT.md; per-call rows (tokens/cost/feedback) live in the app DB, not a separate monitoring Postgres
- **No LangChain/LlamaIndex, no vector DB, no ragas, no MLflow**: all rejected as dependency/ops debt; the ~100-line agent loop, sqlitesearch, and the ~200-line custom LLM-as-judge port cover everything

### Expected Features

**Must have (launch, P1):**
- **Hybrid book search (BM25 + semantic RRF)** — exact fields (ISBN, call number, title) via BM25, paraphrase queries via embeddings; the core "find me books about X" promise
- **Result cards with live availability** — SQL-resolved copies available/total per cited book; availability never LLM-generated, never indexed
- **Chat with numbered citations + grounding rules** — `[N]` markers mapped to real retrieved doc IDs; "I don't have enough information" as first-class behavior; off-topic refusal
- **Library policy Q&A over the rulebook** — `RuleHeader`/`RuleRegulation` corpus (DB-derived FAQ shape), question-field boosts, re-index on rule change
- **Livewire chat widget** — role-aware (existing Gates), spinner + typing indicator, persisted history, per-user rate limit + cost guard, graceful degradation
- **Retrieval evaluation + feedback** — golden set with precision/recall/F1@k in CI; thumbs up/down per answer

**Should have (P2, after validation):**
- **Academic paper search** — distinct corpus (`AcademicPaper` + authors + advisers), paper-specific chunking, citation-style cards, no availability
- **Summarization** — only of retrieved content (single paper, policy topic, result set); never arbitrary pasted text
- **Agentic multi-search** — ship only if eval proves trajectory+answer scores beat one-shot; cap iterations
- **Grafana dashboards** — usage exists to visualize; cost alerts

**Defer (v2+):**
- Librarian-only copy-level tools (PII-scoped role work), follow-up chips, multi-agent orchestration, voice/multilingual persona features
- Open web search (permanent anti-feature: retrieval stays DB-only — catalog, papers, policy)

### Architecture Approach

One-way data flow with two network rules: **Laravel → sidecar** over loopback with a shared token (sidecar never publicly exposed), and **sidecar → Laravel DB never happens**. Laravel exports the corpus (JSON via `ai:export-corpus`), the sidecar ingests it into the persisted index, and all chat state (conversations, messages, feedback) lives in Laravel's Postgres. The sidecar is stateless except its rebuildable index cache; chat requests carry `session_id` + trimmed history. Retrieval is one-shot first; the agentic tool-calling loop is a post-eval upgrade behind the same `/chat` endpoint.

**Major components:**
1. **`AiService` (Laravel)** — single gateway to the sidecar: token, timeouts, retries (idempotent GETs only), error mapping, contract-version check
2. **`ai:export-corpus` artisan command + `ai:sync-index`** — serializes `Inventory`/titles/authors/descriptions, `AcademicPaper`+authors+advisers, `RuleHeader`/`RuleRegulation`; scheduled hourly + event-driven debounced sync + nightly count reconciliation
3. **FastAPI sidecar (`ai-sidecar/`)** — ported `HybridSearch` (BM25 + semantic + RRF k=60), ingest with pre-embedding persisted to disk, one-shot `rag()` with the four prompt modes (rag/summarize/citations/question), `RAGWithMetrics` port, `/health` + `/metrics`, golden-dataset eval runner
4. **Livewire `ChatWidget`** — Mary UI chat bubbles, spinner (non-streaming v1), citations with clickable catalog links, thumbs up/down, role-aware mount via Gates
5. **Observability** — Prometheus scrapes sidecar `/metrics`; Grafana reads Prometheus + the app DB feedback/conversation tables; no separate monitoring Postgres

### Conflict Resolutions

The four research files disagreed on several points; resolved as follows:

1. **Database engine**: PITFALLS.md says "MySQL" throughout; STACK.md and ARCHITECTURE.md verified `.env` (`DB_CONNECTION=pgsql`, `librarydb @ 127.0.0.1:5432`) and Aiven CA handling in `Docker/start.sh`. **Resolution:** the app DB is PostgreSQL (Aiven-managed); every PITFALLS "MySQL" reference applies to that Postgres.
2. **Laravel version**: brief said 12; both STACK and ARCHITECTURE cite verified `composer.json` (`laravel/framework ^13.0`, PHP ^8.4). **Resolution:** Laravel 13 / PHP 8.4.
3. **Sidecar ↔ DB data flow**: ARCHITECTURE.md says the sidecar never touches the DB (Laravel exports JSON, Laravel resolves availability); PITFALLS.md Pitfalls 2/3 suggest the sidecar reads the DB read-only at ingest/answer time to avoid drift and stale availability. **Resolution:** keep the one-way flow (Laravel is the sole DB owner — cleaner security, PITFALLS Pitfall 12's own least-privilege stance); the drift/staleness risks PITFALLS was solving are covered by event-driven sync + nightly reconciliation (both files propose these) and by live availability resolved Laravel-side at answer time, injected into the response template — the model never decides availability.
4. **Index persistence**: STACK.md chose `sqlitesearch` (SQLite FTS5 + vector store); ARCHITECTURE.md's tree shows the rag-search-engine pickle/`.npy` cache. **Resolution:** sqlitesearch is the persistent store; the pickle/`.npy` sketch is the port source illustration. Atomic swap-on-rebuild applies either way.
5. **Python version**: STACK.md says 3.13 (llm-zc floor); ARCHITECTURE.md's Dockerfile sketch says 3.12-slim (rag-search-engine floor). **Resolution:** Python 3.13 — satisfies both references, torch-compatible.
6. **Index sync timing**: ARCHITECTURE.md defers refresh automation to step 6 of its build order; PITFALLS Pitfall 3 says update/delete paths must be in the ingestion design from day one or the retrofit is a rewrite. **Resolution:** sync (event-driven + scheduled + reconciliation) lands in P1 with the sidecar skeleton, not later.
7. **Streaming**: FEATURES.md lists streaming as table stakes; ARCHITECTURE.md and PITFALLS recommend non-streaming v1 (it spans three layers). **Resolution:** widget ships with spinner + typing indicator at launch; SSE streaming is a P6 fast-follow (config choice, not an architecture change).
8. **LLM provider**: ARCHITECTURE.md says "provider TBD" (PROJECT.md); STACK.md recommends OpenRouter. **Resolution:** env-driven client factory from day one (no hard-coding, per PITFALLS tech-debt table), OpenRouter as the configured default.
9. **Monitoring store**: ARCHITECTURE.md sketches a dedicated monitoring Postgres (llm-zc pattern); PITFALLS Pitfall 10 calls duplicating DB infra in a managed-DB environment a mistake; STACK.md says keep the Postgres datasource if feedback tables live in the app DB. **Resolution:** no separate monitoring Postgres — per-call/feedback rows live in the app DB (Laravel-owned), Prometheus carries live system metrics, Grafana reads both.
10. **Role enforcement**: ARCHITECTURE.md treats the sidecar's requests as pre-authorized (Gates in Laravel); PITFALLS Pitfall 12 demands server-side collection×role enforcement in the sidecar. **Resolution:** both — Laravel gates the UI/endpoint, and the sidecar enforces a collection×role allowlist from role claims carried in each request (defense in depth; the loopback-only rule still stands).
11. **Policy Q&A phase**: FEATURES.md rates it P1 (table stakes, corpus tables already exist); PITFALLS.md's canonical P3 is named "Papers & Policy FAQ". **Resolution:** policy Q&A ships with chat (P2 in the canonical P1–P6 framework); papers corpus remains P3.
12. **Agentic loop**: all three files agree one-shot first; agentic is evaluated in P4 and ships only if it wins. No conflict.

### Critical Pitfalls

1. **Ghost books (hallucinated answers)** — the LLM answers from parametric memory when retrieval is weak; fluent wrong answers are worse than a broken search box. *Avoid:* grounding instructions copied from `llm-zc` INSTRUCTIONS, a citation gate (no claim about a book without a retrieved doc ID), structured output schemas, negative golden cases where the correct answer is "I don't know".
2. **Availability lies** — indexed `status=available` snapshots go stale within hours. *Avoid:* static/dynamic field split; availability never embedded, always resolved live at answer time and prefixed with a check timestamp ("checked at 14:02"); the model repeats a value it is given, never decides one.
3. **Index drift** — both reference codebases have build-once indexes (no update/delete path); deleted books would stay searchable forever. *Avoid:* event-driven sync on model events, delete = hard removal, nightly count reconciliation + `index_version`/`last_synced_at` health, designed into P1 — never retrofitted.
4. **Prompt injection via catalog metadata** — librarian-editable titles/descriptions/policy text become instructions once in the prompt. *Avoid:* frame every retrieved record as DATA with delimiters and field labels, strip control chars, plain-text citations (no live links from metadata), red-team cases in the eval set.
5. **PII escaping the PiiSanitizer perimeter** — the existing sanitizer only covers Laravel logs; the AI path adds sidecar logs, provider receipts, chat history, and metrics. *Avoid:* sanitize at the Livewire boundary before anything leaves PHP, opaque `user_ref` (never user identity to the provider), retention policy, no user identifiers as metric labels, no full-prompt logging.

(Rounding out the top-13: fusion-weighting confusion → default RRF with dominance unit tests; chunking micro-documents → one doc per book record with weighted field concatenation, chunk only papers; unbounded cost → per-user budgets, history windowing, capped iterations; slow first answers → pre-embed at ingest, warm on deploy, health-driven UI state; eval traps → golden-set versioning, held-out split, judge ≠ answer model, human spot-audit; sidecar contract drift → versioned OpenAPI + contract tests in CI + single release pipeline; role leakage → signed role claims + sidecar allowlist; SSRF via web search → retrieval stays DB-only, recorded as a deliberate scope boundary.)

## Implications for Roadmap

### Phase 1: Index & Sidecar Foundation
**Rationale:** the corpus export contract is the P0 dependency for everything else; retrieval quality must be measurable before any prompt/UI work; sync design must exist from day one (Pitfall 3).
**Delivers:** `ai:export-corpus` (catalog + policies JSON), sidecar skeleton (FastAPI + sqlitesearch + hybrid search RRF k=60), pre-embedding persisted at ingest, event-driven + scheduled sync with atomic rebuild + nightly reconciliation, `/health` with index coverage, fusion dominance unit tests, retrieval eval harness (hit rate/MRR) against a bootstrap golden set.
**Addresses:** hybrid search, policy corpus export (FEATURES P1).
**Avoids:** index drift (P3), fusion confusion (P4), slow first answers (P8), chunking metadata (P5 — book doc schema with field boosts).

### Phase 2: RAG & Chat
**Rationale:** one-shot `/chat` is the proven reference path; every prompt/UX decision (grounding, citations, availability, PII boundary) is cheapest to make correctly in the phase that introduces the model.
**Delivers:** one-shot `/chat` with citations + grounding + citation gate, policy Q&A over `RuleHeader`/`RuleRegulation`, live availability enrichment (Laravel-side, template-injected), `AiService` + `ai_conversations`/`ai_messages` migrations, Livewire `ChatWidget` (spinner, role-aware, thumbs up/down, rate limit, cost guard, graceful degradation), boundary PII sanitization, context framing, sidecar role allowlist.
**Uses:** openai SDK ~=2.54 via OpenRouter, prompt templates ported from `augmented_generation_cli.py`, `RAGWithMetrics` wrapper.
**Implements:** Patterns 1–4 (export pipeline, stateless sidecar, one-shot RAG, non-streaming chat).
**Avoids:** ghost books (P1), availability lies (P2), PII escape (P6), unbounded cost (P7), prompt injection (P11), role leakage (P12).

### Phase 3: Papers & Summarization
**Rationale:** papers need a different chunker and card layout than books (no availability, author/year/adviser filters); FEATURES defers it until catalog search is validated.
**Delivers:** paper corpus export (`AcademicPaper` + authors + advisers), paper-specific chunking with passage-level citations, paper search cards, summarization of retrieved content (paper / policy topic / result set).
**Avoids:** chunking short metadata (P5 — papers chunked with token caps, evaluated separately), summarization of non-retrieved text.

### Phase 4: Evaluation & Tuning
**Rationale:** the reference eval lessons show eval done wrong silently passes — versioning, splits, and judge hygiene must be in the plan from the first eval run.
**Delivers:** golden-set versioning against catalog snapshots, train/held-out split, LLM-as-judge (structured verdicts, retries, human spot-audit of 10%), tuning of RRF k / boosts / pool size on the train split only, red-team injection cases, real anonymized query seeding, agentic multi-search evaluation (ships only if it beats one-shot on trajectory + answer scores).
**Uses:** custom judge port (~200 LOC, Pydantic verdicts), pandas eval frames.
**Avoids:** eval traps (P9 — golden-set rot, judge bias, overfitting), agentic cost blow-up (P7).

### Phase 5: Monitoring & Feedback
**Rationale:** usage must exist before dashboards have signal; the feedback→judge→golden-set loop feeds Phase 4.
**Delivers:** Prometheus `/metrics` on the sidecar (latency, tokens, cost, verdict counters — no user identifiers as labels), Grafana provisioned as code reading Prometheus + app DB, cost alerts, offline queued judge pipeline, retention cleanup.
**Avoids:** PII in metrics/labels (P6), unbounded cost (P7 — alerts), duplicated monitoring infra (P10).

### Phase 6: Deployment & Hardening
**Rationale:** contract versioning and a single pipeline must exist before two services drift; streaming is a config change worth its own phase.
**Delivers:** SSE streaming (sidecar → Livewire stream) with typing indicator, versioned OpenAPI contract + contract tests on both sides in CI, single release pipeline (pinned sidecar image + model version), deploy warm-up step, least-privilege DB grants, retention policies, Laravel Cloud orchestration check for the sidecar.
**Avoids:** contract drift (P10), slow first answers at scale (P8 warm-up), role leakage via DB grants (P12).

### Phase Ordering Rationale

- Each phase consumes the previous one's output and verification data: golden metrics before prompts, prompts before UI, UI before feedback instrumentation, usage before dashboards — the llm-zc lesson path (chat → metrics → feedback → judge) plus eval-first retrieval.
- Sync automation is pulled into P1 (Pitfall 3 explicitly says retrofitting is a rewrite), overriding ARCHITECTURE's step-6 placement.
- Agentic search and streaming are deliberately late: both are evaluated/UX upgrades, not architecture changes, and both multiply cost/failure surface if shipped before validation.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 1:** `sqlitesearch` 0.3 API surface (port from llm-zc, not rag-search-engine's hand-rolled nltk); CPU benchmark of `all-MiniLM-L6-v2` query-embedding latency on the target host.
- **Phase 2:** Livewire 4 streaming/component patterns for the chat widget (existing components are form/table-based); Mary UI chat-bubble styling specifics.
- **Phase 6:** SSE passthrough specifics (FastAPI `StreamingResponse` → Laravel proxy → Livewire 4 stream); Laravel Cloud provisioning of a second (Python) service.

Phases with standard patterns (skip research-phase):
- **Phase 5:** Prometheus/Grafana provisioning is a copied, proven pattern (`llm-zc/grafana`); `prometheus-client` `/metrics` mount is trivial.
- **Phase 4:** the eval harness is a direct port of two validated implementations.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | PyPI/GitHub/Docker Hub versions verified live 2026-08-13; both reference codebases run the same combos |
| Features | HIGH | Every feature traced to reference implementations or existing CEIT-Library tables/patterns |
| Architecture | HIGH | Integration patterns tied to existing app structure (Gates, artisan commands, service layer) |
| Pitfalls | HIGH | Pitfalls grounded in reference codebase bugs (build-once index, debug_hybrid.py, golden-set rot) + CEIT-Library's own code |

**Overall confidence:** HIGH

### Gaps to Address

- **LLM provider budget/contract**: which OpenRouter models for chat vs. judge, and cost caps — resolve via env config in P2 planning; provider-agnostic factory means no rework.
- **Taglish/Filipino query quality**: `all-MiniLM-L6-v2` is English-centric; multilingual answers are deferred as a free model capability, but P4 must include a multilingual golden-set slice to decide if a multilingual embedding model is needed.
- **Laravel Cloud sidecar hosting**: whether the Python service runs as a managed service, VPS, or alongside the app — a P6 planning decision; the loopback-token design keeps it deployment-agnostic.
- **Exact OpenAI SDK pin**: `~=2.54` confirmed against both references; re-verify 3.0 compatibility only after the milestone.

## Sources

### Primary (HIGH confidence)
- `.planning/research/STACK.md`, `FEATURES.md`, `ARCHITECTURE.md`, `PITFALLS.md` (this project, 2026-08-13)
- PyPI JSON API, GitHub Releases API, Docker Hub, python.org — live version verification (fastapi 0.141.1, uvicorn 0.52.2, sentence-transformers 5.7.0, openai 2.54.0/3.0.0, sqlitesearch 0.3.0, prometheus-client 0.26.0, psycopg 3.3.4, Grafana 13.1.3, Prometheus 3.13.2)
- CEIT-Library repo: `composer.json` (Laravel ^13.0, PHP ^8.4, Livewire ^4.0, Octane ^2.17), `.env` (pgsql, Aiven), `Docker/start.sh`, `app/Models/*`, `app/Providers/AppServiceProvider.php`, `app/Logging/PiiSanitizerProcessor.php`, `routes/console.php`, `docs/codebase/*`, `.planning/PROJECT.md`

### Secondary (MEDIUM confidence)
- `rag-search-engine` (WSL `~/workspace/rag-search-engine`) — hybrid search/RRF port source, golden-dataset shape, citation prompts, known bugs (build-once index, alpha ambiguity, min-max anchoring)
- `llm-zc` (D:\ai-eng\llm-zc) — grounding INSTRUCTIONS, sqlitesearch ingestion, RAGWithMetrics, judge.py, db_feedback.py, Grafana provisioning, eval lessons (ground truth, tuning, judge bias)

### Tertiary (LOW confidence)
- Community LLM-zoomcamp evaluation/monitoring lessons (linked from llm-zc files) — corroborating eval methodology; validated via the reference implementations

---
*Research completed: 2026-08-13*
*Ready for roadmap: yes*
