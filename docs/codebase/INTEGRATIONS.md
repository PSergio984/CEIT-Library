# External Integrations

## Core Sections (Required)

### 1) Integration Inventory

| System | Type (API/DB/Queue/etc) | Purpose | Auth model | Criticality | Evidence |
|--------|---------------------------|---------|------------|-------------|----------|
| ceit-ai-sidecar | HTTP API (FastAPI, loopback locally / FastAPI Cloud in production) | Hybrid search (`/search`), SSE chat (`/chat/stream`), health, rebuild | Shared `X-Sidecar-Token` header (`SIDECAR_TOKEN`); production URL via `SIDECAR_URL` | High (AI features) | `app/Services/AiService.php`, `.env.example` |
| OpenRouter (via sidecar) | LLM API (openai SDK) | Chat answer generation | `LLM_API_KEY` lives only in sidecar `.env` (ADR 0001) | High | sidecar `app/config.py` |
| Web Push (VAPID) | Push notification service | Browser push notifications | VAPID keys (generated via `php artisan generate:vapid-keys`) | Medium | `composer.json` (minishlink/web-push), `app/Http/Controllers/PushSubscriptionController.php` |
| Mail | SMTP/log | Notifications, welcome email | `MAIL_*` env vars (default log) | Low-Medium | `.env.example` |
| PDF export | dompdf (in-process) | Analytics/reporting PDFs | n/a | Low | `composer.json` (laravel-dompdf) |
| SonarCloud | CI quality gate | Static analysis quality gate | `SONARQUBE_TOKEN` repo secret | Med (CI only) | `.github/workflows/ci.yml` (sonarcloud job) |
| SonarQube CLI | CI secrets scan | Secrets detection (offline) | `SONARQUBE_TOKEN` repo secret | Med (CI only) | `.github/workflows/sonar-secrets.yml` |
| GitHub CodeQL | CI security scan | Code scanning | `GITHUB_TOKEN` | Med (CI only) | `.github/workflows/codeql.yml` |

### 2) Data Stores

| Store | Role | Access layer | Key risk | Evidence |
|-------|------|--------------|----------|----------|
| SQLite (`database/database.sqlite`, CI `:memory:`) | Primary relational store | Eloquent | Production-scale/multi-writer limits; postgres/redis env vars exist in `.env.example` but are not wired as defaults | `.env.example`, `phpunit.xml` |
| Sidecar versioned index (files: `docs-N.json`, `vectors-N.npy`, `index-N.db`, `state.json`) | Search index owned by the sidecar | sidecar `HybridSearch`; Laravel never reads it directly | Staleness if scheduler/queue not running | sidecar `app/search.py`, `app/rebuild.py` |
| `storage/app/ai-corpus/*.json` | Corpus export hand-off files | `CorpusExporter` writes; sidecar reads — locally via shared disk, **in cloud via `ai:push-corpus` → `POST /corpus/upload`** | Schema drift between exporter and sidecar validator (`schema_version: 1`) | `app/Services/CorpusExporter.php`, `app/Console/Commands/PushAiCorpus.php`, sidecar `app/ingest.py` |

### 3) Secrets and Credentials Handling

- Credential sources: `.env` files (both repos, gitignored); GitHub repo secrets for CI (`SONARQUBE_TOKEN`); VAPID keys in env
- Hardcoding checks: sonar-secrets workflow scans every push (passes — no findings); no committed keys found in repo
- Rotation notes: `SIDECAR_TOKEN` is the shared placeholder `smoke-test-token` in both `.env` files — **must be rotated before production**; `LLM_API_KEY` (OpenRouter) was exposed in an earlier chat session — **must be rotated** (lives only in sidecar `.env`)

### 4) Reliability and Failure Behavior

- Retry/backoff: not implemented for sidecar calls (`AiService` request uses `retries: 0`, timeout 120s)
- Timeout policy: `AiService::request(timeout: 120, retries: 0, stream: true)` for `/chat/stream`; SSE parsing throws on truncation (no `[DONE]`) — fail-closed
- Circuit-breaker/fallback: none; AI features fail visibly with logged errors; non-AI library features unaffected (sidecar is only used by AI surfaces)
- Scheduled sync: hourly `ai:export-corpus` + `ai:sync-index`, nightly `ai:reconcile-index --repair` (self-heals stale index)

### 5) Observability for Integrations

- Logging around external calls: yes — `AiService` logs failures (`logFailure('/chat/stream', ...)`); sidecar logs provider errors (`logger.error(repr(exc))` → `event: error` SSE)
- Metrics/tracing: no distributed tracing; sidecar exposes hand-rolled counters (`/metrics`: searches_total, rebuilds_total, search_avg_ms, index_documents) — Prometheus export is Phase 14 work [TODO]
- Missing visibility gaps: no latency histograms per integration; no LLM cost tracking; no alerting [TODO — OPS-01/02]

### 6) Evidence

- `app/Services/AiService.php`
- `.env.example` (SIDECAR_URL/SIDECAR_TOKEN/AI_CORPUS_PATH)
- `routes/console.php` (sync schedule), `app/Console/Commands/ReconcileAiIndex.php`
- `.github/workflows/*.yml`
