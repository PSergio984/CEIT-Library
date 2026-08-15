---
phase: 11-academic-papers-agentic-search
plan: 05
subsystem: api
tags: [laravel, livewire, blade, sse, agentic-loop, sidecar, ai-chat, chatwidget, ai-service]

# Dependency graph
requires:
  - phase: 11-03 (agentic loop)
    provides: verified SSE wire contract — event: activity / data: {"text": ...}, event: citations / data: [{n, id, corpus, title, url, catalog_code}], chunk envelope {"c": ...} unchanged, [DONE] + event: error taxonomy unchanged
  - phase: 09 (RAG chat)
    provides: chatStreamEvents() read loop, companionCitations() fallback, W-3 persistent stream slot pattern, ai_messages.citations persistence
provides:
  - AiService::chatStreamFrames() typed frame generator (activity/chunk/citations) with chatStreamEvents() byte-unchanged for back-compat
  - ChatWidget agentic path: second persistent 'activity' stream slot, citations-frame binding with companionCitations() fallback, refusal-as-normal-bubble (D-08/D-12)
affects: [phase 12 (role-aware answers), phase 13 eval, phase 14 monitoring (live sidecar + OpenRouter smoke)]

# Tech tracking
tech-stack:
  added: []
  patterns: [typed SSE frame parser, dual persistent stream slots (W-3), frame-payload shape gate with nullable-key-aware array_key_exists, stream-frame capture via ob_start callback in Livewire tests]

key-files:
  created:
    - tests/fixtures/ai-sidecar/chat-stream-agentic.txt
  modified:
    - app/Services/AiService.php
    - app/Livewire/ChatWidget.php
    - resources/views/livewire/chat-widget.blade.php
    - tests/Feature/AiServiceChatTest.php
    - tests/Feature/ChatWidgetTest.php

key-decisions:
  - "chatStreamFrames() copies the chatStreamEvents() loop verbatim and adds ONLY the event: activity / event: citations line-pair branch; every other line keeps the legacy decode path so raw frame JSON can never fall through into chunk payloads (T-11-18, pitfall #1)"
  - "Citations frame payload is ADR 0006 shape-gated (list of entries with the six keys via array_key_exists — nullable url/catalog_code must not fail isset) before it replaces the companionCitations() fallback for render + persistence (T-11-19)"
  - "Bounce-dots stream retargeted from 'ans' to the new 'activity' slot unconditionally (masks first-call decision latency, A-4); chunk frames stream into 'ans' as today, no dots on the agentic path"
  - "Fail-closed refusal text arrives as normal chunk frames and renders as a normal assistant bubble; event: error still throws AiServiceProviderException → amber bubble + Retry (D-08, taxonomy untouched)"

patterns-established:
  - "Typed SSE frame parsing: event-type line-pair consumption (event line + its data: line) inside the shared read-loop skeleton — additive frame types, legacy parser untouched"
  - "W-3 dual slots: two persistent single-line wire:stream elements with empty:py-0 empty:invisible collapse, mounted activity-above-ans"
  - "Stream-capture in Livewire 4 tests: ob_start(callback) — streamContent()'s ob_flush() truncates plain ob_start buffers, so frame assertions go through a capture callback"

requirements-completed: [CHAT-05]

# Metrics
duration: 55min
completed: 2026-08-15
---

# Plan 11-05: Agentic Chat Widget — Summary

**AiService::chatStreamFrames() typed SSE frame parser (activity/citations/chunk) wired into ChatWidget's second persistent activity slot with citations-frame binding, companionCitations() fallback, and refusal-as-normal-bubble — CHAT-05 complete end to end with 11-03**

## Performance

- **Duration:** 55 min
- **Started:** 2026-08-15T18:25:00
- **Completed:** 2026-08-15T19:20:00
- **Tasks:** 3 (Task 1 and Task 3 TDD: test + feat commits)
- **Files modified:** 5

## Accomplishments
- `AiService::chatStreamFrames(Response): \Generator` yields typed frames from the same resource() read loop; `chatStreamEvents()` byte-unchanged — back-compat proven on the same agentic fixture (legacy fall-through of frame payload lines locked by test)
- ChatWidget streams one spinner+copy line per activity frame into the new W-3 `activity` slot above the answer slot; a shape-gated citations frame drives the final bubble + `ai_messages.citations` persistence with `companionCitations()` fallback when absent/malformed (T-11-19)
- Fail-closed refusal text renders as a normal assistant bubble (no error styling, no Retry); `event: error` taxonomy unchanged — D-08 and D-12 complete, CHAT-05 satisfied
- Full suite: 598 passed / 3 skipped (baseline 591 + 7 new), `php artisan view:cache` compiles

## Task Commits

Each task was committed atomically:

1. **Task 1: AiService::chatStreamFrames() + agentic fixture + parser tests** - `f8723157` (test, RED scaffold: fixture + 3 parser tests) + `2fa175b4` (feat, chatStreamFrames)
2. **Task 2: ChatWidget streamQuestion() frame routing + activity slot blade markup** - `c702fb3c` (feat)
3. **Task 3: ChatWidget agentic feature tests** - `9c7c2111` (test: frames render, fallback, refusal bubble, malformed frame)

**Plan metadata:** final `docs(11-05)` commit of this plan (see `git log`; includes this summary + STATE/ROADMAP/REQUIREMENTS updates)

## Files Created/Modified
- `app/Services/AiService.php` - Added `chatStreamFrames()` typed frame generator (79 lines, additive); `chatStreamEvents()` untouched
- `app/Livewire/ChatWidget.php` - streamQuestion() routes frames (activity→slot, chunk→ans, citations→shape-gated payload); new `validCitationsPayload()` helper; dots retargeted to activity slot
- `resources/views/livewire/chat-widget.blade.php` - Second W-3 persistent single-line `wire:stream="activity"` slot mounted above the ans slot
- `tests/fixtures/ai-sidecar/chat-stream-agentic.txt` (NEW) - Agentic SSE fixture: activity frame + chunk lines + citations frame + [DONE]
- `tests/Feature/AiServiceChatTest.php` - 3 new tests (frame parsing order, legacy raw-chunk back-compat on the same fixture, error taxonomy on frame stream)
- `tests/Feature/ChatWidgetTest.php` - 4 new tests (agentic activity+citations render/persist, companion fallback without frame, refusal-as-normal-bubble, malformed frame fallback)

## Decisions Made
- Frame payload shape-gate uses `array_key_exists` rather than `isset` so nullable ADR 0006 keys (`url`, `catalog_code` — policy citations) still validate
- Bounce-dots stream into the activity slot unconditionally (not only on the agentic path) — there is no up-front way to know a stream is agentic, and the plan's Task 2 action is unconditional; the non-agentic visual is unchanged apart from which slot carries the dots
- Back-compat test locks the exact legacy yield sequence on the agentic fixture (including the raw frame-payload strings that fall through the untouched parser) — proving the new parser is the ONLY frame-aware path and the widget uses it

## Deviations from Plan

None - plan executed exactly as written (see Stub scan note below for the Task 3 assertion detail).

### Test-assertion note (Rule 1 auto-fix, within plan intent)

**1. [Rule 1 - Missing Critical] Streamed-frame assertions need an ob_start callback, not assertSeeHtml**
- **Found during:** Task 3 (ChatWidget agentic feature tests)
- **Issue:** The plan's `assertSeeHtml` cannot see streamed slot content in Livewire 4 — `SupportStreaming::streamContent()` echoes frames into the SSE body (never component state/effects), and its `ob_flush()` truncates a plain `ob_start()` buffer (verified with a throwaway spike test)
- **Fix:** Capture the echoed wire frames through `ob_start(function ($chunk) use (&$streamed) { $streamed .= $chunk; return ''; })` around `call('send')`, then assert the frame JSON (spinner+copy line, `"name":"activity"` / `"name":"ans"`, no activity-line markup on the back-compat path) — same assertions, same intent
- **Files modified:** tests/Feature/ChatWidgetTest.php
- **Verification:** `php artisan test --filter ChatWidgetTest` 23 passed (81 assertions)
- **Committed in:** 9c7c2111 (Task 3 commit)

---

**Total deviations:** 1 auto-fixed (1 missing critical)
**Impact on plan:** Auto-fix only changed the assertion mechanism to reach the same planned assertions. No scope creep.

## Issues Encountered
- Second `Livewire::test` send in one test replayed the same faked SSE response instance — exhausted stream → W-2 truncation throw. Fixed with `Http::sequence()->push(...)` twice (the repo's established multi-send pattern, `it_reuses_active_conversation`).

## Stub Scan
- `chatStreamFrames`: implemented in `app/Services/AiService.php` (typed yields, `[DONE]` return, error taxonomy preserved)
- `chatStreamEvents`: untouched — git diff shows only additions
- `wire:stream="activity"`: mounted in `chat-widget.blade.php` above the ans slot, single line, `empty:py-0 empty:invisible`
- `companionCitations()`: still the fallback point; frame payload wins only when shape-valid
- `validCitationsPayload()`: new private helper — six ADR 0006 keys via `array_key_exists`
- Agentic fixture `chat-stream-agentic.txt`: created with `event: activity`, `event: citations`, `{"c": ...}` chunks, `[DONE]`
- No stubs, TODOs, or placeholder implementations left behind

## User Setup Required

None - no external service configuration required. (Live manual smoke of the full loop requires a running sidecar + OpenRouter key, per 11-VALIDATION.md live checklist.)

## Next Phase Readiness
- CHAT-05 complete: the agentic loop (11-03) + Laravel consumption (this plan) deliver D-08/D-12 end to end
- Phase 12 (role-aware answers) can extend the citations frame payload contract additively; phase 13 eval can assert on the persisted `ai_messages.citations`
- Watch item (from 11-03): live tool-call reliability of OpenRouter Balanced routing — config-level fix if malformed calls appear in production

## Self-Check: PASSED

- Created files verified on disk: `tests/fixtures/ai-sidecar/chat-stream-agentic.txt`, `11-05-SUMMARY.md`
- Modified files verified: `app/Services/AiService.php`, `app/Livewire/ChatWidget.php`, `resources/views/livewire/chat-widget.blade.php`, `tests/Feature/AiServiceChatTest.php`, `tests/Feature/ChatWidgetTest.php`
- Commits verified via `git log`: f8723157, 2fa175b4, c702fb3c, 9c7c2111 (+ final docs commit of this plan)
- Verification commands: `php artisan test --filter AiServiceChatTest` 14 passed; `php artisan test --filter ChatWidgetTest` 23 passed; full `php artisan test` 598 passed / 3 skipped; `php artisan view:cache` compiles

---
*Phase: 11-academic-papers-agentic-search*
*Completed: 2026-08-15*
