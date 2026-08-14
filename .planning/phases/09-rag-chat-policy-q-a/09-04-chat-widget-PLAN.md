---
phase: 09-rag-chat-policy-q-a
plan: 04
type: execute
wave: 2
depends_on: ["09-02", "09-03"]
files_modified:
  - app/Livewire/ChatWidget.php
  - resources/views/livewire/chat-widget.blade.php
  - resources/views/components/layouts/app.blade.php
  - resources/views/components/layouts/admin.blade.php
  - tests/Feature/ChatWidgetTest.php
autonomous: true
requirements: [CHAT-01, CHAT-02]
must_haves:
  truths:
    - "Widget mounts on every authenticated page via `@auth` + &lt;livewire:chat-widget /&gt; before &lt;x-mary-toast /&gt; in BOTH app and admin layouts; FAB + right drawer per ADR 0008 markup (D-27)"
    - "send() lazy-creates the ai_conversations row with Conversation::makeTitle() auto-title, persists user and assistant Messages, and streams chunks via $this->stream($chunk, false, 'ans') with typing dots (D-28/D-32/D-18, CHAT-01)"
    - "List view is the default on open; entries show ~40-char title + relative time; openConversation loads messages ascending and is auth-scoped to the current user (D-30/D-31/D-33, CHAT-02)"
    - "Provider failure renders an inline amber banner with Retry that replaces the failed turn — no duplicate assistant rows (D-29)"
  artifacts:
    - path: app/Livewire/ChatWidget.php
      provides: "Non-page Livewire component (app/Livewire root, no #[Title]/#[Layout]) with view/activeConversationId/messages/draft/streaming/open state"
      contains: "chatStreamEvents"
    - path: resources/views/livewire/chat-widget.blade.php
      provides: "FAB + drawer + bubbles + typing dots + failure banner markup (verdict variant A verbatim)"
      contains: "translate-x-full"
  key_links:
    - from: app/Livewire/ChatWidget.php
      to: app/Services/AiService.php
      via: "send()/retry() consume chatStream() + chatStreamEvents() (09-02)"
      pattern: "chatStream"
    - from: app/Livewire/ChatWidget.php
      to: app/Models/Conversation.php
      via: "lazy create + Conversation::makeTitle() + Message persistence (09-03)"
      pattern: "makeTitle"
---

<objective>
Build the in-app chat widget (ADR 0008/0009): the `ChatWidget` Livewire component at `app/Livewire/` root, its view with the floating launcher FAB + slide-in right drawer (verdict variant-A markup), the bubble layout with typing dots, the send() pipeline that lazily creates conversations, persists messages, and streams chunks from `AiService::chatStream()` + `chatStreamEvents()` via `$this->stream($chunk, false, 'ans')`, the failure banner + Retry that replaces the failed turn, the conversation list view (default on open) with open/new actions, the auth-guarded mounts in both layouts, and the `ChatWidgetTest` feature suite with the full `Http::fake` stack.

Purpose: CHAT-01 (streamed in-app chat) and CHAT-02 (persisted, viewable history) surface here. Citation chips/Sources wiring is deliberately NOT in this plan — it lands in 09-05 on top of this component (assistant messages persist with `citations => null` until then).

Output: A mounted, streaming, history-persisting widget on every authenticated page, green `ChatWidgetTest` suite.

Commit discipline: each task is one focused commit.
</objective>

<execution_context>
@$HOME/.codex/get-shit-done/workflows/execute-plan.md
@$HOME/.codex/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/09-rag-chat-policy-q-a/09-RESEARCH.md
@.planning/phases/09-rag-chat-policy-q-a/09-CONTEXT.md
@.planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md
@.planning/phases/09-rag-chat-policy-q-a/09-VALIDATION.md
@docs/adr/0007-minimal-per-user-chat-guard.md
@docs/adr/0008-chat-widget-shape.md
@docs/adr/0009-conversation-list-ui-flow.md
@app/Livewire/QrScanner.php
@app/Livewire/Pages/Prototype/ChatWidgetPrototype.php

> NOTE: The prototype files (`app/Livewire/Pages/Prototype/ChatWidgetPrototype.php`, `resources/views/livewire/pages/prototype/chat-widget-variant-a.blade.php`) are NOT in the main working tree — they live only on branch `prototype/chat-widget` (tip 395244dc). Read them via `git show prototype/chat-widget:&lt;path&gt;` (or checkout that branch) before the branch is deleted.
</context>

<threat_model>
ASVS L1. Block on HIGH severity threats. This plan exposes a persistent, user-owned chat surface on every authenticated page.

| Threat | Severity | Mitigation in this plan |
|---|---|---|
| T-01 IDOR — user B opens user A's conversation by id | HIGH | `openConversation()` loads via `Conversation::where('user_id', auth()->id())->whereKey($id)->first()` and no-ops on miss; list queries are always `where('user_id', auth()->id())`. Enforced by the auth-scoping test. |
| T-02 XSS via streamed content or message text | HIGH | All content renders through Blade `{{ }}` escaping (prototype markup: `whitespace-pre-line` divs, escaped text); no `@html`/raw anywhere in the widget views. Enforced by view review + tests asserting escaped output. |
| T-03 Widget rendered on public pages or for guests | MED | Mounts wrapped in `@auth` in both layouts; component's `mount()` loads nothing for guests. Welcome page stays FAB-free (D-25). |
| T-04 Concurrent sends during streaming corrupt the message array | MED | `send()` guards on `$this->streaming` (early return); test asserts a second send during streaming creates no extra rows. |

No HIGH-severity threat is left without a mitigation — nothing blocks this plan.
</threat_model>

<tasks>

<task type="auto">
  <name>Task 1: ChatWidget component skeleton + mount()</name>
  <files>app/Livewire/ChatWidget.php</files>
  <read_first>app/Livewire/QrScanner.php (non-page component conventions), app/Livewire/Pages/Prototype/ChatWidgetPrototype.php (state machine vocabulary — NOT in working tree; branch prototype/chat-widget @ 395244dc — read via git show prototype/chat-widget:app/Livewire/Pages/Prototype/ChatWidgetPrototype.php or checkout that branch before it is deleted), .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.5)</read_first>
  <action>
  - Create `app/Livewire/ChatWidget.php` at the `app/Livewire/` root (NOT `Pages/`), extending `Livewire\Component`, with NO `#[Title]`/`#[Layout]` attributes.
  - Public props: `bool $open = false`, `string $view = 'list'` (`list`|`chat`, D-30), `?int $activeConversationId = null`, `array $conversations = []`, `array $messages = []` (local render array with `role`/`content`/`citations`/`failed`/`error` keys), `string $draft = ''`, `bool $streaming = false`.
  - `mount()`: when `auth()->check()`, load `$this->conversations` = `Conversation::where('user_id', auth()->id())->orderByDesc('updated_at')->get()` (D-16/D-30); otherwise leave empty.
  - Actions `toggle()` (flip `$open`; when opening, refresh `$this->conversations` and reset `$this->view = 'list'` — list is the default on open) and `render()` → `view('livewire.chat-widget')`.
  - No `#[Lazy]`, no `use WithPagination`, no `Toast` trait (D-29 — inline bubbles, not toasts).
  </action>
  <verify>php -l app/Livewire/ChatWidget.php</verify>
  <acceptance_criteria>
  - File exists with public props exactly as locked (`$view`, `$activeConversationId`, `$conversations`, `$messages`, `$draft`, `$streaming`, `$open`)
  - `mount()` queries `Conversation::where('user_id', auth()->id())->orderByDesc('updated_at')`
  - `render()` returns `view('livewire.chat-widget')`; file has no `#[Title]` and no `#[Layout]`
  - `php -l` exits 0
  </acceptance_criteria>
</task>

<task type="auto" name="Task 2: Widget view — FAB, drawer, bubbles, typing dots, failure banner">
  <files>resources/views/livewire/chat-widget.blade.php</files>
  <read_first>resources/views/livewire/pages/prototype/chat-widget-variant-a.blade.php (verbatim verdict markup — NOT in working tree; branch prototype/chat-widget @ 395244dc — read via git show prototype/chat-widget:resources/views/livewire/pages/prototype/chat-widget-variant-a.blade.php or checkout that branch before it is deleted), .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.6)</read_first>
  <action>
  - Create `resources/views/livewire/chat-widget.blade.php` from the variant-A verdict markup, production-normalized (no PROTOTYPE comments):
    - Launcher FAB (only when `! $open`): `<button type="button" wire:click="toggle" class="fixed bottom-6 right-6 z-40 btn btn-circle btn-primary shadow-2xl w-16 h-16" title="Open chat">` with the chat SVG icon.
    - Drawer: `fixed inset-y-0 right-0 z-40 w-full sm:w-96 bg-base-100 border-l border-base-300 shadow-2xl flex flex-col transition-transform duration-300` with `{{ $open ? '' : 'translate-x-full' }}`; header bar (bg-primary) with "CEIT Library Assistant" + "Grounded in catalog + rulebook", `wire:click="newConversation"` New button, and `wire:click="toggle"` ✕ close.
    - Body: when `$view === 'list'` → conversation entries (see Task 5); when `$view === 'chat'` → `@foreach ($messages as $m)` bubbles: user right (`bg-primary text-primary-content rounded-2xl rounded-br-sm px-4 py-2 max-w-[80%] text-sm`), assistant left (`bg-base-200 ... max-w-[85%]`), content inside `<div class="whitespace-pre-line">{{ $m['content'] }}</div>`; streaming tail: when `$streaming && $loop->last` → `<div wire:stream="ans"></div>`; typing dots when `$streaming` (three `<span class="w-1.5 h-1.5 rounded-full bg-base-content/40 animate-bounce">` with `[animation-delay:150ms]` / `[animation-delay:300ms]`); failure banner when `! empty($m['error'])` → amber box (`bg-amber-50 border border-amber-300 px-3 py-2 text-xs text-amber-800`) with `{{ $m['error']['message'] }}` + `<button type="button" wire:click="retry" class="btn btn-xs btn-warning">Retry</button>`.
    - Input form: `<form wire:submit="send" class="border-t border-base-200 p-3 flex items-end gap-2 bg-base-100">` with `<textarea wire:model="draft" rows="1" class="textarea textarea-bordered textarea-sm flex-1 resize-none text-sm" placeholder="Ask about rules, papers…" @if ($streaming) disabled @endif></textarea>` and a submit button disabled while `$streaming`.
  </action>
  <verify>php artisan view:cache</verify>
  <acceptance_criteria>
  - The view contains the exact FAB class string `fixed bottom-6 right-6 z-40 btn btn-circle btn-primary shadow-2xl w-16 h-16`, the drawer string `translate-x-full`, `wire:stream="ans"`, the typing-dots spans with `animate-bounce`, the amber banner with `btn-xs btn-warning` Retry, and `wire:model="draft"`
  - No `{!!` raw-echo directive anywhere in the file (all output escaped)
  - `php artisan view:cache` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 3: send() pipeline — lazy create, persistence, streaming loop</name>
  <files>app/Livewire/ChatWidget.php</files>
  <read_first>app/Livewire/ChatWidget.php (Task 1 state), app/Services/AiService.php (chatStream/chatStreamEvents from 09-02), app/Models/Conversation.php + app/Models/Message.php (09-03), .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.5 ANALOG C streamAnswer)</read_first>
  <action>
  - Implement `public function send(): void` with the prototype guard: `$question = trim($this->draft); if ($question === '' || $this->streaming) { return; }`.
  - Append the user bubble, clear `$this->draft`, then call a private `streamQuestion(string $question)` helper shared with `retry()`.
  - In `streamQuestion()`:
    1. If `$this->activeConversationId === null`: lazily create `Conversation::create(['user_id' => auth()->id(), 'title' => Conversation::makeTitle($question)])` (D-32/D-18), set `$this->activeConversationId`, and refresh `$this->conversations`.
    2. Persist the user Message (`role => 'user'`, `content => $question`).
    3. `$this->streaming = true`; push an assistant placeholder bubble (`content => ''`, `citations => null`, `failed => false`, `error => null`).
    4. `$svc = new AiService; $response = $svc->chatStream($question, 'citations', null, 5);` then `foreach ($svc->chatStreamEvents($response) as $chunk) { $this->stream($chunk, false, 'ans'); }` accumulating chunks; on completion set the bubble content, persist the assistant Message (`role => 'assistant'`, `content => accumulated`, `citations => null` — citations wiring is 09-05), and refresh `$this->conversations` (touch() from 09-03 bumps ordering).
    5. Catch `AiServiceProviderException|AiServiceUnavailableException|AiServiceAuthException` → mark the bubble `failed => true` with `error => ['code' => 'provider_error', 'message' => $e->getMessage()]`; do NOT persist an assistant row for a failed turn.
    6. `finally { $this->streaming = false; }`.
  - History is view-only: opening/loading a conversation NEVER calls the sidecar (D-19).
  </action>
  <verify>php -l app/Livewire/ChatWidget.php</verify>
  <acceptance_criteria>
  - `send()` early-returns when draft is blank or `$streaming` is true
  - First message creates one `ai_conversations` row with `title` from `Conversation::makeTitle()` and persists one user Message; success persists exactly one assistant Message with `citations` null; failure persists NO assistant row
  - Streaming uses `$this->stream($chunk, false, 'ans')` and the chat call is `chatStream($question, 'citations', null, 5)` (mode locked to citations, D-02)
  - No sidecar call happens when loading an existing conversation
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 4: retry() — replace the failed turn in place</name>
  <files>app/Livewire/ChatWidget.php</files>
  <read_first>app/Livewire/ChatWidget.php (Task 3 state), app/Livewire/Pages/Prototype/ChatWidgetPrototype.php (retry implementation — NOT in working tree; branch prototype/chat-widget @ 395244dc — read via git show prototype/chat-widget:app/Livewire/Pages/Prototype/ChatWidgetPrototype.php or checkout that branch before it is deleted), .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.5)</read_first>
  <action>
  - Implement `public function retry(): void`: walk `array_reverse($this->messages, true)` to find the last `role === 'user'` message (store its content); if none, return. If the last message is a trailing `failed` assistant bubble (index greater than the user index), `unset` it — the turn is REPLACED, not duplicated (D-29). Then call `streamQuestion($lastUserContent)`.
  - Guard: early return when `$this->streaming` (same concurrency rule as send()).
  </action>
  <verify>php -l app/Livewire/ChatWidget.php</verify>
  <acceptance_criteria>
  - `retry()` re-streams the last user question after dropping a trailing failed assistant bubble
  - After a failed send followed by retry with a healthy fake, the component's `$messages` contains exactly one assistant bubble for that turn and the DB contains exactly one assistant Message for the conversation
  - `php -l` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 5: Conversation list UI + openConversation + newConversation</name>
  <files>app/Livewire/ChatWidget.php, resources/views/livewire/chat-widget.blade.php</files>
  <read_first>app/Livewire/ChatWidget.php (Tasks 1-4 state), .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.5 + 2.6), @docs/adr/0009-conversation-list-ui-flow.md</read_first>
  <action>
  - In the view's list branch (`$view === 'list'`): loop `$this->conversations`; each entry is a `<button type="button" wire:click="openConversation({{ $c->id }})" class="...">` showing `mb_strimwidth($c->title ?? 'New conversation', 0, 40, '…')` and the relative time `{{ $c->updated_at->diffForHumans() }}` (D-33). Empty state text when no conversations exist.
  - Component: `public function openConversation(int $id): void` — `$conversation = Conversation::where('user_id', auth()->id())->whereKey($id)->first();` and `if (! $conversation) return;` (IDOR guard); load `$conversation->messages` (ascending by id via the 09-03 relation), map to `$this->messages` bubbles (`role`, `content`, `citations` decoded from the JSON cast — rendered as plain bubble content in this plan; chips/Sources arrive in 09-05); set `$this->activeConversationId = $id; $this->view = 'chat';`.
  - Component: `public function newConversation(): void` — `$this->view = 'chat'; $this->activeConversationId = null; $this->messages = []; $this->draft = '';` (D-32).
  </action>
  <verify>php -l app/Livewire/ChatWidget.php</verify>
  <acceptance_criteria>
  - List entries render `mb_strimwidth($c->title ?? 'New conversation', 0, 40, '…')` and `$c->updated_at->diffForHumans()`
  - `openConversation()` loads with `where('user_id', auth()->id())` and no-ops for foreign conversations
  - `newConversation()` resets view/activeConversationId/messages/draft
  - `php -l` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 6: Layout mounts — app + admin, @auth-guarded</name>
  <files>resources/views/components/layouts/app.blade.php, resources/views/components/layouts/admin.blade.php</files>
  <read_first>resources/views/components/layouts/app.blade.php (mount seam near the end of body), resources/views/components/layouts/admin.blade.php (same), .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.6 MOUNT SEAM)</read_first>
  <action>
  - In BOTH layouts, insert immediately before the existing `&lt;x-mary-toast /&gt;` element near the end of the body: `@auth` ... `&lt;livewire:chat-widget /&gt;` ... `@endauth`.
  - Do not touch the welcome (public) page — the `@auth` guard keeps the FAB off unauthenticated pages (D-25/D-27).
  - Optional (not required): wrap in `wire:persist` if SPA `wire:navigate` re-mount behavior proves jarring in manual smoke — re-mount is acceptable per D-30 (list is the default on open).
  </action>
  <verify>php artisan view:cache</verify>
  <acceptance_criteria>
  - `app.blade.php` and `admin.blade.php` each contain `@auth` + `&lt;livewire:chat-widget /&gt;` + `@endauth` placed before `&lt;x-mary-toast /&gt;`
  - `php artisan view:cache` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 7: ChatWidgetTest feature suite</name>
  <files>tests/Feature/ChatWidgetTest.php</files>
  <read_first>tests/Feature/AiServiceTest.php (fake stack), tests/Feature/AcademicPaperIndexHybridTest.php (Livewire::test conventions), tests/Feature/SidecarLiveTest.php (attribute style), .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.7)</read_first>
  <action>
  - Create `tests/Feature/ChatWidgetTest.php` (PHPUnit-attribute style, `RefreshDatabase`). Every test: `actingAs(User::factory()->create())`, `config(['services.ai_sidecar.token' => 'test-token'])`, `Http::preventStrayRequests()`, and `Http::fake` for `http://127.0.0.1:8310/chat/stream` using the `chat-stream.txt` fixture as a string body with `['Content-Type' => 'text/event-stream']` (see 09-02). No real key anywhere.
  - Tests:
    1. `it_mounts_with_conversation_list_default` — seed one Conversation; `Livewire::test(ChatWidget::class)` → `assertSet('view', 'list')` and `assertSee` the ~40-char title; `call('toggle')` → drawer opens with `view` still `list`.
    2. `it_lazily_creates_conversation_and_persists_turn` — `Livewire::test(ChatWidget::class)->set('draft', 'borrowing rules')->call('send')` → `assertDatabaseHas('ai_conversations', ['user_id' => ..., 'title' => 'borrowing rules'])`, `assertDatabaseHas('ai_messages', ['role' => 'user', 'content' => 'borrowing rules'])` and one assistant row; `assertSet('streaming', false)`.
    3. `it_reuses_active_conversation` — second send with `$activeConversationId` set appends to the same conversation (still 1 conversation row).
    4. `it_renders_streamed_content_into_bubble` — after send, `assertSet('messages.1.content', 'CEIT Library ')` (accumulated fixture content).
    5. `it_shows_failure_banner_and_retry_replaces_turn` — fake `/chat/stream` 500 first, then SSE success via `Http::sequence()`; send → `assertSet('messages.1.failed', true)` and error code `provider_error`; `call('retry')` → assistant content present and `assertDatabaseCount('ai_messages', 2)` (user + ONE assistant — no duplicates).
    6. `it_blocks_cross_user_conversation_open` — user B calls `openConversation(user A's id)` → `assertSet('activeConversationId', null)` and messages remain empty.
    7. `it_guards_against_send_during_streaming` — `set('streaming', true)->call('send')` → `assertDatabaseCount('ai_messages', 0)`.
    8. `it_renders_widget_on_authenticated_page` — `$this->get(route('dashboard'))->assertOk()` and `assertSee('CEIT Library Assistant')`.
    9. `it_shows_list_entries_with_relative_time` — seed conversation with `updated_at` 1 hour ago → `assertSee('hour')` or the diffForHumans output.
  </action>
  <verify>php artisan test --filter=ChatWidgetTest</verify>
  <acceptance_criteria>
  - All 9 tests exist and pass: `php artisan test --filter=ChatWidgetTest` exits 0
  - The file contains `Http::preventStrayRequests()` and `config(['services.ai_sidecar.token' => 'test-token'])` in every test; no real API key value present
  - Test 5 proves the retry path leaves exactly one assistant Message per turn; test 6 proves cross-user isolation
  - `php artisan test` (full suite) still green after the plan
  </acceptance_criteria>
</task>

</tasks>

<verification>
- [ ] `php artisan test --filter=ChatWidgetTest` — 9 passing
- [ ] `php artisan test --filter="AiServiceChatTest|ConversationMessageTest"` — upstream wave-1 seams stay green
- [ ] `php artisan test` — full Laravel suite green (523 passed / 3 skipped baseline)
- [ ] Manual smoke prep note: after any `php artisan test` run, corpus files may be deleted — re-run `php artisan ai:export-corpus` before live sessions
</verification>

<success_criteria>
- All 7 tasks complete
- CHAT-01: user chats through the mounted widget and answers stream in with typing dots
- CHAT-02: conversations persist (lazy create + auto-title), the list is the default view, entries show ~40-char titles + relative time, and opening a conversation re-renders its history — with no cross-user leakage
</success_criteria>

<output>
After completion, create `.planning/phases/09-rag-chat-policy-q-a/09-04-SUMMARY.md`
</output>
