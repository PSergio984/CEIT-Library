# Phase 9 Research: RAG Chat & Policy Q&A

**Researched:** 2026-08-14 (verified against live code, not just docs)
**Repos:** `ceit-ai-sidecar` (clean tree, HEAD `d82c317`) · `CEIT-Library` (clean tree, 2 untracked files unrelated to Phase 9)
**Answer to:** *"What do I need to know to PLAN this phase well?"*

---

## 1. Verified Facts at a Glance

| Fact | Verified value |
|---|---|
| `/chat/stream` exists | `app/main.py:109-134` — SSE `data:` chunks + `[DONE]`, mode/corpus/top_k validation (corpus value NOT validated), `include_text=True`, `k=60` fixed |
| 401 error code today | `invalid_request` (`main.py:74`) — **violates D-05** (needs `auth_failed`) |
| Error event shape today | `event: error\ndata: RuntimeError` — bare exception type (`rag.py:157`) — **violates D-04** (needs JSON `{"code":"provider_error","message":...}`) |
| Empty-retrieval refusal | NOT programmatic — prompt-only (`rag.py:9-12` docstring, `stream_events` always calls LLM) — **violates D-23** |
| `RagService.answer()` | internal only, never routed (`main.py` has no `/chat`) — **D-01 compliant** |
| PROMPTS | rag + citations + question ported (`rag.py:25-61`), summarize absent — **D-35 compliant** |
| LLM config | `config.py:13-16` — `LLM_BASE_URL/LLM_API_KEY/LLM_MODEL/LLM_MAX_TOKENS`, default `meta-llama/llama-3.3-70b-instruct`, 512 tokens — **D-34 compliant** |
| Livewire version | `livewire/livewire` **v4.4.0** (`composer.lock`) |
| `stream()` signature | `stream($content, $replace = false, $name = null, $el = null, $ref = null)` (`SupportStreaming.php:19`) — positional-first; named arg `content:` also works (param is literally `$content`) |
| `Http::toStream()` | **DOES NOT EXIST** in Laravel 13's `Illuminate\Http\Client` — the method is `Response::resource()` ("Get the body of the response as a PHP resource", `Response.php:169-175`). D-11's `toStream()` reference is stale; plan must use `resource()` |
| Livewire streaming test assertions | **NONE in 4.4.0** — `Testable.php` has zero `stream` matches; no `SupportStreaming/Testing` dir. Streaming can't be asserted via `Livewire::test()`; assert persistence/state instead |
| Laravel baseline | **523 passed / 3 skipped** (1487 assertions) in 50.60s — re-verified today |
| Sidecar baseline | **42 tests collected** via `uv run pytest --collect-only -q` |
| Sidecar LLM isolation | 100% fake-client injection (`FakeCompletionsHolder`); **no test anywhere hits OpenRouter**; live verification lives in the smoke-test ticket, not pytest |
| Laravel live-test gate | `SidecarLiveTest` `#[Group('sidecar')]` + `markTestSkipped` unless `SIDECAR_LIVE_TEST=1` |
| AiService test pattern | `Http::fake()` + `tests/fixtures/ai-sidecar/*.json` + `Http::preventStrayRequests()` + `Http::assertSent()` + `Log::shouldReceive()` (Mockery) |
| `rule_regulations` R10 bug | CONFIRMED at DB level: migration `2025_10_02_105415` has NO `order` column; `RuleHeader::ruleRegulations()` does `orderBy('order')` (`RuleHeader.php:12`) → **calling that relation throws SQL error**; CorpusExporter works around with independent `RuleRegulation::orderBy('id')` |
| Corpus-deletion landmine | CONFIRMED: `ExportAiCorpusTest::setUp()` does `@unlink(storage_path('app/ai-corpus/*.json'))` → after `php artisan test` the exported corpus may be gone; re-run `php artisan ai:export-corpus` before live sessions |
| Catalog corpus | Bibliographic-only: title, authors, advisers, dean, department, year, paper_type, catalog_code — **AcademicPaper has NO abstract/content field** |
| Policy corpus | Faker placeholder content — real rulebook data pending (known caveat, not a bug) |
| Secrets | `SIDECAR_TOKEN=smoke-test-token` in BOTH `.env` files; `LLM_API_KEY` present in `ceit-ai-sidecar/.env` (gitignored) and **must be rotated** (was pasted in chat — see CONTEXT specifics) |
| 9 ADRs | All present in `docs/adr/0001..0009-*.md` ✓ |
| Prototype | Branch `prototype/chat-widget` @ `395244dc` — variant A (right drawer `w-full sm:w-96`, FAB bottom-right) is the D-27 verdict source; citation chips + sources blades implement D-21/D-22 shapes verbatim |

---

## 2. Verified Current State per Seam

### 2.1 Sidecar — `POST /chat/stream` (`app/main.py:109-134`)

- Token gate: global `require_token` http middleware (`main.py:66-79`) — constant-time compare via `secrets.compare_digest`, 401 with `{"error":{"code":"invalid_request","message":"missing or invalid X-Sidecar-Token"}}`.
- Request handling (all validated in-code, no Pydantic):
  - `query`: required non-blank string → else 422 `invalid_request` (`main.py:59-63`).
  - `mode`: default `"citations"`; must be in `citations|question|rag` → else 422 (`main.py:120-122`).
  - `corpus`: `payload.get("corpus") or None` — **accepted verbatim, NO value validation** (D-07 gap). No filter applied when None (both corpora).
  - `top_k`: `int()` coerced, default 5, range 1-50 → else 422 (`main.py:124-129`).
  - Unknown extra fields silently ignored (no `history` field — D-02 compliant).
- Retrieval: `_get_engine().rrf_search(query, k=60, limit=top_k, corpus=corpus, include_text=True)` (`main.py:131`).
- Response: `StreamingResponse(events, media_type="text/event-stream")` (`main.py:134`). **No `Cache-Control`/`X-Accel-Buffering` headers** (minor hardening gap, not in ADR).
- No user identity anywhere in the payload or path — D-08 compliant.

### 2.2 Sidecar — `RagService` (`app/rag.py`)

- `PROMPTS` dict with `rag` / `citations` / `question` — domain-parameterized for "CEIT Library", refusal language `I don't have enough information` embedded in rag + citations (question mode has NO grounding/refusal instruction — ported as-is per ADR 0003).
- `build_context()`: `"{i}. {title} - {text}"`, `MAX_DOC_CHARS = 600`, falls back to title, strips newlines.
- `build_prompt()`: formats `query/question` and `docs/context` from the same values; unknown mode defaults to citations prompt.
- `stream_answer()`: openai SDK `chat.completions.create(stream=True)`, yields `delta.content` when non-empty.
- `stream_events()`: wraps `stream_answer`; on ANY exception yields `event: error\ndata: {type(exc).__name__}\n\n` (D-04 gap); always ends `data: [DONE]\n\n` (even after error event — parser must stop at the error event and treat trailing `[DONE]` as terminator).
- `answer()`: one-shot internal method (test seam only — D-01 compliant).
- Client lazily created in `_ensure_client()` only when not injected — tests inject fakes so **no test ever constructs a real OpenAI client**.

### 2.3 Sidecar — search/config

- `rrf_search(query, k=60, limit=10, filters, corpus, include_text=False)` — `include_text=True` adds `result["text"]`; result shape: `{id, corpus, title, score, bm25_rank, semantic_rank, metadata}` where metadata carries the full corpus doc metadata (incl. `url`, `catalog_code`, `header_title`, ...).
- Deterministic RRF fusion (k=60) + code-exact pin (`CEIT-[A-Z]{2}-\d{2}(-\d+)?` → rank 1). Same k=60 + same corpus filter on `/search` and `/chat/stream` → **identical ranking**, which is what makes D-20's companion-search citation binding sound.
- `config.py` — `Settings` via pydantic-settings, env_file `.env`; keys `SIDECAR_TOKEN, CORPUS_PATH, MODEL_NAME, HOST, PORT, LLM_*`; cache_dir default `cache`.
- `/search` endpoint (`main.py:87-106`) — `query` required, `filters` dict, `corpus`, `limit` (default 10), `k` (default 60); returns `{query, total, took_ms, results}`; metrics recorded.

### 2.4 Sidecar — test infra

- `tests/conftest.py`: sets `SIDECAR_TOKEN`/`CORPUS_PATH` env **before** app imports; deterministic hash embedder (8-dim) + `build_test_index()` (real versioned index); fixture corpus with realistic doc shapes (`paper-{id}`, `policy-h{h}`, `policy-h{h}-r{r}`).
- `test_chat_stream.py`: `TestClient` + monkeypatched globals (`main_mod.settings`, `main_mod._search_engine = FakeEngine`, `main_mod._rag = RagService(client=FakeCompletionsHolder(...))`). Asserts: 401 without token, 422 missing query, 200 + `text/event-stream` + `data: [DONE]` terminator, prompt wiring (`include_text=True`, corpus/limit forwarded), 422 non-numeric top_k, error event on provider failure (**asserts `"RuntimeError" in resp.text` — will BREAK under D-04**).
- `test_rag.py`: pure unit tests of `build_context/build_prompt/answer/stream_answer/stream_events` with fake client; asserts `[DONE]` last; error event test asserts `startswith("event: error")` (survives D-04, but the JSON body assertion should be added).
- `test_api.py`: **`test_search_without_token_is_401` asserts `error.code == "invalid_request"` (`test_api.py:60`) — will BREAK under D-05**.
- 42 tests total, deterministic, no model downloads, sub-second collection; full run is seconds.

### 2.5 Laravel — `AiService` (`app/Services/AiService.php`)

- `search($query, $filters=[], $corpus='catalog', $limit=10)` → POST `/search` with `k = RRF_CANDIDATES (60)`, timeout 10s, retries 2.
- `rebuildIndex()` timeout 120s/1 retry; `health()` timeout 5s/1 retry.
- `send()` private gateway: `Http::withHeaders(['X-Sidecar-Token' => config('services.ai_sidecar.token')])->baseUrl(config('services.ai_sidecar.base_url'))->connectTimeout(3)->timeout($timeout)->retry($retries, 250, throw:false)` → post/get → `ConnectionException` → `AiServiceUnavailableException` → `throwUnlessOk()` → `$response->json()`.
- `throwUnlessOk()`: 401 → `AiServiceAuthException`; any other failure → `AiServiceUnavailableException('...HTTP {status}...')` (so 422 lands under Unavailable — D-12 compliant as-is).
- `logFailure()`: sanitized `Log::warning` with only `endpoint` + `reason` (never token/query/body).
- Typed exceptions: `AiServiceAuthException`, `AiServiceUnavailableException` — both bare `RuntimeException` subclasses; `AiServiceProviderException` does NOT exist yet (D-12).
- Config: `config/services.php` → `ai_sidecar.base_url` = `SIDECAR_URL` (default `http://127.0.0.1:8310`), `token` = `SIDECAR_TOKEN`, `corpus_path` = `AI_CORPUS_PATH` (default `storage_path('app/ai-corpus')`).

### 2.6 Laravel — Livewire 4.4.0 streaming mechanics (verified in vendor)

- `SupportStreaming.php`: `stream($content, $replace = false, $name = null, $el = null, $ref = null)` — sends `{"stream":true,"body":{...},"endStream":true}` JSON through `response()->stream()` headers (`Cache-Control: no-cache`, `Content-Type: text/event-stream`, `X-Accel-Buffering: no`, `X-Livewire-Stream: true`).
- `StreamManager` fluent alias: `$this->stream()->content($c)->to()`.
- Component calls `$this->stream(...)` (hook intercepts component method calls).
- **Testing gap:** no `assertStreamed`/streamed assertions in v4.4.0 — streaming behavior is not directly assertable in `Livewire::test()`; verify at the AiService layer (read fake stream resource) + assert post-call state.
- `Http\Client\Response::resource()` (`Response.php:169-175`) — "Get the body of the response as a PHP resource" via `StreamWrapper::getResource($psrBody)`. With `withOptions(['stream' => true])` this is the incremental read seam; `fgets()`/`fread()` over it yields SSE lines as they arrive.
- `Http::fake()` accepts stream resources / PSR StreamInterfaces as bodies (`Factory::response()` validation at `Factory.php` — string, null, stream resource, or StreamInterface) → **the SSE stream is fakesble** with a plain string body (wrapped in a seekable in-memory PSR stream; `resource()` still works line-by-line).

### 2.7 Laravel — component conventions (widget is a component, not a page)

- Pages: `app/Livewire/Pages/Student|Admin/...` with `#[Title(...)]`, `#[Layout('components.layouts.app')]`, `#[Lazy]`, `#[Computed]`, `#[Validate]`, `render()` → `view('livewire.pages.student.x')`, `placeholder()` method; `use Livewire\WithPagination`.
- Non-page components live at `app/Livewire/` root (e.g., `QrScanner.php`); layout components at `app/Livewire/Layout/` (`<livewire:layout.user-menu />`).
- Sidecar consumption pattern (Phase 8 precedent): `AcademicPaperIndex::runHybridSearch()` — try/catch `AiServiceUnavailableException|AiServiceAuthException` → `$this->aiSearchFailed = true` → silent fallback; `Http::fake` in tests with `config(['services.ai_sidecar.token' => 'test-token'])`.
- Layouts: `resources/views/components/layouts/app.blade.php` (255 lines, DaisyUI/Mary UI, `@livewireStyles`, `wire:navigate.hover`, `<x-mary-toast />` + `<x-pwa-install-banner />` near `</body>`) and `components/layouts/admin.blade.php` (Mary UI drawer, `x-slot:sidebar`). **Both layouts are the mount seams for the FAB** — D-27 "every authenticated page" requires including the widget in BOTH (welcome page is public — no FAB).
- SPA mode: `wire:navigate.hover` everywhere → the widget must tolerate page transitions (component state resets per navigation unless `wire:persist` is used — implementation consideration).

### 2.8 Laravel — routes & middleware

- `routes/web.php`: `Route::middleware(['auth', 'verified'])->group(...)` for user pages (dashboard, academic-papers, rule-and-regulation, transactions, notifications); admin group adds `librarian.or.admin`; `academic-paper.show` = `/academic-papers/{academicPaper}` (number-constrained) — the catalog citation link target.
- `CheckAccountStatus` is **appended to the global web group** (`bootstrap/app.php` — `$middleware->web(append: [...])`), logs out non-`active` accounts → no new middleware needed for the widget (D-25 compliant).
- Throttle aliases exist (`throttle:search`, `throttle:transactions`, `throttle:qr-scanning`) — chat rate limiting is Phase 14 (D-06), do not add now.
- `users.account_status` enum `active|suspended` default `active`.

### 2.9 Laravel — models & corpus shapes (citation binding ground truth)

- `AcademicPaper` (table `academic_papers`): id, catalog_code, title, publication_year, paper_type, research_adviser_id, technical_adviser_id, department, dean_id + relations (authors, researchAdviser, technicalAdviser, dean, copies). **No abstract/content column** → catalog grounding is bibliographic-only (D-34/D-35 implication, confirmed by conftest doc text shape: title ×2 + authors/advisers/dean/department/year/type/code).
- `RuleHeader` (table `rule_headers`): title, `order` (integer, default 0). Relation `ruleRegulations()` = `hasMany(...)->orderBy('order')` → **R10 latent bug** (see 2.10).
- `RuleRegulation` (table `rule_regulations`): rule_header_id (FK cascade), content. No `order` column.
- `CorpusExporter` doc shapes (locked, Phase 8):
  - catalog: `id: paper-{id}`, metadata includes `catalog_code`, `url: /academic-papers/{id}` (+ department/year/type/authors/advisers/dean).
  - policy header: `id: policy-h{h}`, text `Section: {title}`, metadata `policy_type: header`, `url: /policies`.
  - policy regulation: `id: policy-h{h}-r{r}`, text `Section: {title}\n{content}`, metadata `policy_type: regulation`, `header_title`, `url: /policies`.
  - Policy docs have NO `catalog_code` → citation payload `catalog_code: null` for policy rows (matches D-14 shape `[{n, id, corpus, title, url, catalog_code}]`).
- Citation payload source: sidecar `/search` result → `{n: rank, id, corpus, title, url: metadata.url, catalog_code: metadata.catalog_code}`.

### 2.10 Laravel — migrations

- Convention (latest examples `2026_06_15_234457_create_notification_preferences_table.php`, `2026_05_21_000001_create_push_subscriptions_table.php`): anonymous class, `Schema::create`, `$table->id()`, `$table->foreignId('user_id')->constrained()->onDelete('cascade')`, inline `//` comments on columns, `$table->timestamps()`, indexes/uniques declared at the end, `Schema::dropIfExists` in `down()`.
- **R10 confirmed at DB level**: `2025_10_02_105415_create_rule_regulations_table.php` — only `id`, `rule_header_id` (cascade), `content`, timestamps. `RuleHeader::ruleRegulations()`'s `orderBy('order')` will throw `SQLSTATE[42S22]: Column not found` if called. CorpusExporter avoids it (`RuleRegulation::orderBy('id')->get()->groupBy('rule_header_id')`, `CorpusExporter.php:65`). Chat phase should NOT introduce new code paths that touch that relation without either fixing the relation (`orderBy('id')`) or adding the column.

### 2.11 Laravel — test infra

- `phpunit.xml`: PHPUnit 13 (Pest plugin enabled but tests are PHPUnit-attr style), suites Unit + Feature, sqlite `:memory:`, memory_limit 512M, `CACHE_STORE=array`, `MAIL_MAILER=array`, etc.
- Layout: `tests/Feature/AiServiceTest.php` (Http::fake + fixtures + Mockery `Log::shouldReceive`), `AcademicPaperIndexHybridTest.php` (Livewire::test + Http::fake + seed papers with `forceFill(['id'=>77])` to match fixtures), `ExportAiCorpusTest.php` (artisan `ai:export-corpus` + `@unlink` corpus files in setUp), `SidecarLiveTest.php` (`#[Group('sidecar')]`, env-gated), `tests/fixtures/ai-sidecar/{health,rebuild,search}.json`, `tests/Traits/CreatesTestDatabase.php`, `tests/Traits/TestHelper.php`.
- Fixture `search.json` is the canonical citation-source mock: `paper-77`, metadata incl. `url: /academic-papers/77`, `catalog_code: CEIT-CE-15-014`.

### 2.12 Env / secrets

- Both `.env` files carry `SIDECAR_TOKEN=smoke-test-token` (placeholder — operator sets real token before prod).
- `ceit-ai-sidecar/.env` has live `LLM_API_KEY` (gitignored; **must be rotated** per CONTEXT — was pasted in chat; do NOT commit, do NOT copy into tests).

---

## 3. Exact Deltas to the Locked Contract

### 3.1 Sidecar (ADR 0004/0006) — code exists, N contract deviations

| # | Location | Current | Required | Test impact |
|---|---|---|---|---|
| Δ1 (D-04) | `rag.py:157` | `event: error\ndata: RuntimeError` | `event: error` + `data: {"code":"provider_error","message":"<safe generic>"}` (JSON); details only in server logs | `test_chat_stream.py:206` asserts `"RuntimeError" in resp.text` → must change; `test_rag.py:167` survives |
| Δ2 (D-05) | `main.py:74` | 401 `error.code = "invalid_request"` | `error.code = "auth_failed"` | `test_api.py:60` asserts `invalid_request` → must change |
| Δ3 (D-07) | `main.py:123` | `corpus = payload.get("corpus") or None` — anything accepted | reject values other than `catalog`/`policy`/absent with 422 `invalid_request` | add test |
| Δ4 (D-23) | `rag.py:148-158` / `main.py:131-134` | refusal is prompt-only; empty results still calls LLM | programmatic branch: when `results == []` yield `data: I don't have enough information\n\n` then `data: [DONE]\n\n`, NO LLM call, no score threshold | add test asserting fake client NOT invoked |
| Δ5 (opt) | `main.py:134` | `StreamingResponse(events, media_type="text/event-stream")` | consider `Cache-Control: no-cache`, `X-Accel-Buffering: no` (parity with Livewire stream headers; not in ADR) | none |

**Compliant already:** request schema + defaults (D-02), `[DONE]` terminator (D-03), `include_text=True` retrieval, mode validation, top_k 1-50, one-shot `answer()` unexposed (D-01), no user identity (D-08), 3 prompt modes, no `summarize` (D-35), provider config (D-34).

### 3.2 Laravel AiService (ADR 0004) — additive, no behavior break

| # | Current | Required |
|---|---|---|
| Δ6 (D-09) | request builder inline in `send()` (`AiService.php:48-56`) | extract into shared private helper (e.g., `request(string $method, string $path, array $body, int $timeout, int $retries, bool $stream=false): PendingRequest`); `send()` refactors to use it |
| Δ7 (D-10) | — | `chatStream(string $query, ?string $mode = 'citations', ?string $corpus = null, int $topK = 5): Response` — POST `/chat/stream` `{query, mode, corpus, top_k}`, `throwUnlessOk()` FIRST (before touching body), return the streamed `Response`. **Use `withOptions(['stream' => true])` + `$response->resource()` — NOT `Http::toStream()` (doesn't exist in Laravel 13; renamed to `resource()`)** |
| Δ8 (D-11) | — | component: read SSE lines via `fgets($response->resource())`; per chunk `$this->stream(content: $chunk, replace: false)` (valid — param is `$content`; positional `$this->stream($chunk)` also fine) |
| Δ9 (D-12) | two exceptions | add `App\Exceptions\AiServiceProviderException extends RuntimeException`; SSE `event: error` line → decode JSON → throw with `message` |
| Δ10 | `retry(2, 250, throw: false)` on send | **flag:** retries re-issue the whole POST → duplicate LLM generation on 5xx. For `chatStream` use retries 0-1 + explicit `when()` guard, or accept the risk; note in plan |
| Δ11 | — | SSE parse rules: `data:` lines → content (accumulate), `event: error` → abort + provider exception, `data: [DONE]` → end; trailing `[DONE]` after error must be ignored |

### 3.3 Schema (ADR 0005) — net-new

- New migrations (naming per convention): `create_ai_conversations_table` — `id`, `user_id` (FK→users, `onDelete('cascade')`, NOT NULL), `title` (string 120, nullable), timestamps; index `(user_id, updated_at)` (D-13).
- `create_ai_messages_table` — `id`, `conversation_id` (FK→ai_conversations, cascade), `role` (enum `user|assistant`), `content` (text), `citations` (json nullable — the `[{n, id, corpus, title, url, catalog_code}]` list), timestamps; index `(conversation_id, id)` (D-14).
- New models `App\Models\Conversation` + `App\Models\Message` (mapped tables, `$table->...` names) — none exist today (verified: no Conversation/Message in `app/Models`).
- No soft deletes, no TTL (D-17); `touch()` on parent when a message is added (D-16 `updated_at` desc list); auto-title 120 chars from first user message, fallback `New conversation` (D-18); hard delete cascade (D-17); history loads are view-only, never replayed (D-19).

### 3.4 Widget (ADR 0007/0008/0009) — net-new component

- New non-page Livewire component (convention: `app/Livewire/ChatWidget.php` or a `Chat/` subfolder — agent discretion), mounted via `<livewire:chat-widget />` in **both** `components/layouts/app.blade.php` and `components/layouts/admin.blade.php` (D-27 "every authenticated page").
- Behind existing `['auth', 'verified']`; `CheckAccountStatus` already global — no new middleware, no role gating, no blocked-state UI (D-25/D-26).
- Drawer two-view state (list ⇄ chat, list default on open) per D-30; lazy conversation row creation on first message per D-32; entries = truncated title (~40 chars) + relative time per D-33; no delete/rename.
- Prototype reference: `prototype/chat-widget` @ `395244dc` — `chat-widget-variant-a.blade.php` (FAB `btn btn-circle btn-primary fixed bottom-6 right-6 z-40 w-16 h-16` + drawer `fixed inset-y-0 right-0 z-40 w-full sm:w-96 ... translate-x-full`), `chat-widget-citations.blade.php` (link vs non-link chips), `chat-widget-sources.blade.php` (numbered Sources list under answer) — matches D-21/D-22/D-27/D-28/D-29 markup. Throwaway; delete after planning consumes (per CONTEXT).

---

## 4. Recommended Implementation Approach per Plan Area

### 4.1 Sidecar contract fixes (Δ1-Δ5) — small, self-contained
1. `rag.py` `stream_events()`: wrap provider errors → `json.dumps({"code": "provider_error", "message": "The AI provider is temporarily unavailable. Please try again."})`; log `repr(exc)` server-side; keep `[DONE]` after error event.
2. `main.py` middleware: `code` → `"auth_failed"`.
3. `main.py` chat_stream: validate corpus (`if corpus not in (None, "catalog", "policy")` → 422).
4. Empty-retrieval branch: in `chat_stream` (or `stream_events` — recommend a guard in `stream_events(query, results, mode)` so the internal `answer()` seam also refuses) — when `not results`: yield refusal chunk + `[DONE]` without constructing a client.
5. Update the 2 breaking tests + add 3 tests (Δ3, Δ4-no-LLM-call, Δ1 JSON shape). Re-run full sidecar suite (seconds).

### 4.2 AiService extension (Δ6-Δ11)
1. Refactor `send()` → shared `request()` helper (D-09); `search/rebuildIndex/health` keep exact behavior (existing AiServiceTest is the regression net — 9 tests).
2. `chatStream()`: build via helper with `withOptions(['stream' => true])`, `throwUnlessOk()` before stream read, return `Response`. Document `resource()` (not `toStream()`).
3. `AiServiceProviderException` + tiny SSE-line parser (yield parsed chunks; detect `event: error`). Put parsing in AiService (e.g., `chatStreamEvents()` iterator) so the component stays thin — agent discretion; D-10 says return streamed Response, so either return Response + let component parse lines, or return an iterator of parsed chunks. Recommend: `chatStream()` returns Response (per D-10) and a small internal parser used by the widget (keeps single-turn contract testable via `resource()` fakes).

### 4.3 Schema + migrations
- Two migrations per 2.10 conventions; models with `$fillable`, `$casts` (`citations` → array), relations (`Message::conversation()`, `Conversation::messages()`, `Conversation::user()`), `Conversation::booted`/message-saved `touch()`, auto-title in a `conversations()` creator. Factories for tests (`Database\Factories\ConversationFactory`, `MessageFactory`).
- Flag: do NOT touch `RuleHeader::ruleRegulations()` anywhere new (R10). Optionally fix the relation to `orderBy('id')` as a tiny side fix in this phase (2-line change, no migration needed, CorpusExporter workaround can stay) — planner's call.

### 4.4 Widget + drawer + conversation list
- Component with: `$view` (`list`|`chat`), `$activeConversationId`, `$query`, `$messages` (local render array), `$streaming`, `$failed`, `$error` — per D-29/D-30/D-31/D-32.
- FAB/drawer markup from prototype variant A; Alpine for open/close + scroll-to-bottom; typing-dots while `$streaming` (D-28).
- Actions: `openConversation(id)` (load messages asc + citations re-rendered from JSON), `newConversation()`, `send()` (lazy-create conversation + first user message + auto-title; companion `/search`; POST chat stream; persist assistant message with citations; `touch()`), `retry()` (replaces failed turn in place — D-29).
- Route: none needed (component-in-layout, not a page) — but list default view needs a `mount()` that loads conversations by `user_id` ordered `updated_at desc`.
- `wire:persist` on the widget (or accept re-mount) given SPA `wire:navigate`; keep `$streaming` guard against concurrent sends.
- Both layouts include `<livewire:chat-widget />` before `</body>`; consider `#[Lazy]` + placeholder (convention) — but note lazy + `wire:navigate` interplay (widget should be eager on layout to avoid FAB pop-in after navigation).

### 4.5 Citations/grounding wiring (D-20..D-24)
- Companion search: `(new AiService)->search($query, [], $corpus, $topK)` — NOTE `search()` default `$corpus = 'catalog'`; chat must pass `$corpus` explicitly (null = both, matching the chat call). k=60 fixed on both sides → identical ranking → `n = index+1` of results.
- Payload: `['n' => $i+1, 'id' => r['id'], 'corpus' => r['corpus'], 'title' => r['title'], 'url' => r['metadata']['url'] ?? null, 'catalog_code' => r['metadata']['catalog_code'] ?? null]`.
- Render: catalog → link chip to `url` (route `academic-paper.show` = `/academic-papers/{id}`) with `catalog_code`; policy → non-link chip with header title; full numbered Sources list under the answer (D-21/D-22) — prototype blades are the exact markup.
- Refusal: if companion search returns 0 results → skip the chat call entirely? NO — D-23 puts the refusal in the sidecar (server-side); the widget renders whatever bubbles come back. But the widget can short-circuit its own companion-search for citations (empty → no sources list). Keep sidecar as the single refusal authority.
- Recommendation: run companion search SEQUENTIALLY before streaming (payload binds to the same retrieval order; parallel adds complexity for no benefit at this scale) — agent discretion per CONTEXT.

---

## 5. Risks & Landmines

1. **R10 latent bug (CONFIRMED, DB-level)**: `rule_regulations` has no `order` column; `RuleHeader::ruleRegulations()` calls `orderBy('order')` → SQL error on use. Chat code must not call that relation; CorpusExporter already works around it. Consider a 2-line fix (`orderBy('id')`) in this phase or explicitly defer.
2. **Corpus files deleted by tests**: `ExportAiCorpusTest::setUp()` unlinks `storage/app/ai-corpus/{catalog,policies}.json` → run `php artisan ai:export-corpus` before any live sidecar session (documented in CONTEXT, re-verified).
3. **Faker policy content**: policy corpus is placeholder data; CHAT-04 answers are demo-quality until real rulebook data lands. Not a bug; set expectations in UAT.
4. **Bibliographic-only catalog**: no abstract/content on AcademicPaper — catalog chat can answer "which papers exist / by whom / which department/year", NOT "what does this paper say". D-34/D-35 implication; don't over-promise in widget copy or tests.
5. **`Http::toStream()` does not exist** in Laravel 13 — use `Response::resource()`. Any plan step quoting `toStream()` will fail.
6. **No streaming test assertions in Livewire 4.4.0** — don't plan `assertStreamed`; assert state + DB instead, and verify streaming via faked resource + optional live smoke.
7. **Retry on streamed POST** duplicates LLM calls on 5xx (retry re-issues the request). Use `retries: 0` (or 1 with `when: fn($resp) => $resp->status() === 429` — defer 429 mapping to Phase 14 per D-06).
8. **401 code contract change (Δ2)** is breaking — update `test_api.py:60` in the same commit as the sidecar change (sidecar tests are the guard).
9. **`SIDECAR_TOKEN=smoke-test-token`** in both `.env` files — fine for dev, must be replaced before prod (Phase 14 hardening; flag for operator).
10. **OpenRouter key must be rotated** — the live key sits in `ceit-ai-sidecar/.env` (gitignored); never echo it into code/tests/docs.
11. **`wire:navigate` SPA** — FAB/drawer state across page transitions; use `wire:persist` or accept re-mount; test navigation in widget feature tests (at minimum assert component renders on a page via layout).
12. **Error event mid-stream**: 200 already sent; the parser must not append `event: error`/`data: {...error...}` as answer content; failed turn → inline amber banner + Retry (D-29), provider exception typed Δ9.
13. **`question` mode has no grounding/refusal instruction** (ported as-is per ADR 0003) — widget default mode is `citations` (D-02) so D-24's single grounding rule holds for the UI; don't expose `question` mode in the widget without revisiting (agent discretion).
14. **Citation binding depends on identical ranking** between `/chat/stream` (k=60, limit=top_k) and `/search` (k=60, limit=top_k) — verified identical today; if either side changes k/limit, D-20 breaks silently. Add a fixture-level test that asserts the widget passes the same corpus/top_k to both calls.

---

## 6. Validation Architecture

### 6.1 Test infra table

| Layer | Framework | Quick command | Full suite | Current size / duration | Notes |
|---|---|---|---|---|---|
| Laravel app | PHPUnit 13 (Pest-enabled, PHPUnit-style attrs) | `php artisan test --filter AiServiceTest` (or any class name) | `php artisan test` | **523 passed / 3 skipped (1487 assertions), 50.6s** | sqlite `:memory:`; `CACHE_STORE=array`; `Http::fake` with `tests/fixtures/ai-sidecar/*.json`; `Log::shouldReceive` (Mockery); `Livewire::test(Class)` |
| Sidecar | pytest | `uv run pytest tests/test_chat_stream.py` | `uv run pytest` (root of `ceit-ai-sidecar`) | **42 tests**, seconds (deterministic hash embedder, no model downloads) | `TestClient` + monkeypatched `main_mod` globals; fake OpenAI client injection |
| Live sidecar round-trip | PHPUnit gated | `SIDECAR_LIVE_TEST=1 php artisan test --filter SidecarLiveTest` | — | 1 test (skipped by default) | export → rebuild → search; **never calls the LLM** (no chat step) |
| Manual smoke | curl | `curl -N -H "X-Sidecar-Token: $token" -d '{"query":"school ID"}' http://127.0.0.1:8310/chat/stream` | — | — | real OpenRouter path — the ONLY sanctioned live-LLM check (see 6.4) |

### 6.2 Sampling rates (per-task recommendations)

| Task size | Policy |
|---|---|
| Sidecar contract fix (any Δ1-Δ5 commit) | **100% of `tests/test_chat_stream.py` + `tests/test_api.py` + `tests/test_rag.py`** (`uv run pytest tests/` — whole sidecar suite is seconds; just run all 42) |
| AiService changes | 100% of `AiServiceTest.php` + new chatStream tests each task; full suite after the refactor commit (D-09 touches the shared path every method uses) |
| Schema + models | 100% of new `ConversationTest`/`MessageTest` + `php artisan migrate:fresh --env=testing` sanity; full suite at phase end |
| Widget | 100% of new widget test file per task; full suite at phase end (once) |
| Cross-cutting (citations binding) | new binding test at 100%; full suite at phase end (once) |
| End of phase gate | full `php artisan test` + full `uv run pytest` + optional live smoke (key rotated first) |

Full Laravel suite costs ~51s — cheap enough for a full run per phase-closing commit, not per task.

### 6.3 Per-task verification map (REQ-IDs → test seams)

| REQ-ID | Deliverable | Primary seam | Assertion examples |
|---|---|---|---|
| **SEARCH-03** (numbered citations → real records) | Citation payload (D-20) + render (D-21/D-22) | Sidecar: prompt wiring test (exists); Laravel: `AiServiceChatTest` (new) + widget render test (new) | chatStream POSTs `{query, mode, corpus, top_k}`; companion `/search` called with SAME corpus+top_k; payload rows `{n, id, corpus, title, url, catalog_code}`; catalog chip `<a href="/academic-papers/{id}">` renders title + code; policy chip renders title only, non-link |
| **SEARCH-04** (refusal on empty retrieval) | Programmatic refusal (D-23/D-24) | Sidecar `test_chat_stream.py` (new test) | empty results → body is exactly `data: I don't have enough information\n\n` + `data: [DONE]\n\n`; fake client `.calls == []` (no LLM call); verbatim string locked |
| **CHAT-01** (widget, streamed) | chatStream + widget streaming (D-10/D-11) | Laravel: `AiServiceChatTest` (fake SSE via `Http::fake([... => Http::response("data: a\n\ndata: [DONE]\n\n", 200, ['Content-Type'=>'text/event-stream'])])` then `fgets($response->resource())`); widget test (Livewire::test, Http::fake) | chunks read in order; `[DONE]` terminates; `event: error` → `AiServiceProviderException` with message; widget persists user+assistant messages, clears `$streaming`, renders streamed content; typing-dots present while streaming (view assert) |
| **CHAT-02** (persisted history) | Schema + models (D-13..D-19) + list UI (D-30..D-33) | Model tests (new `ConversationTest`/`MessageTest`); widget list test | lazy `ai_conversations` row on first message; auto-title from first user msg (truncate 120); fallback `New conversation`; messages ordered `(conversation_id, id)` asc; list by `updated_at` desc (`touch()` on new message); cascade delete on user/conversation; `auth` scoping (user A cannot see user B's conversations); entry shows ~40-char title + relative time |
| **CHAT-04** (policy Q&A grounded) | corpus=policy flow end-to-end | Sidecar: `test_chat_stream.py` (extend to corpus=policy → `FakeEngine.calls` corpus forwarded — already covered); Laravel: widget policy-chip render test; optional live smoke | `corpus: policy` forwarded to sidecar; policy citation rendered as non-link chip with header title; D-07 422 test for `corpus: "bogus"`; faker-data caveat documented in test comments |

**Existing tests that break under the contract fixes (must be updated in the same commit):** `tests/test_api.py:60` (401 code) and `tests/test_chat_stream.py:206` (error event body). Everything else is additive.

### 6.4 LLM isolation strategy (tests must never hit OpenRouter)

- **Verified current state:** sidecar tests are 100% offline — `RagService(client=FakeCompletionsHolder(...))` injection (`test_chat_stream.py:93-97`, `test_rag.py:68-75`); `_ensure_client()` lazily constructs the real client only when not injected, so no test path ever reaches OpenRouter. **There is no real-engine regression test in pytest** — the real-OpenRouter verification was the pre-planning smoke test (documented in CONTEXT specifics), not a CI artifact.
- **Recommendation (keep + extend):**
  1. Sidecar: keep fake-client injection as the ONLY chat test path. Extend the smoke-test discipline into a skipped-by-default live test mirroring `SidecarLiveTest` (e.g., `test_chat_stream_live.py` gated by `SIDECAR_LIVE_CHAT_TEST=1` + a real key), so the real-LLM regression check is reproducible but never runs in CI.
  2. Laravel: `Http::fake()` + `Http::preventStrayRequests()` in every widget/AiService test (pattern already used in `AiServiceTest`) — a stray request to `http://127.0.0.1:8310/*` throws `StrayRequestException`, making LLM/sidecar leaks fail loudly.
  3. Never put the real API key in fixtures, config files, or test code; `config(['services.ai_sidecar.token' => 'test-token'])` per test (existing convention).
  4. Live smoke (manual or `SIDECAR_LIVE_TEST=1`): run only after `php artisan ai:export-corpus` (corpus files are deleted by tests — §5.2) and with a rotated key.

---

## 7. Key File Map for the Planner

| File | Role |
|---|---|
| `ceit-ai-sidecar/app/main.py` | `/chat/stream` endpoint, 401 middleware, validation |
| `ceit-ai-sidecar/app/rag.py` | PROMPTS, build_context, stream_events (D-04/D-23 edits) |
| `ceit-ai-sidecar/app/search.py` | rrf_search (include_text, k=60 — citation-ordering ground truth) |
| `ceit-ai-sidecar/tests/{conftest,test_chat_stream,test_api,test_rag}.py` | test seams + 2 breaking assertions |
| `CEIT-Library/app/Services/AiService.php` | D-09 refactor + D-10 chatStream |
| `CEIT-Library/app/Exceptions/` | + `AiServiceProviderException` (D-12) |
| `CEIT-Library/app/Services/CorpusExporter.php` | doc shapes for citation payload mapping |
| `CEIT-Library/app/Models/{RuleHeader,RuleRegulation,AcademicPaper}.php` | R10, corpus metadata |
| `CEIT-Library/routes/web.php` | `auth,verified` group, `academic-paper.show` |
| `CEIT-Library/bootstrap/app.php` | CheckAccountStatus global (D-25 free) |
| `CEIT-Library/resources/views/components/layouts/{app,admin}.blade.php` | widget mount seams (D-27) |
| `CEIT-Library/database/migrations/2026_06_15_234457_*.php` | migration style template (D-13/D-14) |
| `CEIT-Library/tests/Feature/{AiServiceTest,AcademicPaperIndexHybridTest,ExportAiCorpusTest,SidecarLiveTest}.php` | test patterns to clone |
| `CEIT-Library/tests/fixtures/ai-sidecar/search.json` | citation-binding mock |
| `prototype/chat-widget` @ `395244dc` | widget markup verdict source (throwaway) |
| `docs/adr/0001..0009` | locked decisions (all present) |

---

*Research complete — all facts verified against live code on 2026-08-14.*
