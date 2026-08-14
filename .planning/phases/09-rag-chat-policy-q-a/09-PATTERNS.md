# Phase 9: RAG Chat & Policy Q&A — Pattern Mapping

**Mapped:** 2026-08-14 (read both sides of every analog pair; excerpts are verbatim from live code)
**Source docs:** `09-CONTEXT.md` (locked decisions ADR 0001-0009, D-01..D-35) · `09-RESEARCH.md` (verified state, Δ1-Δ11, §7 key file map)
**Rule:** Research only — no files edited except this one.

---

## 1. File Inventory & Data Flow

### 1.1 Files to CREATE

| # | File | Role | Closest analog |
|---|---|---|---|
| 1 | `database/migrations/2026_08_14_000001_create_ai_conversations_table.php` | schema (D-13) | `2026_06_15_234457_create_notification_preferences_table.php` |
| 2 | `database/migrations/2026_08_14_000002_create_ai_messages_table.php` | schema (D-14) | `2026_05_21_000001_create_push_subscriptions_table.php` |
| 3 | `app/Models/Conversation.php` | model (D-15) | `app/Models/NotificationPreference.php` |
| 4 | `app/Models/Message.php` | model (D-15) | `app/Models/PushSubscription.php` |
| 5 | `database/factories/ConversationFactory.php` / `MessageFactory.php` | test data (D-13/D-14) | `database/factories/RuleRegulationFactory.php` |
| 6 | `app/Exceptions/AiServiceProviderException.php` | typed exception (D-12) | `app/Exceptions/AiServiceAuthException.php` |
| 7 | `app/Livewire/ChatWidget.php` | widget component (ADR 0008) | `app/Livewire/QrScanner.php` (non-page shape) + `app/Livewire/Pages/Prototype/ChatWidgetPrototype.php` (state machine, @ `395244dc`) |
| 8 | `resources/views/livewire/chat-widget.blade.php` + `chat-widget-citations.blade.php` + `chat-widget-sources.blade.php` | widget views (D-27..D-29, D-21/D-22) | `resources/views/livewire/pages/prototype/chat-widget-{variant-a,citations,sources}.blade.php` @ `395244dc` (verbatim verdict markup) |
| 9 | `tests/Feature/AiServiceChatTest.php` | service tests (D-10/D-11/Δ6-Δ11) | `tests/Feature/AiServiceTest.php` |
| 10 | `tests/Feature/ChatWidgetTest.php` | widget tests (D-18..D-33) | `tests/Feature/AcademicPaperIndexHybridTest.php` conventions + `tests/Feature/AiServiceTest.php` fakes |
| 11 | `tests/Feature/ConversationMessageTest.php` | model/schema tests | `tests/Feature/SidecarLiveTest.php` (structure) + model-test conventions |
| 12 | `tests/fixtures/ai-sidecar/chat-stream.txt` (SSE body fixture) | fake stream resource | `tests/fixtures/ai-sidecar/search.json` (fixture convention) |

### 1.2 Files to MODIFY

| # | File | Change | Analog |
|---|---|---|---|
| 13 | `ceit-ai-sidecar/app/main.py` | Δ2 401 code `invalid_request`→`auth_failed`; Δ3 corpus value validation; Δ5 stream headers | `_invalid()`/`_require_query()` helpers (same file) |
| 14 | `ceit-ai-sidecar/app/rag.py` | Δ1 JSON error event; Δ4 empty-retrieval refusal branch | `stream_events()` (same file) |
| 15 | `ceit-ai-sidecar/tests/test_api.py` | Δ2: `:60` asserts old code | test_api assertion style (same file) |
| 16 | `ceit-ai-sidecar/tests/test_chat_stream.py` | Δ1: `:206` asserts old body; + tests for Δ3/Δ4 | `make_client()` seam (same file) |
| 17 | `app/Services/AiService.php` | Δ6 `request()` helper refactor; Δ7 `chatStream()`; Δ11 SSE parser | `send()` + `search()` (same file) |
| 18 | `resources/views/components/layouts/app.blade.php` | D-27: mount `<livewire:chat-widget />` before `</body>` | `<livewire:layout.user-menu />` embed (`app.blade.php:139`) + `<x-mary-toast />` (`:279`) |
| 19 | `resources/views/components/layouts/admin.blade.php` | D-27: same mount | `admin.blade.php:204` `<x-mary-toast />` slot |

### 1.3 Data flow (one request turn)

```
user types draft ──▶ ChatWidget::send()
  1. lazy-create ai_conversations row (auto-title D-18) + persist user Message
  2. companion (new AiService)->search($query, [], $corpus, $topK)  ──▶ POST /search ──▶ rrf_search(k=60, limit=top_k)
        └─▶ citation payload [{n,id,corpus,title,url,catalog_code}] from metadata (D-20)
  3. (new AiService)->chatStream($query, $mode, $corpus, $topK)   ──▶ POST /chat/stream
        └─▶ sidecar: rrf_search(k=60, limit=top_k, include_text=True) ──▶ RagService.stream_events()
              └─▶ SSE data: <chunk> ... data: [DONE] | event: error {code,message}
  4. widget: fgets($response->resource()) per line; $this->stream($chunk) per data: chunk (Δ8)
  5. persist assistant Message (content + citations JSON); Conversation::touch() (D-16)
  6. render: bubbles + citation chips + Sources list (D-21/D-22); refusal renders as normal bubble (D-29)
```

---

## 2. Analog Mappings

### 2.1 Migration pattern → `ai_conversations` / `ai_messages`

**ANALOG A:** `database/migrations/2026_06_15_234457_create_notification_preferences_table.php` (user-owned row, composite unique — closest to `ai_conversations`)

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('category'); // reminders, role_changes, account_alerts
            $table->boolean('email')->default(false);
            $table->boolean('push')->default(false);
            $table->boolean('in_app')->default(true); // Always true
            $table->timestamps();
            $table->unique(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
```

**ANALOG B:** `database/migrations/2026_05_21_000001_create_push_subscriptions_table.php` (FK-child table — closest to `ai_messages`)

```php
Schema::create('push_subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->text('endpoint');
    $table->string('public_key');
    ...
    $table->timestamps();
});
```

**REPLICATE:** anonymous-class migration; `$table->id()`; `foreignId()->constrained()->onDelete('cascade')`; column-order comments (`// reminders, role_changes...`); `$table->timestamps()`; index/unique declared at the END of the closure; `Schema::dropIfExists` in `down()`. Inline `//` comment on columns whose values are opaque (D-14 `role` enum → `// user|assistant`, `citations` → comment the payload shape `// [{n,id,corpus,title,url,catalog_code}]`).

**CHANGE:** 
- `ai_conversations`: add `$table->string('title', 120)->nullable()` (D-13); index is `(user_id, updated_at)` — `$table->index(['user_id', 'updated_at'])` (no unique — multiple conversations per user).
- `ai_messages`: `foreignId('conversation_id')->constrained('ai_conversations')->onDelete('cascade')` (foreignId constrained to a non-default table name — the first arg is the column; `->constrained()` infers `ai_conversations` from the column name automatically); `$table->enum('role', ['user', 'assistant'])`; `$table->text('content')`; `$table->json('citations')->nullable()`; index `(conversation_id, id)` — `$table->index(['conversation_id', 'id'])`. Column order per D-14. Migration filenames/first lines are agent discretion (CONTEXT §specifics).
- No soft deletes / no unique constraint (D-17).

### 2.2 Model pattern → `Conversation` / `Message`

**ANALOG A:** `app/Models/NotificationPreference.php` (owner relation + casts + fillable — the full house style)

```php
class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'email',
        'push',
        'in_app',
    ];

    protected function casts(): array
    {
        return [
            'email' => 'boolean',
            'push' => 'boolean',
            'in_app' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**ANALOG B:** `app/Models/PushSubscription.php` — same shape, no casts.

**REPLICATE:** namespace `App\Models`; plain `Model` subclass; docblock-tagged `$fillable`; `casts()` method (Laravel 13 style, not `$casts` property); typed `BelongsTo` return. Eloquent infers tables `ai_conversations`/`ai_messages` from class names `Conversation`/`Message` — NO `$table` override needed (D-15).

**CHANGE:**
- `Message`: `casts()` → `['citations' => 'array']` (D-14 JSON); relations: `conversation(): BelongsTo` (→ `Conversation::class`); **no** `user()` (single owner via conversation — D-15; auth-scoped loads happen through `Conversation`).
- `Conversation`: `messages(): HasMany` (`->orderBy('id')` — flat ordering D-16) and `user(): BelongsTo`; `$fillable` = `['user_id', 'title']`; **no** soft deletes (D-17).
- Ownership scoping precedent: none of the analog models scope queries themselves — scoping lives in the component (`ChatWidget` queries `Conversation::where('user_id', auth()->id())->orderByDesc('updated_at')` — D-16/D-30, mirroring `AcademicPaperIndex` query-building in the component, §2.4).

### 2.3 Factory pattern → `ConversationFactory` / `MessageFactory`

**ANALOG:** `database/factories/RuleRegulationFactory.php` (child-of-parent factory)

```php
class RuleRegulationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rule_header_id' => RuleHeader::factory(),
            'content' => $this->faker->paragraph(2),
        ];
    }
}
```

**REPLICATE:** `definition()` returning parent FK via `Parent::factory()`. `MessageFactory`: `'conversation_id' => Conversation::factory()`, `'role' => 'assistant'`, `'content'` faker paragraph, `'citations'` optional `null`. `ConversationFactory`: `'user_id' => User::factory()`, `'title'` faker sentence truncated (or null). Docblock `@extends Factory<Model>`.

### 2.4 Service extension → `AiService` (Δ6-Δ11)

**ANALOG A:** current `send()` gateway (`app/Services/AiService.php:45-65`) — the private helper `request()` clones this:

```php
private function send(string $method, string $path, array $body, int $timeout, int $retries): array
{
    try {
        $request = Http::withHeaders(['X-Sidecar-Token' => config('services.ai_sidecar.token')])
            ->baseUrl(config('services.ai_sidecar.base_url'))
            ->connectTimeout(3)
            ->timeout($timeout)
            ->retry($retries, 250, throw: false);

        $response = $method === 'POST'
            ? $request->post($path, $body)
            : $request->get($path);
    } catch (ConnectionException $e) {
        $this->logFailure($path, 'connection');
        throw new AiServiceUnavailableException('AI sidecar is unavailable (connection failed).', 0, $e);
    }

    $this->throwUnlessOk($response, $path);

    return $response->json() ?? [];
}
```

**ANALOG B:** public method shape (`search()`, `AiService.php:19-28`):

```php
public function search(string $query, array $filters = [], ?string $corpus = 'catalog', int $limit = 10): array
{
    return $this->send('POST', '/search', [
        'query' => $query,
        'filters' => $filters,
        'corpus' => $corpus,
        'limit' => $limit,
        'k' => self::RRF_CANDIDATES,
    ], timeout: 10, retries: 2);
}
```

**ANALOG C:** `throwUnlessOk()` (`AiService.php:67-78`) — 401 → `AiServiceAuthException`, everything else → `AiServiceUnavailableException`.

**ANALOG D:** typed exception clone (`app/Exceptions/AiServiceAuthException.php`):

```php
namespace App\Exceptions;

use RuntimeException;

class AiServiceAuthException extends RuntimeException
{
}
```

**REPLICATE:**
- Δ6: extract `private function request(string $method, string $path, array $body, int $timeout, int $retries, bool $stream = false): PendingRequest` containing the header/baseUrl/connectTimeout/timeout/retry chain + method dispatch; `send()` becomes `$this->request(...)->post/get(...)` — **behavior-identical** so the 9 existing `AiServiceTest` cases are the regression net.
- Δ7: `chatStream(string $query, ?string $mode = 'citations', ?string $corpus = null, int $topK = 5): Response` — POST `/chat/stream` with `{query, mode, corpus, top_k}`; body skips `corpus` when null; `withOptions(['stream' => true])`; call `throwUnlessOk()` FIRST (before touching the body), then return the `Response` (D-10). Keep `logFailure()` sanitized-shape convention (endpoint + reason only — `AiService.php:80-83`).
- Δ9: `AiServiceProviderException extends RuntimeException` — verbatim copy of the 9-line exception file, new name.
- Δ11: SSE line parser (agent discretion per CONTEXT): read `fgets()` lines off `$response->resource()`; `data: <chunk>` → accumulate/yield; `event: error` → decode JSON body, throw `AiServiceProviderException` with `message`; `data: [DONE]` → stop; trailing `[DONE]` after error ignored. RESEARCH: `Http::toStream()` does NOT exist in Laravel 13 — the method is `Response::resource()` (`Response.php:169-175`); `Http::fake()` accepts plain string bodies as the stream resource.

**CHANGE:**
- `chatStream` uses `retries: 0` (Δ10 — retry re-issues the whole POST → duplicate LLM generation on 5xx; `retry($retries, 250, throw: false)` in the shared helper must be parameterized, not assumed).
- Mode default `'citations'` (D-02), not nullable — the widget must not expose `question` mode (risk 13 in RESEARCH).

### 2.5 Livewire component → `ChatWidget`

**ANALOG A:** non-page component conventions — `app/Livewire/QrScanner.php`:
- lives at `app/Livewire/` root (not `Pages/`), extends `Livewire\Component`, no `#[Title]`/`#[Layout]` (page attributes — a layout-embedded component gets neither), `use Mary\Traits\Toast`, `render()` → `view('livewire.qr-scanner')`.
- action methods mutate public state + dispatch browser events: `$this->dispatch('attendanceRecorded', attendance: $result['attendance'])` (`QrScanner.php:81`); `$this->hasError = true` error-state pattern; `$this->redirect(route(...))` for guard failures (`QrScanner.php:43`).

**ANALOG B:** page conventions to borrow for querying/scoping — `app/Livewire/Pages/Student/AcademicPaperIndex.php`:
- `#[Computed]` properties for derived reads (`AcademicPaperIndex.php:99-172`); sidecar call wrapped in `try/catch (AiServiceUnavailableException|AiServiceAuthException)` → `$this->aiSearchFailed = true` silent fallback (`:296-303`) — the established sidecar-failure pattern the widget's `retry()`/amber banner (D-29) builds on.
- `public function updatedSearch()` live-recompute hooks (`:217-221`).

**ANALOG C:** the state machine + streaming loop — `app/Livewire/Pages/Prototype/ChatWidgetPrototype.php` @ `395244dc` (throwaway, delete after planning):
- public props: `array $messages` (local render array), `string $draft`, `bool $streaming`, `bool $open` (`ChatWidgetPrototype.php:29-38`).
- `send()` guard: `if ($question === '' || $this->streaming) return;` — append user message, clear draft, call `streamAnswer()` (`:128-137`).
- streaming: `$this->messages[] = ['role' => 'assistant', 'content' => '', ...]` then per chunk `$this->stream($chunk, false, 'ans')`, finally `$this->messages[$idx]['content'] = $accumulated; $this->streaming = false;` (`:154-180`).
- `retry()`: find last user message, drop trailing failed assistant message (replace, not duplicate — D-29), re-stream (`:100-124`).
- failure bubble: `$this->messages[$idx]['error'] = ['code' => 'provider_error', 'message' => ...]` (`:181-186`) — mirrors ADR 0004 event payload.
- `render()` → `view('livewire.pages.prototype.chat-widget-prototype')`.

**REPLICATE:** class at `app/Livewire/` root; the full prototype prop/action vocabulary (`messages`, `draft`, `streaming`, `open`, `send`, `retry`, `toggle`, `newConversation`, `ask`); replace `fakeAnswer()` with real pipeline: lazy-create conversation (D-32), companion `search()` (D-20), `chatStream()` + `fgets(resource())` loop (Δ8) with `$this->stream(content: $chunk, replace: false)` (valid — Livewire 4.4 `stream($content, $replace = false, $name = null, $el = null, $ref = null)`, positional-first per RESEARCH §2.6). Add `$view` (`list`|`chat`), `$activeConversationId` (D-30/D-31), `$query` and drawer-open state; `mount()` loads conversations by `user_id` ordered `updated_at desc` (D-30 list default); persist user+assistant `Message` rows with `citations` JSON.

**CHANGE:** `[#Lazy]` NOT recommended on the widget (RESEARCH §4.4 — FAB pop-in under `wire:navigate`); consider `wire:persist` for SPA transitions (RESEARCH risk 11); no `#[Title]`/`#[Layout]`; no `Toast` needed (inline bubbles, not toasts — D-29); no `WithPagination`; `$streaming` guard against concurrent sends.

### 2.6 Blade component conventions → widget views

**ANALOG (verbatim verdict markup):** `resources/views/livewire/pages/prototype/chat-widget-variant-a.blade.php` @ `395244dc`:

```blade
@if (! $open)
    <button type="button" wire:click="toggle" class="fixed bottom-6 right-6 z-40 btn btn-circle btn-primary shadow-2xl w-16 h-16" title="Open chat">
```

```blade
<div class="fixed inset-y-0 right-0 z-40 w-full sm:w-96 bg-base-100 border-l border-base-300 shadow-2xl flex flex-col transition-transform duration-300 {{ $open ? '' : 'translate-x-full' }}">
    {{-- header: title + New + close --}}
    ...
    @if ($streaming && $loop->last)
        <div wire:stream="ans"></div>
    @else
        <div class="whitespace-pre-line">{{ $m['content'] }}</div>
    @endif
    ...
    @if (! empty($m['error']))
        <div class="mt-2 rounded-lg bg-amber-50 border border-amber-300 px-3 py-2 text-xs text-amber-800 ...">
            <span>{{ $m['error']['message'] }}</span>
            <button type="button" wire:click="retry" class="btn btn-xs btn-warning">Retry</button>
        </div>
    @endif
    ...
    <form wire:submit="send" class="border-t border-base-200 p-3 flex items-end gap-2 bg-base-100">
        <textarea wire:model="draft" rows="1" class="textarea textarea-bordered textarea-sm flex-1 resize-none text-sm" ... @if ($streaming) disabled @endif></textarea>
```

Citations partial (`chat-widget-citations.blade.php`) — the D-21 split:

```blade
@if ($c['corpus'] === 'catalog' && $c['url'])
    <a href="{{ $c['url'] }}" class="inline-flex items-center gap-1 rounded-full border border-primary/40 bg-primary/5 text-primary px-2.5 py-0.5 text-[11px] hover:bg-primary/10">
        [{{ $c['n'] }}] {{ $c['title'] }} <span class="opacity-60 font-mono">{{ $c['catalog_code'] }}</span>
    </a>
@else
    <span class="inline-flex items-center gap-1 rounded-full border border-base-300 bg-base-100 px-2.5 py-0.5 text-[11px] text-base-content/80">
        [{{ $c['n'] }}] {{ $c['title'] }}
    </span>
@endif
```

Sources partial (`chat-widget-sources.blade.php`) — D-22: `<ol>` of `[n]` + link (catalog, `link link-primary`) or plain title + `(rulebook)` suffix; dashed top border, `text-[10px] uppercase tracking-wide` "Sources" label.

**REPLICATE:** FAB `btn btn-circle btn-primary fixed bottom-6 right-6 z-40 w-16 h-16` (D-27); drawer `fixed inset-y-0 right-0 z-40 w-full sm:w-96` + `translate-x-full` toggle (D-27); bubble classes `bg-primary text-primary-content rounded-2xl rounded-br-sm px-4 py-2 max-w-[80%] text-sm` (user) / `bg-base-200 ... max-w-[85%]` (assistant, D-28); `wire:stream="ans"` insertion point; typing dots `animate-bounce [animation-delay:150ms]`; amber failure banner + `btn btn-xs btn-warning` Retry; `textarea textarea-bordered` + disabled-while-`$streaming`. Move partials to `resources/views/livewire/` (component namespace) and `@include('livewire.chat-widget-citations', ['citations' => $m['citations']])`. These views sit with the other DaisyUI/Mary-UI classes used across the app (e.g. `x-mary-toast`, `badge badge-primary badge-sm` in `app.blade.php:182`).

**MOUNT SEAM (layouts):** embed `<livewire:chat-widget />` immediately before `<x-mary-toast />` near `</body>` — the existing component-in-layout precedents are `<livewire:layout.user-menu />` (`app.blade.php:139`, `admin.blade.php:36`) and `<x-pwa-install-banner />` (`app.blade.php:282`). Both `app` and `admin` layouts get it (D-27; welcome page is public — no FAB).

### 2.7 Feature test pattern → AiService chat tests + widget tests

**ANALOG A:** `tests/Feature/AiServiceTest.php` — the full fake stack:

```php
private function fixture(string $name): string
{
    return file_get_contents(base_path('tests/fixtures/ai-sidecar/'.$name));
}

#[Test]
public function it_posts_search_with_token_and_locked_payload(): void
{
    config(['services.ai_sidecar.token' => 'test-token']);

    Http::preventStrayRequests();
    Http::fake([
        'http://127.0.0.1:8310/search' => Http::response(json_decode($this->fixture('search.json'), true), 200),
    ]);

    (new AiService)->search('water pump', ['department' => 'Civil Engineering'], 'catalog', 10);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/search')
            && $request->hasHeader('X-Sidecar-Token', 'test-token')
            && $request['query'] === 'water pump'
            && $request['corpus'] === 'catalog'
            && $request['limit'] === 10
            && $request['k'] === 60;
    });
}
```

Plus: `Log::shouldReceive('warning')->once()->with('AI sidecar request failed', \Mockery::on(...))` sanitized-context assertions (`:104-113`); `Http::sequence()->push([], 500)->push(...)` retry tests (`:171-175`); `Http::fake([...] => fn () => throw new ConnectionException('Connection refused')])` (`:75`); `expectException(AiServiceAuthException::class)` (`:66`).

**ANALOG B (SSE fake):** `Http::fake` accepts a plain string body → fake the stream: `Http::fake(['http://127.0.0.1:8310/chat/stream' => Http::response("data: CEIT\n\ndata: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream'])])`, then `$stream = (new AiService)->chatStream(...); while (! feof($stream->resource())) { $line = fgets($stream->resource()); ... }` — RESEARCH §6.3 CHAT-01 seam. New fixture `tests/fixtures/ai-sidecar/chat-stream.txt` holds the SSE body.

**ANALOG C (env-gated live):** `tests/Feature/SidecarLiveTest.php`:

```php
#[Group('sidecar')]
class SidecarLiveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (env('SIDECAR_LIVE_TEST') !== '1') {
            $this->markTestSkipped('Set SIDECAR_LIVE_TEST=1 to run live sidecar round-trip tests.');
        }
    }
    #[Test]
    public function export_rebuild_search_round_trip(): void { ... }
}
```

**REPLICATE:** `config(['services.ai_sidecar.token' => 'test-token'])` + `Http::preventStrayRequests()` in every chat/widget test (stray sidecar hits throw); assert companion `/search` called with SAME corpus+top_k as the chat POST (citation binding — RESEARCH risk 14); widget tests via `Livewire::test(ChatWidget::class)` + `Http::fake` + seeded `Conversation` (forceFill id to match fixtures, per `AcademicPaperIndexHybridTest`); **no streaming assertions exist in Livewire 4.4.0** — assert post-call DB persistence (`assertDatabaseHas('ai_messages', [...])`), `$streaming` cleared, and stream parsing at the AiService layer via `resource()` (RESEARCH §2.6, risk 6).

**CHANGE:** widget tests need `actingAs(User::factory()->create())` for the `auth`-scoped loads (D-15); user B must not see user A's conversations; new `SidecarLiveChatTest` (env-gated, mirrors ANALOG C) must NOT exist as a CI path — live LLM checks stay manual smoke only.

### 2.8 Sidecar contract fixes → main.py / rag.py

**ANALOG A:** validation helpers in `ceit-ai-sidecar/app/main.py` — Δ2/Δ3 follow them:

```python
def _invalid(message: str) -> JSONResponse:
    return JSONResponse(
        status_code=422,
        content={"error": {"code": "invalid_request", "message": message}},
    )


def _require_query(payload: dict) -> str | JSONResponse:
    query = payload.get("query")
    if not isinstance(query, str) or not query.strip():
        return _invalid("'query' is required")
    return query
```

Mode/top_k validation in `chat_stream` shows the exact in-place pattern (`main.py:120-129`):

```python
mode = payload.get("mode", "citations")
if not isinstance(mode, str) or mode not in ("citations", "question", "rag"):
    return _invalid("'mode' must be citations, question or rag")
corpus = payload.get("corpus") or None
try:
    top_k = int(payload.get("top_k", 5))
except (TypeError, ValueError):
    return _invalid("'top_k' must be an integer")
if top_k < 1 or top_k > 50:
    return _invalid("'top_k' must be between 1 and 50")
```

**ANALOG B:** token middleware (`main.py:66-79`) — constant-time `secrets.compare_digest`, 401 JSON envelope; Δ2 changes one literal: `"code": "invalid_request"` → `"code": "auth_failed"` at `:74`.

**ANALOG C:** `stream_events()` in `app/rag.py:148-158` — the Δ1/Δ4 edit point:

```python
def stream_events(self, query: str, results: list[dict], mode: str = "citations") -> Iterator[str]:
    """SSE-framed events: `data: <chunk>` lines, `[DONE]` terminator, or
    an `event: error` line on provider failure (ADR 0002 framing)."""
    try:
        for delta in self.stream_answer(query, results, mode):
            yield f"data: {delta}\n\n"
    except Exception as exc:  # noqa: BLE001 - provider errors become SSE error events
        yield f"event: error\ndata: {type(exc).__name__}\n\n"
    yield "data: [DONE]\n\n"
```

**REPLICATE/CHANGE:**
- Δ1: replace the error line with `yield f"event: error\ndata: {json.dumps({'code': 'provider_error', 'message': 'The AI provider is temporarily unavailable. Please try again.'})}\n\n"`; `logger.error(repr(exc))` server-side only; keep `[DONE]` last (RESEARCH §4.1).
- Δ4: add empty-retrieval branch at the TOP of `stream_events` (so the internal `answer()` seam also refuses — RESEARCH §4.1): `if not results: yield "data: I don't have enough information\n\n"; yield "data: [DONE]\n\n"; return` — NO client construction, no score threshold (D-23/D-24).
- Δ3: in `chat_stream`, after `corpus = payload.get("corpus") or None`: `if corpus is not None and corpus not in ("catalog", "policy"): return _invalid("'corpus' must be catalog, policy or omitted")`.
- Δ5 (optional): `StreamingResponse(events, media_type="text/event-stream", headers={"Cache-Control": "no-cache", "X-Accel-Buffering": "no"})` — parity with Livewire stream headers.

### 2.9 Sidecar test pattern → updated + new assertions

**ANALOG A:** `tests/test_chat_stream.py` — `make_client()` seam (monkeypatched `main_mod.settings`, `_search_engine = FakeEngine(...)`, `_rag = RagService(client=FakeCompletionsHolder(content, fail), ...)`, `build_test_index`, `embed_from`):

```python
main_mod.settings = settings
main_mod._search_engine = FakeEngine(engine_results)
main_mod._rag = RagService(
    client=FakeCompletionsHolder(content, fail),
    model="test-model",
    max_tokens=64,
)
```

with `FakeEngine` recording calls (`self.calls.append({"query":..., "corpus":..., "limit":..., "include_text":...})`) and assertions like `assert engine.calls == [{"query": "school ID", "corpus": "policy", "limit": 3, "include_text": True}]` (`:143-145`).

**ANALOG B:** breaking assertions to update in the same commit (RESEARCH §6.3):
- `test_api.py:56-60`: `assert resp.json()["error"]["code"] == "invalid_request"` → `"auth_failed"` (Δ2).
- `test_chat_stream.py:197-208`: `assert "RuntimeError" in resp.text` → assert `resp.text` contains `event: error`, `"code": "provider_error"` (JSON), and NOT the exception name (Δ1).
- `test_rag.py:167`: `assert any(e.startswith("event: error") for e in events)` survives; add JSON-body assertion.

**REPLICATE:** new Δ3 test (corpus `"bogus"` → 422 `invalid_request` — style of `test_chat_stream_rejects_non_numeric_top_k`, `:148-156`); new Δ4 test (empty results → body exactly `data: I don't have enough information\n\n` + `data: [DONE]\n\n` and `fake_client.chat.completions.calls == []` — no LLM call; `FakeCompletions` records `calls` in `test_rag.py:33-36`); 401-token test `test_chat_stream_requires_token` (`:106-109`) already covers the gate.

---

## 3. Anti-patterns / traps in the analogs (do NOT replicate)

1. **`toStream()` does not exist** — Laravel 13 `Response::resource()` is the stream read seam (RESEARCH §2.6). Any plan quoting `toStream()` fails.
2. **Livewire 4.4 has no streamed assertions** — do not plan `assertStreamed`; assert state/DB + AiService-level resource reads (RESEARCH §2.6).
3. **Retry duplicates LLM generation** — shared `retry()` in the `request()` helper must be parameterized; `chatStream` uses 0 (Δ10).
4. **`RuleHeader::ruleRegulations()` `orderBy('order')` throws** (R10, confirmed) — chat code must not touch that relation; optional 2-line fix `orderBy('id')` (RESEARCH §2.10).
5. **Corpus files deleted by tests** — re-run `php artisan ai:export-corpus` before live sessions; never depend on corpus presence in tests (RESEARCH §5.2).
6. **401-code change is breaking** — sidecar tests must change in the same commit as `main.py` (Δ2; RESEARCH risk 8).
7. **`question` mode has no refusal instruction** — widget default stays `citations` (D-02; RESEARCH risk 13).
8. **Sidecar error event currently leaks exception type** — the new JSON shape must keep details server-side only (Δ1; RESEARCH §2.2).
9. **Citation binding depends on identical k/limit** on both calls — add a fixture-level test asserting same corpus/top_k (RESEARCH risk 14).
10. **Never commit/echo the OpenRouter key** — lives only in `ceit-ai-sidecar/.env`, must be rotated; fixtures/tests use `config(['services.ai_sidecar.token' => 'test-token'])` (RESEARCH §6.4).

---

## 4. Strongest Patterns (summary)

1. **Migration** — anonymous-class, `foreignId()->constrained()->onDelete('cascade')`, index-at-end, `dropIfExists`; copy `notification_preferences` verbatim, swap in `title(120)->nullable()`, `enum role`, `json citations nullable`, `(user_id, updated_at)` / `(conversation_id, id)` indexes.
2. **Models** — bare 9-22-line classes: `$fillable` + `casts()` (→ `'citations' => 'array'`) + typed `BelongsTo`/`HasMany`; Eloquent infers the `ai_` tables; no `$table` override, no soft deletes.
3. **AiService** — the whole delta is one private helper refactor (`send()` → `request(method, path, body, timeout, retries, stream=false)`) + one method clone of `search()` that returns the streamed `Response` after `throwUnlessOk()`; new exception is a 9-line `RuntimeException` copy.
4. **Widget** — prototype `ChatWidgetPrototype` @ `395244dc` IS the widget: props `messages/draft/streaming/open`, `send()` guard, `$this->stream($chunk, false, 'ans')` loop, `retry()` replace-in-place, `provider_error` bubble — swap `fakeAnswer()` for search + chatStream; views are the verdict markup verbatim (FAB `btn-circle btn-primary fixed bottom-6 right-6 z-40 w-16 h-16`, drawer `w-full sm:w-96` + `translate-x-full`).
5. **Tests** — clone `AiServiceTest`'s `Http::fake` + fixture + `preventStrayRequests` + Mockery `Log::shouldReceive` stack (SSE faked as a plain string body read via `fgets($response->resource())`); update the 2 sidecar breaking assertions in the same commits as Δ1/Δ2.

## PATTERN MAPPING COMPLETE
