# Capstone Readiness Audit — CEIT-Library vs FlyRank brief ("Your 10x Solution")

Research for wayfinder ticket [#45](https://github.com/PSergio984/CEIT-Library/issues/45).
Branch: `research/capstone-readiness-audit` · HEAD audited: `0d1d9d52`.
Method: static inspection of the worktree (no runtime `docker compose up` was executed); secret sweep ran pattern matching over all git-tracked text files with values suppressed.

## Scorecard

| # | Checkbox | Verdict | Effort to close |
|---|----------|---------|-----------------|
| 1 | Five claimed concepts locatable in code | **PASS** (two are thin — see G4) | — |
| 2 | LLM integration has validation **and** cost log | **PARTIAL FAIL** — validation ✓, cost log ✗ | Medium |
| 3 | Documented commands start whole system on clean machine | **PASS** (static consistency verified) | — |
| 4 | Seed/demo-data script | **PASS** | — |
| 5 | Written 5-minute demo path ("open X, click Y, see Z") | **FAIL** — not written down | Small |
| 6 | `.gitignore` coverage + no committed secrets | **PASS** (housekeeping notes below) | Small |
| 7 | Measured 10x number from eval/judge metrics | **PARTIAL PASS** — measured numbers exist, no 10x framing | Small |
| 8 | Background jobs actually consumed on documented runs | **FAIL** — jobs dispatched, never consumed | Medium |

Tally: 4 pass · 4 fail/partial across 8 checkboxes.

---

## Concept → file-path table (for the README concept table)

### 1. API endpoints — present but thin
| What | Path |
|------|------|
| JSON push-notification API (GET vapid key, POST subscribe; validates payload, returns JsonResponse, 401 guard) | `routes/web.php:119-120`, `app/Http/Controllers/PushSubscriptionController.php` |
| Token-gated HTTP client consumed by app (the sidecar contract) | `app/Services/AiService.php` |

No `routes/api.php` exists; bootstrap/app.php registers only web middleware. The two `/api/*` routes live under session auth in `web.php`. Acceptable as-is if documented honestly; a Sanctum-guarded `routes/api.php` (e.g. papers search) would make the claim robust.

### 2. Database — solid
| What | Path |
|------|------|
| 33 migrations (users, jobs, papers, authors, inventory, borrowing, attendance, violations, credit scores, rules, roles, notifications, AI conversations/messages) | `database/migrations/` |
| 24 Eloquent models | `app/Models/` |
| Supabase PostgreSQL config (pooler-compatible) | `.env.example` (`DB_*`), `config/database.php`, `docker-compose.yml` (`db` service, postgres:16-alpine) |

### 3. Authentication — solid
| What | Path |
|------|------|
| Session guard + Eloquent provider | `config/auth.php` |
| Rate-limited login (`Auth::attempt` + RateLimiter) | `app/Livewire/Forms/LoginForm.php` |
| One-click demo student login ("Reviewer demo" button, `demoLogin()`) | `resources/views/livewire/pages/auth/login.blade.php:39-192` |
| Role/status middleware | `app/Http/Middleware/AdminOnly.php`, `LibrarianOrAdmin.php`, `CheckAccountStatus.php`, `CheckCreditScore.php` |
| Route-level authorization (`can:` middleware, role groups) | `routes/web.php:91-208` |
| Auth routes (login/register/reset/verify) | `routes/auth.php`, `app/Http/Controllers/Auth/VerifyEmailController.php` |

### 4. Background jobs — wired at the producer end only
| What | Path |
|------|------|
| Job classes (`ShouldQueue`/`ShouldBeUnique`) | `app/Jobs/AiIndexRebuildJob.php`, `app/Jobs/AiIndexRebuildImmediateJob.php`, `app/Jobs/SendPushNotificationJob.php` |
| Dispatch sites | `app/Observers/AcademicPaperObserver.php`, `PeopleNameObserver.php`, `RulebookObserver.php`, `app/Models/AcademicPaperAuthor.php`, `app/Console/Commands/ReconcileAiIndex.php:96`, `app/Services/NotificationService.php:40` |
| Queue backend (database driver default) | `config/queue.php` (`'default' => env('QUEUE_CONNECTION','database')`), `.env.example` (`QUEUE_CONNECTION=database`), `database/migrations/0001_01_01_000002_create_jobs_table.php` |

**GAP:** nothing consumes the queue. `Docker/supervisord.conf` manages only nginx + php-fpm; `Docker/start.sh` runs no `queue:work`; README never mentions `php artisan queue:work`. On every documented run path, dispatched AI-rebuild and push jobs accumulate in the `jobs` table forever. The system still works because the hourly scheduler (`routes/console.php`: `ai:sync-index`, `ai:push-corpus`, …) calls the sidecar synchronously — which masks the broken consumer.

### 5. LLM integration — strong, minus the cost log
| What | Path |
|------|------|
| Single token-gated sidecar gateway (SSE streaming chat, bounded timeouts/retries, typed exception mapping, PII-sanitized failure logging) | `app/Services/AiService.php` |
| Chat widget loop: streaming answers, citations, feedback thumbs, conversation persistence | `app/Livewire/ChatWidget.php` |
| Citation-payload validation against mirrored schema constants (`CITATION_KEYS`) | `app/Livewire/ChatWidget.php` (`validCitationsPayload`), `app/Services/AiService.php:32` |
| Conversation/message persistence | `database/migrations/2026_08_14_000001_create_ai_conversations_table.php`, `..._000002_create_ai_messages_table.php` |
| Corpus lifecycle commands + hourly schedule | `app/Console/Commands/{ExportAiCorpus,PushAiCorpus,SyncAiIndex,ReconcileAiIndex}.php`, `routes/console.php` |
| Sidecar-side agentic loop with closed pydantic tool schema (`extra="forbid"`), canonical refusal with zero LLM calls | documented `README.md` § Agentic search (implementation lives in external `ceit-ai-sidecar` repo) |

- **Validation: PASS.** Two layers — Laravel checks every citation frame against `AiService::CITATION_KEYS` before render; sidecar validates tool calls against a closed pydantic schema before touching search.
- **Cost log: FAIL.** No token-usage or cost accounting anywhere in this repo, and the README's Monitoring section lists sidecar Prometheus counters (searches, latency, feedback, indexed docs, rebuilds) with no cost/token counter. The brief explicitly requires this for the LLM concept. Closest existing hooks: `AiService::chatStreamFrames()` could parse usage frames, or the sidecar could export an `llm_cost_total` counter.

---

## Other checkbox detail

### Clean-machine Docker Compose (PASS, statically)
README Quickstart § "Or run it with Docker" matches `docker-compose.yml` exactly: port `${APP_PORT:-8080}:80`, db healthcheck gating, first-boot `key:generate` into the `app_storage` volume, `migrate --seed --force` behind a `.seeded` marker, `CMD` → `Docker/start.sh` (multi-stage Dockerfile: composer + node build + php:8.4-fpm + nginx/supervisord). First-boot seeding cannot crash on a clean machine: compose sets `APP_ENV=local`, and `DatabaseSeeder` falls back to `Hash::make('password')` when `SEED_SUPER_ADMIN_PASSWORD` is empty in local/testing (`database/seeders/DatabaseSeeder.php:56-66`); the email has a literal fallback in `config/seeding.php`. Non-Docker quickstart (`cp .env.example .env`, composer, vite build, migrate --seed, serve) is internally consistent. Caveat: images were not built/run during this audit.

Minor: `database/seeders/AccountSeeder.php` is never called by `DatabaseSeeder` (only referenced from `tests/Feature/SuperAdminSeedConfigTest.php`). Dead code path, harmless.

### Seed/demo data (PASS)
`database/seeders/DatabaseSeeder.php` (714 lines): roles, super admin (+5 admins, +50 students), 30 papers, authors/advisers/deans, inventories, borrow transactions including overdue, attendances, violations, librarian batches — plus a dedicated `student@plv.edu.ph` demo flow used by the login page's demo button. Env knobs: `RUN_DB_SEED`, `SEED_SUPER_ADMIN_EMAIL/PASSWORD`.

### 5-minute demo path (FAIL)
README "Demo" section contains two screenshots and the live sidecar URL — that's it. No click-path script exists anywhere (searched README + docs/). Everything needed is seeded (demo student login button on the login page; chat widget on every authenticated page), so this is purely a writing task: e.g. "open http://localhost:8080 → Log in with demo student → dashboard chat widget asks 'papers about X?' → cited answer; log out → admin demo → attendance QR scan…".

### .gitignore & secrets (PASS, with housekeeping)
- `.gitignore` covers `.env*`, `/storage/*.key`, storage framework dirs, `/storage/app/`, vendor, node_modules, `public/build`. `git ls-files` shows **no committed `.env`**, no key material. Only `storage/certs/aiven-ca.pem` is tracked — a public CA certificate, acceptable (worth a one-line justification in the report or removal).
- Secret sweep over all tracked files (patterns: sk-/ghp_/JWT-service-keys/AKIA/private-key blocks/password literals/postgres URLs): 5 hits, all inspected and confirmed dummy/test fixtures (model/lang/test literals, e.g. local fallback `'password'` hash input). **No real credentials committed.**
- Housekeeping (tracked junk at repo root): `cl`, `curl`, `dockerignore` (stray duplicate of `.dockerignore` without the dot), `test_output.txt`, `tests_failures.txt`, `fix_hooks.js`, `_ide_helper.php`, `_ide_helper_models.php`.

### Measured 10x number (PARTIAL PASS)
`README.md` § Evaluation already carries measured, reproducible numbers (golden-set retrieval table + LLM-as-judge run):
- Shipped pipeline top-1 **0.8636** vs semantic-only 0.2727 (**≈3.2×**) and bm25 0.7273; negative-pass **1.00**; catalog-code top-1 **1.00**; judge relevant-rate **0.90** (9/10 RELEVANT, 0 NON_RELEVANT).
What's missing is the *framing*: no headline sentence states the multiplier. Cheapest close: add one line above the tables, e.g. "top-1 accuracy 3.2× over semantic-only retrieval, with a 100% negative-refusal rate." A true 10× would need a different denominator (e.g. time-to-answer vs manual library search — currently unmeasured).

---

## Prioritized gap list (build tickets burn these down)

| Priority | Gap | Ticket-sized fix | Effort |
|----------|-----|------------------|--------|
| 1 | Queue worker never runs (jobs pile up in DB) | Add `[program:queue-worker]` to `Docker/supervisord.conf` running `php artisan queue:work --stop-when-empty` or long-running worker; add one line to README quickstart; smoke-test rebuild-after-edit | Medium |
| 2 | LLM cost log absent (brief requirement) | Parse token usage from sidecar stream (`chatStreamFrames`) into an `ai_message_costs` column/log channel, **or** add `llm_tokens_total`/cost counter to sidecar `/metrics` + Grafana panel; cite it in README | Medium |
| 3 | 5-minute demo path not written | Write the scripted walkthrough into README § Demo (login button → chat Q&A with citations → admin view) | Small |
| 4 | 10x number unframed | One headline line over the Evaluation tables using existing measurements | Small |
| 5 | API concept thin (2 session-auth JSON routes, no `routes/api.php`) | Either document the push API + sidecar contract as the concept, or add a small Sanctum-guarded `routes/api.php` | Small–Medium |
| 6 | Repo hygiene | Delete `cl`, `curl`, `dockerignore`, `tests_failures.txt`, `test_output.txt`; gitignore `_ide_helper*`; justify/remove tracked CA pem | Small |

## Unverified claims (runtime needed)
- README's "604 tests" (≈616 test methods counted statically; count drift is expected) and sidecar's "144 tests" — require a test run.
- Actual `docker compose up --build` boot on a clean machine — static consistency verified only.
