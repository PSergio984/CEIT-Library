---
phase: 09-rag-chat-policy-q-a
plan: 04
subsystem: ui, api
tags: [livewire, chat, streaming, sse, rag, conversation-history, ai]

# Dependency graph
requires:
  - phase: 09-02
    provides: AiService::chatStream() + chatStreamEvents() SSE parser + typed AiServiceProviderException
  - phase: 09-03
    provides: ai_conversations/ai_messages schema, Conversation::makeTitle(), Message touch() hook, factories
provides:
  - "ChatWidget Livewire component (app/Livewire root, non-page) with list<->chat drawer views, lazy conversation creation, streamed chat, failure banner + retry"
  - "chat-widget blade view (FAB + drawer, bubbles, typing dots, amber failure banner) mounted in both app and admin layouts behind @auth"
  - "ChatWidgetTest feature suite (9 tests) with full Http::fake SSE stack, no real sidecar/LLM"
affects: [09-05-citations-wiring, 12-role-aware-access, 14-monitoring]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Livewire 4.4 streamed chat: chatStream() Response -> chatStreamEvents() generator -> $this->stream($chunk, false, 'ans')"
    - "Livewire 4.4 single-root-element component markup (FAB + drawer wrapped in one root div)"
    - "Lazy conversation creation + Conversation::makeTitle() auto-title + Message touch() ordering (D-32/D-16/D-18)"
    - "Auth-scoped conversation loads: where('user_id', auth()->id()) everywhere (T-01 IDOR guard)"
    - "Provider failure -> inline amber banner + retry replacing the failed turn, no duplicate assistant row (D-29)"

key-files:
  created:
    - app/Livewire/ChatWidget.php
    - resources/views/livewire/chat-widget.blade.php
    - tests/Feature/ChatWidgetTest.php
  modified:
    - resources/views/components/layouts/app.blade.php
    - resources/views/components/layouts/admin.blade.php

key-decisions:
  - "streamQuestion(string $question, bool $persistUser = true) shared by send() and retry(); retry passes persistUser:false so the failed turn leaves exactly one user + one assistant row (plan test-5 spec: assertDatabaseCount 2)"
  - "Widget view has a single root <div> wrapper because Livewire 4.4 rejects multiple root elements (prototype's two-root markup predates the v4 check)"
  - "retry() reindexes $this->messages with array_values() after unset() because PHP's next-free-index hint survives unset and would append the replacement bubble at a gap key"
  - "conversations public property holds Conversation models (ArraySynth rehydrates nested models); list entries render mb_strimwidth title + diffForHumans"

patterns-established:
  - "Widget SSE fake: Http::fake(['http://127.0.0.1:8310/chat/stream' => Http::response(fixture('chat-stream.txt'), 200, ['Content-Type' => 'text/event-stream'])]) + Http::preventStrayRequests() + config token in every test"
  - "Failure + retry test via Http::sequence()->push([], 500)->push(SSE fixture)"
  - "Livewire 4.4 has no streaming assertions — assert post-call state (messages array, streaming flag, DB rows)"

requirements-completed: [CHAT-01, CHAT-02]

# Metrics
duration: 32min
completed: 2026-08-14
---

# Phase 9 Plan 4: Chat Widget Summary

**In-app chat widget: floating FAB + right drawer on every authenticated page, streamed answers via AiService::chatStream with typing dots, lazy conversation creation with auto-title, list-first drawer with relative-time entries, and an inline amber failure banner whose Retry replaces the failed turn in place**

## Performance

- **Duration:** 32 min
- **Started:** 2026-08-14T06:00:00Z (approx)
- **Completed:** 2026-08-14T06:32:00Z (approx)
- **Tasks:** 7
- **Files modified:** 5 (3 created, 2 modified)

## Accomplishments

- ChatWidget component (`app/Livewire/ChatWidget.php`) with the locked prop vocabulary (`open`, `view` list|chat, `activeConversationId`, `conversations`, `messages`, `draft`, `streaming`), `mount()` loading conversations `where user_id = auth()->id() orderByDesc('updated_at')`, and toggle() resetting to the list view on open (D-30).
- `send()` pipeline: trim + `$streaming` guard (T-04), lazy `Conversation::create(['title' => Conversation::makeTitle($question)])` (D-32/D-18), user Message persistence, `chatStream($question, 'citations', null, 5)` (D-02 mode locked) consumed via `chatStreamEvents()` with `$this->stream($chunk, false, 'ans')` per chunk, assistant Message persisted with `citations => null` (citations wiring is 09-05), `$streaming` cleared in `finally`.
- `retry()` replaces the failed turn: drops the trailing failed assistant bubble (D-29), re-streams the last user question with `persistUser: false` so the DB holds exactly one user + one assistant row for the turn.
- View (`resources/views/livewire/chat-widget.blade.php`): verdict variant-A markup production-normalized — FAB (`fixed bottom-6 right-6 z-40 btn btn-circle btn-primary shadow-2xl w-16 h-16`), drawer (`w-full sm:w-96` + `translate-x-full`), bubbles, `wire:stream="ans"` tail, typing dots with staggered `animate-bounce`, amber banner + `btn-xs btn-warning` Retry, all content `{{ }}`-escaped (T-02).
- Conversation list view (default on open): `mb_strimwidth($c->title ?? 'New conversation', 0, 40, '…')` + `$c->updated_at->diffForHumans()` entries (D-33); `openConversation()` auth-scoped with IDOR no-op (T-01); `newConversation()` resets to empty chat (D-32).
- `@auth` + `<livewire:chat-widget />` + `@endauth` mounted immediately before `<x-mary-toast />` in BOTH `app` and `admin` layouts (D-25/D-27); welcome page untouched.
- ChatWidgetTest: 9 passing tests with the full `Http::fake` SSE stack (fixture `chat-stream.txt`), `Http::preventStrayRequests()` and `config(['services.ai_sidecar.token' => 'test-token'])` in every test, zero real keys.

## Task Commits

Each task was committed atomically:

1. **Task 1: ChatWidget component skeleton + mount()** - `48610079` (feat)
2. **Task 2: Widget view - FAB, drawer, bubbles, typing dots, failure banner** - `034acbff` (feat)
3. **Task 3: send() pipeline - lazy create, persistence, streaming loop** - `037fc9e2` (feat)
4. **Task 4: retry() - replace the failed turn in place** - `84b3e71f` (feat)
5. **Task 5: Conversation list UI + openConversation + newConversation** - `420dfa0c` (feat)
6. **Task 6: Layout mounts - app + admin, @auth-guarded** - `adc3df3e` (feat)
7. **Task 7: ChatWidgetTest feature suite** - `a1cdcf94` (test, includes retry fixes)

**Plan metadata:** `(this SUMMARY commit)` (docs: complete chat-widget plan)

## Files Created/Modified

- `app/Livewire/ChatWidget.php` - Created: non-page component; props, mount, toggle, send, retry, openConversation, newConversation, streamQuestion helper, auth-scoped refreshConversations
- `resources/views/livewire/chat-widget.blade.php` - Created: FAB, drawer, list/chat views, bubbles, typing dots, failure banner, input form (single root element per Livewire 4.4)
- `tests/Feature/ChatWidgetTest.php` - Created: 9-test feature suite (mount/list default, lazy create + persistence, conversation reuse, streamed bubble content, failure + retry replace, cross-user isolation, streaming guard, layout mount on dashboard, relative-time entries)
- `resources/views/components/layouts/app.blade.php` - Modified: `@auth` + `<livewire:chat-widget />` before `<x-mary-toast />`
- `resources/views/components/layouts/admin.blade.php` - Modified: same mount seam

## Decisions Made

- `streamQuestion()` takes `bool $persistUser = true`; `retry()` calls it with `false` so the failed turn leaves exactly one user row + one assistant row (plan test-5 spec asserts `assertDatabaseCount('ai_messages', 2)` — the literal "call streamQuestion" flow would have re-persisted the user row and made 3).
- Widget markup wrapped in a single root `<div>`: Livewire 4.4's `SupportMultipleRootElementDetection` throws on the prototype's two-root (FAB + drawer) markup, which predates the v4 check.
- `retry()` reindexes with `array_values()` after `unset()` — PHP's internal next-free-index hint survives `unset`, so a plain `[]` append would place the replacement bubble at a gap key (e.g. `messages.2` instead of `messages.1`).
- `conversations` holds hydrated `Conversation` models (Livewire ArraySynth rehydrates nested models), enabling `$c->title` / `$c->updated_at->diffForHumans()` in the list template across requests.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Livewire 4.4 multiple-root-element rejection**
- **Found during:** Task 7 (ChatWidgetTest first run — all 9 failed with `MultipleRootElementsDetectedException`)
- **Issue:** The verdict prototype markup has two root elements (FAB button + drawer div); Livewire 4.4 rejects multi-root components, so the widget could not even mount.
- **Fix:** Wrapped the whole widget in a single root `<div>` (FAB and drawer are `fixed`-positioned so layout is unaffected).
- **Files modified:** resources/views/livewire/chat-widget.blade.php
- **Verification:** All 9 widget tests pass; `php artisan view:cache` exits 0
- **Committed in:** a1cdcf94 (Task 7 commit)

**2. [Rule 1 - Bug] retry() re-persisted the user message (duplicate turn)**
- **Found during:** Task 7 (test 5 — plan spec requires `assertDatabaseCount('ai_messages', 2)`, "no duplicates")
- **Issue:** Passing the stored question straight into `streamQuestion()` re-created the user Message row on retry, yielding 2 user + 1 assistant rows; the plan's own test spec pins 2 rows total.
- **Fix:** Added `bool $persistUser = true` param; retry passes `false`. The user row from the failed turn is reused; only the assistant answer is re-streamed.
- **Files modified:** app/Livewire/ChatWidget.php
- **Verification:** Test 5 passes: `assertDatabaseCount('ai_messages', 2)` and exactly 1 assistant row
- **Committed in:** a1cdcf94 (Task 7 commit)

**3. [Rule 1 - Bug] PHP next-free-index quirk left a gap key after retry**
- **Found during:** Task 7 (test 5 — `messages.1.content` was null; actual key was `messages.2`)
- **Issue:** `unset($this->messages[$last])` does not lower PHP's internal next-free-index hint, so the replacement bubble appended at key 2, breaking the contiguous-messages invariant and `assertSet('messages.1.content', ...)`.
- **Fix:** `$this->messages = array_values($this->messages)` after the unset, before re-streaming.
- **Files modified:** app/Livewire/ChatWidget.php
- **Verification:** Test 5 passes with `messages.1` assertions
- **Committed in:** a1cdcf94 (Task 7 commit)

---

**Total deviations:** 3 auto-fixed (all Rule 1 - bugs found by the plan's own test spec)
**Impact on plan:** All three were correctness fixes surfaced by the plan's acceptance criteria during Task 7 verification. No scope creep; behavior now matches the plan's locked test expectations exactly.

## Issues Encountered

- PowerShell 5.1 mangles `|` inside quoted `--filter` args when invoking `php artisan test`; resolved with the `--%` stop-parsing token (`php artisan test --% --filter="A|B"`). Test runs unaffected.
- Note (plan verification checklist): `php artisan test` deletes exported corpus files (`ExportAiCorpusTest` setUp) — re-run `php artisan ai:export-corpus` before any live sidecar session.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- **CHAT-01 done:** widget mounts on every authenticated page, lazy conversation creation, streamed answers with typing dots, failure banner + replace-in-place retry.
- **CHAT-02 done:** conversations persist, list is the default view with ~40-char titles + relative times, opening a conversation re-renders its history (view-only, never replayed to the sidecar), no cross-user leakage (T-01 test green).
- Ready for plan **09-05** (citation chips + Sources wiring on top of this component — assistant messages already persist `citations => null` as the seam).
- Full suite: 547 passed / 3 skipped (1537 assertions), up from the 538 baseline by the 9 new widget tests.

---
*Phase: 09-rag-chat-policy-q-a*
*Completed: 2026-08-14*
