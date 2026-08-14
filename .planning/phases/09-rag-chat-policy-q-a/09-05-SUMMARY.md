---
phase: 09-rag-chat-policy-q-a
plan: 05
subsystem: ui
tags: [livewire, blade, citations, grounding, sse, sidecar]

# Dependency graph
requires:
  - phase: 09-rag-chat-policy-q-a (09-01)
    provides: sidecar /chat/stream + /search contract, AiService::chatStream/chatStreamEvents
  - phase: 09-rag-chat-policy-q-a (09-03)
    provides: ai_messages.citations JSON column and array cast
  - phase: 09-rag-chat-policy-q-a (09-04)
    provides: ChatWidget component with send()/streamQuestion() and bubble rendering
provides:
  - Companion /search binding in the widget (same query, corpus null, top_k 5) feeding the locked [{n,id,corpus,title,url,catalog_code}] payload
  - Catalog link chips + policy non-link chips, numbered Sources list partials under the answer
  - Empty-retrieval refusal rendered as a normal bubble with no sources (sidecar stays the single refusal authority)
  - Citation binding/payload/render/refusal/policy-flow tests in ChatWidgetTest
affects: 09-06 (history re-renders citations), phase 10 (live availability on citation links), phase 13 (eval over citations)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Companion search bound to chat retrieval params (corpus null, top_k 5) so [N] markers match the sidecar's deterministic RRF order (D-20)"
    - "Non-fatal companion search: failure/empty yields citations null, turn continues, sidecar refusal is the single authority (D-23)"
    - "Corpus-dependent rendering: catalog rows link to /academic-papers/{id}, policy rows are non-link with (rulebook) suffix (D-21/D-22)"

key-files:
  created:
    - resources/views/livewire/chat-widget-citations.blade.php
    - resources/views/livewire/chat-widget-sources.blade.php
  modified:
    - app/Livewire/ChatWidget.php
    - resources/views/livewire/chat-widget.blade.php
    - tests/Feature/ChatWidgetTest.php

key-decisions:
  - "Companion search runs sequentially before chatStream with explicit corpus null (search() defaults to 'catalog') and top_k 5, dereferenced from the envelope via ['results']"
  - "Assistant bubble carries citations on the message array; ai_messages.citations persists the same payload (null on empty/failed search)"
  - "Refusal string streams as a normal bubble — no refused flag, no special styling, no Sources (D-23/D-24/D-29)"
  - "Policy citations render as non-link chips with the (rulebook) suffix; catalog chips link to /academic-papers/{id}"

patterns-established:
  - "Citation rendering lives in two partials included behind @if (! empty($m['citations'])) inside the assistant bubble"
  - "Http::fake fakes BOTH sidecar endpoints in every widget test; Http::preventStrayRequests() stays in all"

requirements-completed: [SEARCH-03, SEARCH-04, CHAT-04]

# Metrics
duration: 12min
completed: 2026-08-14
---

# Phase 9 Plan 05: Citations & Grounding Summary

**Companion /search bound to the chat call (corpus null, top_k 5, k=60) feeds the locked `[{n,id,corpus,title,url,catalog_code}]` citation payload persisted into `ai_messages.citations`, rendered as catalog link chips + policy non-link chips and a numbered Sources list, with the sidecar's empty-retrieval refusal streaming as a normal bubble**

## Performance

- **Duration:** ~12 min
- **Started:** 2026-08-14T06:17:45Z
- **Completed:** 2026-08-14T06:23:07Z
- **Tasks:** 4 (Task 3 produced no code delta — verified, documented below)
- **Files modified:** 5

## Accomplishments
- `ChatWidget::streamQuestion()` now runs `(new AiService)->search($question, [], null, 5)['results']` before `chatStream()` — same query, corpus null (both corpora), top_k 5, k=60 → identical RRF ordering on both calls (D-20); payload built in rank order with 1-based `n`, envelope dereferenced (RESEARCH binding)
- Citation payload persists with the assistant message and attaches to the bubble; empty or failed companion search yields `citations => null` and never short-circuits the chat call (T-04, D-23)
- New partials `chat-widget-citations.blade.php` (link chip with `catalog_code` mono span vs non-link chip) and `chat-widget-sources.blade.php` (dashed-border box, literal `Sources` label, `<ol>` with `(rulebook)` suffix for policy rows), included inside the assistant bubble behind `@if (! empty($m['citations']))` (D-21/D-22)
- 5 new `ChatWidgetTest` cases: parameter binding across both calls, payload persistence, catalog chip+Sources render, policy non-link chip render, refusal-without-Sources render — 14 passing in the widget suite
- Full Laravel suite 552 passed / 3 skipped (547 baseline + 5 new); sidecar suite untouched, 46 passed / 1 skipped

## Task Commits

Each task was committed atomically:

1. **Task 1: Companion search wiring + citation payload persistence** - `fc537e4c` (feat)
2. **Task 2: Citation chips + Sources partials, wired into the bubble** - `ba041c5d` (feat)
3. **Task 3: Empty retrieval + refusal rendering** - no commit (zero production delta — guard landed in Task 1, view guard in Task 2; plan itself says "no further view change needed"; acceptance proven by Task 4's refusal test)
4. **Task 4: Binding, payload, render, refusal and policy-flow tests** - `7b4ce2c3` (test)

**Plan metadata:** `(docs commit to follow)`

## Files Created/Modified
- `app/Livewire/ChatWidget.php` - `companionCitations()` helper (try/catch search → dereference envelope → rank-ordered payload or null) wired into `streamQuestion()` before the stream; bubble + persisted message carry `citations`
- `resources/views/livewire/chat-widget-citations.blade.php` - citation chip partial (catalog link vs policy non-link), normalized from prototype verdict markup
- `resources/views/livewire/chat-widget-sources.blade.php` - numbered Sources list partial with `(rulebook)` suffix for non-catalog rows
- `resources/views/livewire/chat-widget.blade.php` - both partials included behind `@if (! empty($m['citations']))` in the assistant bubble; also carries the leftover single-root `<div>` wrapper fix (was uncommitted in the working tree at plan start)
- `tests/Feature/ChatWidgetTest.php` - 5 new tests; all 9 existing tests' `Http::fake` extended to cover `/search` (empty-results response, preserving the old `citations => null` assertions)

## Decisions Made
- Companion search runs sequentially before streaming (per RESEARCH §4.5 recommendation); explicit `corpus => null` and `limit => 5` passed despite `search()` defaulting to `'catalog'`/10 (D-20 binding)
- Empty retrieval and companion-search failure both map to `citations => null`; the widget always calls `chatStream()` so the sidecar's programmatic refusal (09-01 Δ4) is the single refusal authority (D-23)
- Chips partial kept the prototype's wrapper `<div class="mt-2 flex flex-wrap gap-1.5">` inside the partial itself (verbatim verdict markup, normalized without THROWAWAY comments)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Existing widget tests needed the `/search` fake in Task 1's commit**
- **Found during:** Task 1 (companion search wiring)
- **Issue:** The new companion call fires on every `send()`; all 9 existing `ChatWidgetTest` cases use `Http::preventStrayRequests()` and only faked `/chat/stream` — the plan scoped test-file edits to Task 4, but leaving them would have made every existing test throw `StrayRequestException` between Task 1 and Task 4.
- **Fix:** Added a shared `emptySearchResponse()` helper and extended every existing test's `Http::fake` with `http://127.0.0.1:8310/search` (empty results, so the pre-existing `citations => null` assertions stay valid).
- **Files modified:** tests/Feature/ChatWidgetTest.php
- **Verification:** `php artisan test --filter=ChatWidgetTest` — 9/9 green after Task 1.
- **Committed in:** fc537e4c (Task 1 commit)

**2. [Rule 3 - Test correctness] Render tests must enter the chat view first**
- **Found during:** Task 4 (render/refusal tests)
- **Issue:** The drawer defaults to the `list` view (D-30); `send()` without `newConversation()` leaves `view = 'list'`, so bubbles (and chips/Sources) never render in the HTML — the plan's test sketch asserted render without switching views. The 09-04 tests only asserted component state, so this was invisible there.
- **Fix:** Added `->call('newConversation')` before `->set('draft', ...)` in the three HTML-render tests.
- **Files modified:** tests/Feature/ChatWidgetTest.php
- **Verification:** `php artisan test --filter=ChatWidgetTest` — 14/14 green.
- **Committed in:** 7b4ce2c3 (Task 4 commit)

**3. [Rule 3 - Scope] Task 3 had zero production delta**
- **Found during:** Task 3 (empty retrieval + refusal rendering)
- **Issue:** The plan's own action text says "no further view change needed" — the empty-results → null guard already shipped in Task 1's `companionCitations()` and the `@if (! empty($m['citations']))` suppression in Task 2.
- **Fix:** Verified acceptance via code inspection (widget never short-circuits `chatStream()` on empty results) + `php -l` exit 0 + Task 4's refusal test; did NOT create an empty commit (GSD discipline).
- **Verification:** `php -l app/Livewire/ChatWidget.php` exit 0; refusal test green.
- **Committed in:** n/a (covered by fc537e4c + ba041c5d + 7b4ce2c3)

**4. [Rule 3 - Scope] Pre-existing uncommitted blade wrapper fix folded into Task 2**
- **Found during:** Task 2 (view wiring)
- **Issue:** `resources/views/livewire/chat-widget.blade.php` had an uncommitted single-root `<div>` wrapper (leftover from 09-04, needed for Livewire single-root rendering) sitting in the working tree at plan start.
- **Fix:** Kept it and committed it together with the citation-include edit (same file, one commit).
- **Files modified:** resources/views/livewire/chat-widget.blade.php
- **Verification:** `php artisan view:cache` exit 0; widget suite green.
- **Committed in:** ba041c5d (Task 2 commit)

---

**Total deviations:** 4 auto-fixed (3 Rule 3 scope/correctness, 1 pre-existing uncommitted change)
**Impact on plan:** All auto-fixes necessary for a green per-commit suite and honest test coverage. No scope creep — the citation/grounding contract (D-20..D-24) shipped exactly as locked.

## Issues Encountered
- PowerShell/CMD arg quoting swallowed `|` in `--filter` regexes — ran the three targeted filters separately (ChatWidgetTest 14, AiServiceChatTest 7, ConversationMessageTest 8).
- Livewire render tests can't be debugged via `assertSeeHtml` output alone; used a temporary dump test (removed before commit) to confirm the `list`-view root cause.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- SEARCH-03: every widget answer carries numbered [N] chips + full Sources list bound to the same retrieval order the model saw; catalog chips link to `/academic-papers/{id}` (route `academic-paper.show`)
- SEARCH-04: empty retrieval shows the canonical `I don't have enough information` refusal as a normal bubble with no sources; sidecar is the single refusal authority
- CHAT-04: policy questions surface rulebook-grounded answers with non-link policy chips + `(rulebook)` suffix
- Phase gate (gated live smoke `SIDECAR_LIVE_CHAT_TEST=1 uv run pytest tests/test_chat_stream_live.py`) NOT run — requires a rotated OpenRouter key and `php artisan ai:export-corpus` (corpus files are deleted by tests); operator-gated, per CONTEXT
- Ready for the next plan (09-06) — history view already re-renders persisted citations via the `Message::citations` array cast

---
*Phase: 09-rag-chat-policy-q-a*
*Completed: 2026-08-14*
