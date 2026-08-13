# Stack Research

**Domain:** AI Assistant (v2.0) — Python RAG sidecar + LLM integration for a Laravel academic library system
**Researched:** 2026-08-13
**Confidence:** HIGH

Scope: the new AI-librarian subsystem (Python sidecar service, retrieval, LLM calls, evaluation, monitoring) and its integration with the existing Laravel 13 / Livewire 4 / PostgreSQL app. Existing validated stack (`docs/codebase/STACK.md`, `composer.json`) is NOT re-researched here.

---

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Python | 3.13 | Sidecar runtime | Matches `llm-zc` (`>=3.13`); `rag-search-engine` uses `>=3.12`. Do NOT use 3.14 yet — torch/sentence-transformers wheels lag new CPython releases. |
| uv | latest | Package/env manager | Both reference codebases are uv-managed (`pyproject.toml` + `uv.lock`). Deterministic, fast, replaces pip+venv. |
| FastAPI | 0.141.x | HTTP service framework for the sidecar | Already committed in PROJECT.md. Native async (non-blocking LLM HTTP calls), first-class SSE/`StreamingResponse` for chat token streaming into Livewire, Pydantic 2 models shared with the eval harness, auto OpenAPI docs (serves as the Laravel↔Python contract), trivial `/metrics` endpoint for Prometheus. |
| Uvicorn | 0.52.x | ASGI server | FastAPI's reference server; single-process deployment is plenty at this scale. |
| sentence-transformers | 5.7.x | Local embeddings | Both reference codebases embed with it (same `all-MiniLM-L6-v2` model). See Embeddings row for model choice. |
| `all-MiniLM-L6-v2` | (model) | 384-dim embedding model | The proven default in BOTH reference codebases (`embedder.py` in llm-zc, `ChunkedSemanticSearch` in rag-search-engine). 80 MB, fast on CPU, fine for a catalog of thousands of books + hundreds of papers. No GPU needed. |
| openai (SDK) | 2.54.x (pin `~=2.54`) | LLM API client | One SDK drives every provider via `base_url` override — OpenRouter in rag-search-engine, Groq + OpenAI in llm-zc. `3.0.0` shipped 2026-08-12 (1 day before this research) with breaking changes; both reference codebases target 2.x (2.44/2.45), so pin 2.54.x and revisit 3.0 later. |
| OpenRouter | API (no lib) | LLM provider | `rag-search-engine` already runs on it: `OpenAI(base_url="https://openrouter.ai/api/v1")`. One key across all models — cheap chat model, strong judge model (`openai/gpt-oss-120b`, used as the llm-zc judge), and free-tier models — without per-provider accounts. Avoids the churn llm-zc hit on Groq (decommissioned model IDs). |
| sqlitesearch | 0.3.x | Search index: SQLite FTS5 (BM25 keyword) + vector store | llm-zc's persistent-index choice: one SQLite file holds `TextSearchIndex` (FTS5, BM25-ranked keyword search) and `VectorSearchIndex` (embeddings, brute-force cosine at this scale). Zero infra, portable, survives restarts. Sync from Laravel via an `/ingest` API (see Laravel Integration). |
| nltk | 3.10.x (optional) | BM25 implementation | Only if we port rag-search-engine's custom nltk-based BM25 verbatim. Default path: let SQLite FTS5 provide BM25 ranking (llm-zc approach) and port the **RRF fusion** (k=60) + min-max weighted fusion from `cli/lib/hybrid_search.py`. |
| Pydantic | 2.13.x | Data models | FastAPI's backbone and llm-zc's structured-output mechanism (`llm_structured_retry` → `client.responses.parse()` with Pydantic models, JSON-mode fallback for providers like Groq). |
| pydantic-settings | 2.15.x | Config from env | Sidecar config (`OPENROUTER_API_KEY`, `API_KEY`, DB paths, model names). Replaces manual `python-dotenv` usage from the reference repos. |
| prometheus-client | 0.26.x | Metrics exposition | Exposes `/metrics` on the sidecar: chat latency histogram, token counts, search/embedding latency, judge verdict counter. |
| psycopg | 3.3.x (`[binary]`) | Monitoring DB driver | llm-zc's monitoring module stores every LLM call + feedback rows in Postgres (`db_init.py`, `db_save.py`, `db_feedback.py`); reuse that design. |
| Grafana | 13.1.x (docker image) | Dashboards | Provisioned-as-code like llm-zc (`grafana/provisioning/`), but with a Prometheus datasource per PROJECT.md's "Grafana + Prometheus" commitment. |
| Prometheus | 3.13.x (docker image) | Metrics scrape | Standard companion to `prometheus-client`; scrapes sidecar `/metrics`. |
| PHP side: none | — | — | Laravel 13's `Http` client + native response streaming (Livewire 4 `->stream()`) cover the app side. No Composer additions required. |

### Supporting Libraries

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| httpx | (transitive, ~0.28.x) | Async HTTP | FastAPI TestClient + openai SDK dependency. Add explicitly only for sidecar tests. |
| pytest | 8.x | Sidecar tests | rag-search-engine already has `tests/` + pytest. Test search/fusion/prompt logic without the network. |
| numpy | 2.x (transitive) | Vector math | Required by sentence-transformers; used directly for cosine similarity (rag-search-engine pattern). |
| pandas | 2.x | Eval dataframes | llm-zc's evaluation workflows load/write CSVs of ground truth, RAG answers, and judge results. Dev-only. |
| tqdm | 4.x | Ingestion progress | Progress bars during catalog re-indexing (llm-zc `ingest_vector.py` pattern). |
| CrossEncoder (`cross-encoder/ms-marco-MiniLM-L6-v2`) | via sentence-transformers | Optional rerank stage | rag-search-engine supports LLM reranking (individual/batch) AND local cross-encoder reranking. Add only if RRF retrieval quality needs a boost. |
| minsearch | 0.2.x | In-memory BM25 | llm-zc's quick-iteration index for eval experiments; not for production (no persistence). |
| ruff | latest | Lint/format | Standard for uv projects; keeps sidecar code consistent with the PHP side's Pint discipline. |
| python-dotenv | 1.x | Legacy env loading | Only if copying reference-repo code verbatim; prefer pydantic-settings in new code. |

### Development Tools

| Tool | Purpose | Notes |
|------|---------|-------|
| Docker Compose | Run sidecar + monitoring | New `docker-compose.yml` at repo root (none exists yet): `sidecar` (uvicorn), `prometheus`, `grafana`. Follows llm-zc's pattern but with Prometheus as the metrics datasource. |
| Grafana provisioning | Dashboards as code | Port llm-zc's `provisioning/datasources/datasource.yml` + `dashboards.yml` shape; swap Postgres datasource for Prometheus, keep Postgres datasource if the conversations/feedback tables live in the app DB. |
| Laravel Boost | PHP-side dev tooling | Already installed; extend boost config when adding the Livewire chat widget. |
| uv + ruff + pytest | Sidecar quality gates | CI job mirroring the existing PHP CI. |

---

## Installation

```bash
# Sidecar project (e.g. ai-service/)
uv init ai-service --python 3.13
cd ai-service

# Core
uv add "fastapi>=0.141,<0.142" "uvicorn[standard]>=0.52,<0.53" \
       "sentence-transformers>=5.7,<5.8" "openai~=2.54" \
       "sqlitesearch>=0.3,<0.4" "pydantic>=2.13,<2.14" \
       "pydantic-settings>=2.15,<2.16" "prometheus-client>=0.26,<0.27" \
       "psycopg[binary]>=3.3,<3.4" "numpy>=2.0"

# Optional (port of rag-search-engine BM25) — only if not using FTS5 BM25
uv add "nltk>=3.10,<3.11"

# Dev
uv add --dev pytest httpx pandas ruff tqdm

# CPU-only torch (llm-zc pyproject pattern — avoids huge GPU wheels)
# pyproject.toml:
#   [tool.uv.sources]      torch = { index = "pytorch-cpu" }
#   [[tool.uv.index]]      name = "pytorch-cpu"
#                         url = "https://download.pytorch.org/whl/cpu"

# Monitoring stack (repo root docker-compose.yml)
# services: sidecar (uvicorn, 127.0.0.1:8000 inside compose network),
#           prometheus (scrape sidecar /metrics),
#           grafana (provisioned dashboards)
docker compose up -d prometheus grafana
```

---

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| FastAPI | Flask | Flask only if the team is Flask-only and streaming/async is not needed. Flask's sync model + no native SSE makes it a worse fit for LLM token streaming. |
| FastAPI | Litestar | Litestar is faster and lighter, but FastAPI's docs/ecosystem and the two reference codebases' Pydantic-2 patterns tip the decision. |
| FastAPI | Streamlit (llm-zc `app.py`) | Streamlit is a demo UI, not a service — keep it as an optional internal debug dashboard, never the API. |
| sqlitesearch (SQLite FTS5 + vectors) | pgvector in the app's Postgres | Choose pgvector when (a) catalog grows well past ~100k chunks, (b) the team wants SQL joins between vectors and catalog rows, or (c) infra team prefers one DB. Aiven supports the pgvector extension (enable in Aiven console). At today's scale it buys complexity: schema coupling to the managed DB + no BM25 (Postgres FTS `tsvector` ≠ BM25). |
| sqlitesearch | minsearch (in-memory) | minsearch only for eval notebooks/experiments; it loses its index on restart. |
| sqlitesearch | Qdrant / Weaviate / Milvus / Chroma | Dedicated vector DBs are overkill for thousands of docs — new service, new ops burden, same recall. |
| Custom LLM-as-judge (port of llm-zc `judge.py` + `evaluation_utils.py`) | ragas 0.4.x | ragas when the team wants off-the-shelf standard metrics (faithfulness, context precision) without owning judge prompts. For this project the custom judge (~200 LOC, Pydantic structured verdicts + retry + JSON fallback) is already validated in llm-zc, costs one LLM call per judged pair (same as ragas), and keeps the eval loop transparent. |
| OpenRouter | OpenAI direct | OpenAI direct if the team already holds an OpenAI contract and model variety is irrelevant. Same SDK — swap `base_url`. |
| OpenRouter | Groq (llm-zc's provider) | Groq for fast open-weight inference, but llm-zc hit decommissioned model IDs and missing Responses-API support there; OpenRouter's router absorbs that churn. |
| Prometheus + Grafana | Postgres-as-metrics-store + Grafana (llm-zc's exact pattern) | llm-zc stores LLM-call rows in Postgres and lets Grafana query them directly — fewer moving parts. We keep that pattern for **conversation/feedback rows** (needed for eval anyway) but add Prometheus for live system metrics (latency histograms, HTTP codes) per PROJECT.md. If ops simplicity wins, drop Prometheus and use the llm-zc Postgres-only dashboards. |
| Local embeddings (`all-MiniLM-L6-v2`) | Hosted embeddings API (e.g., OpenRouter/OpenAI `text-embedding-3-small`) | Hosted embeddings when the catalog is huge (re-embedding cost) or CPU cycles are scarce. Tradeoff: per-embed cost, network dependency on every query embed, and model-version drift — for a small academic catalog, local is free, consistent, and lets eval iterate without API budget. |
| `all-MiniLM-L6-v2` | `BAAI/bge-small-en-v1.5`, `thenlper/gte-small` | Newer 384-dim models score a few points higher on BEIR; swap-in is one line if hybrid recall disappoints. |
| openai SDK 2.54.x | openai 3.0.0 | 3.0.0 released 2026-08-12; both reference codebases and all known provider-compat quirks (Groq JSON-mode fallback, base_url) are 2.x-proven. Upgrade after the milestone. |
| Custom agent loop (port llm-zc `rag_agent` — Responses API function-calling) | LangChain / LlamaIndex agents | LangChain/LlamaIndex bring thousands of LOC and abstraction debt for a loop llm-zc implements in ~100 lines with the raw SDK. See What NOT to Use. |

---

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| LangChain / LlamaIndex | Massive dependency surface, breaking-change churn, opaque abstractions. llm-zc's `rag_agent()` proves the multi-search agent loop is ~100 lines of raw SDK. | openai SDK function-calling (Responses API) or the simpler one-shot `rag()` path |
| Dedicated vector DB (Qdrant/Weaviate/Milvus/Pinecone) | New stateful service to run, back up, and secure — for a few thousand catalog rows | sqlitesearch; pgvector if/when scale demands |
| Kestra (llm-zc 03-orchestration) | Workflow orchestration server + its own Postgres for what is one chat pipeline; ops cost dwarfs benefit here | FastAPI endpoints; Laravel queue for the sync job if it grows |
| ragas (v0.4) in the first iteration | Heavy dependency tree (langchain-core etc.), forces its dataset formats; the custom judge already covers the milestone's eval goals | Custom LLM-as-judge ported from llm-zc; revisit ragas if standard-metric reports are demanded |
| Local LLM serving (vLLM, Ollama, llama.cpp) | GPU/hardware burden; the milestone explicitly targets a hosted provider API | OpenRouter (or OpenAI/Groq) via openai SDK |
| Streamlit as the chat UI | The chat widget lives in Laravel (Livewire 4); a second frontend fragments the UX and auth | Livewire chat widget calling the sidecar API; Streamlit only as internal debug dashboard |
| MLflow / Langfuse / full LLM-observability platform | Extra self-hosted service + SDK instrumentation for features Grafana + the feedback tables already provide at this scale | prometheus-client + Grafana; note Langfuse as the upgrade path if trace-level debugging becomes needed |
| Pinecone/Weaviate cloud APIs | Recurring cost + data egress for an on-prem academic system | Local embeddings + SQLite |
| Flask + Celery worker pattern | Queue + broker + workers to orchestrate LLM calls the async FastAPI event loop handles natively | FastAPI async endpoints |
| openai 3.0.0 | 1-day-old major with breaking changes; no reference-codebase validation | openai `~=2.54` |

---

## Stack Patterns by Variant

**If the catalog stays small (current scale: thousands of books, hundreds of papers, dozens of policy docs):**
- Use sqlitesearch (FTS5 + brute-force vector search) + `all-MiniLM-L6-v2` + in-process sync
- Because a single SQLite file needs zero infra and re-indexing takes seconds

**If the catalog grows past ~100k chunks or the team wants vector joins with catalog tables:**
- Use pgvector 0.8.x on the app's Postgres (Aiven supports the extension)
- Because data lives where the catalog lives — no sync pipeline, and SQL joins let retrieval filter by shelf, department, or availability natively

**If the MVP must ship fastest (chat without streaming):**
- Use non-streaming JSON responses (`POST /v1/chat` → `{answer, citations[]}`)
- Because Livewire renders instantly and the eval harness only needs complete answers

**If chat UX quality matters (fast-follow):**
- Use SSE from the sidecar, proxied through Laravel, rendered via Livewire 4 `->stream()`
- Because FastAPI's `StreamingResponse` and Livewire's streaming support make token-by-token rendering a config choice, not an architecture change

**If ops simplicity beats live-metrics granularity:**
- Use llm-zc's exact monitoring pattern: LLM-call rows + feedback rows in Postgres, Grafana queries Postgres directly (no Prometheus)
- Because the conversations table is already required for the feedback/eval loop, and Grafana needs only one datasource

**If a multi-model eval budget matters (judge vs chat models):**
- Use OpenRouter with separate configurable model IDs for `chat` and `judge` (e.g., judge = `openai/gpt-oss-120b` as llm-zc does)
- Because judging with a strong model catches quality regressions the cheap chat model hides

---

## Version Compatibility

| Package A | Compatible With | Notes |
|-----------|-----------------|-------|
| Python 3.13 | sentence-transformers 5.7, torch (CPU wheels), FastAPI 0.141 | llm-zc already runs this combo (`requires-python = ">=3.13"`). Avoid 3.14 until torch publishes wheels for it. |
| openai SDK 2.54.x | OpenRouter (`https://openrouter.ai/api/v1`), Groq (`https://api.groq.com/openai/v1`), OpenAI | All via `OpenAI(base_url=...)`. Responses-API structured output works on OpenAI/OpenRouter; Groq needs the JSON-mode fallback (llm-zc `llm_structured_retry`). |
| FastAPI 0.141.x | pydantic 2.13, uvicorn 0.52, prometheus-client 0.26 | `/metrics` endpoint via `prometheus_client.make_asgi_app()` mounts cleanly. |
| sqlitesearch 0.3.x | Python >=3.10; single-file SQLite FTS5 + vector index | `VectorSearchIndex` IVF mode exists (llm-zc default) — prefer brute force below ~10k docs for recall. |
| Laravel 13 / PHP 8.4 | Sidecar via HTTP only | No shared runtime; contract is JSON/SSE over HTTP + shared-secret auth. |
| pgvector 0.8.6 (image `pgvector/pgvector:0.8.6-pg17`) | PostgreSQL 16–18; Aiven (enable extension) | Only needed in the scale-up variant. |
| Grafana 13.1.x | Prometheus 3.13.x datasource; Postgres datasource (optional) | Provision via `provisioning/datasources/*.yml` (llm-zc pattern). |
| Livewire 4 streaming | Any HTTP source exposing SSE | Laravel proxies the sidecar's SSE stream; sidecar stays unexposed to the browser. |

---

## Sources

- `rag-search-engine` (WSL, `~/workspace/rag-search-engine`) — mined 2026-08-13: `pyproject.toml` (nltk 3.9.1, numpy 2.3.3, openai 2.44.0, sentence-transformers >=5.6.0), `cli/lib/hybrid_search.py` (weighted min-max + RRF k=60 fusion), `cli/lib/semantic_search.py` (all-MiniLM-L6-v2, chunked embeddings cached to `.npy`), `cli/augmented_generation_cli.py` (rag/summarize/citations/question over OpenRouter), `cli/evaluation_cli.py` (golden dataset, precision@k/recall@k/F1), `cli/hybrid_search_cli.py` (CrossEncoder + LLM reranking), `.env` (OpenRouter key)
- `llm-zc` (D:\ai-eng\llm-zc) — mined 2026-08-13: `requirements.txt`/`pyproject.toml` (openai>=2.45, sqlitesearch>=0.3, minsearch>=0.2, psycopg[binary], pydantic, Python>=3.13, CPU torch index), `ingest_sqlite.py` + `ingest_vector.py` (persistent FTS5 + vector indexes), `rag_helper.py` (RAGBase + agentic `rag_agent` via Responses API), `embedder.py` (all-MiniLM-L6-v2), `04-evaluation/` (ground truth, hit-rate/MRR, `13-llm-as-judge.py` with structured verdicts), `judge.py`/`db_feedback.py`/`db_init.py`/`metrics.py` (built-in judge, feedback + conversation tables, LLMCallRecord), `dashboard.py` (Streamlit), `docker-compose.yml` + `grafana/provisioning/` (Postgres-metrics + Grafana-as-code), `03-orchestration/` (Kestra flows — rejected)
- `C:\Users\admin\Herd\CEIT-Library` — `composer.json` (Laravel ^13.0, PHP ^8.4, Livewire ^4.0, Octane ^2.17), `.env` (`DB_CONNECTION=pgsql`, librarydb), `Dockerfile` (php:8.4-fpm-alpine + nginx + supervisor, Aiven CA cert handling in `Docker/start.sh` — production Postgres is Aiven-managed)
- PyPI JSON API (fetched 2026-08-13) — verified latest versions: fastapi 0.141.1, uvicorn 0.52.2, sentence-transformers 5.7.0, openai 3.0.0 (2.54.0 latest 2.x), prometheus-client 0.26.0, sqlitesearch 0.3.0, psycopg 3.3.4, pydantic 2.13.4, pydantic-settings 2.15.0, nltk 3.10.3, minsearch 0.2.0, ragas 0.4.3
- GitHub Releases API (fetched 2026-08-13) — grafana/grafana v13.1.3, prometheus/prometheus v3.13.2
- Docker Hub (fetched 2026-08-13) — `pgvector/pgvector` latest 0.8.6 (pg16/pg17/pg18 variants)
- python.org — latest stable 3.14.7 (recommendation holds at 3.13 for torch compatibility)

---

*Stack research for: CEIT-Library v2.0 AI Assistant (Python RAG sidecar + evaluation + monitoring)*
*Researched: 2026-08-13*
