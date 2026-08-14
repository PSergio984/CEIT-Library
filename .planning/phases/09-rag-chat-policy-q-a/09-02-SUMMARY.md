---
phase: 09-rag-chat-policy-q-a
plan: 02
subsystem: api
tags: [laravel, ai-sidecar, sse, http-fake, streaming, chat]

# Dependency graph
requires:
  - phase: 09-rag-chat-policy-q-a
    provides: 09-01 sidecar contract fixes (JSON error event, auth_failed 401) and locked ADR 0004 endpoint contract
provides:
  - AiService::chatStream() — streamed POST /chat/stream with retries 0, throwUnlessOk first, returns the streamed Response
  - AiService::chatStreamEvents() — SSE line parser over Response::resource() yielding data: chunks, terminating on [DONE], throwing AiServiceProviderException on event: error
  - AiServiceProviderException typed exception (D-12/Δ9)
  - Shared request() helper refactor (D-09/Δ6) — behavior-identical, 11-test regression net green
  - AiServiceChatTest (7 tests) + chat-stream.txt SSE fixture
affects: [09-03 conversation schema, 09-04 chat widget — both consume chatStream()/chatStreamEvents()]

# Tech tracking
tech-stack:
  added: [none — no new dependencies]
  patterns:
    - "Streamed fake via Http::fake string-body + fgets($response->resource()) with a SINGLE hoisted resource — calling resource() per read breaks shared-stream reads"
    - "retries: 0 for streamed POSTs (Δ10) — retry would duplicate LLM generation"

key-files:
  created:
    - app/Exceptions/AiServiceProviderException.php
    - tests/Feature/AiServiceChatTest.php
    - tests/fixtures/ai-sidecar/chat-stream.txt
  modified:
    - app/Services/AiService.php

key-decisions:
  - "chatStreamEvents() hoists one $stream = $response->resource() instead of calling resource() per line — Laravel 13's StreamWrapper shares the underlying PSR stream, and interleaved fresh wrappers read EOF after PHP's feof() probe-read drains it"
  - "chatStream() body omits corpus when null (D-02 contract) and passes retries: 0 (Δ10)"
  - "Error-path data line is never yielded as content — event: error throws AiServiceProviderException immediately (T-04 mitigation)"

patterns-established:
  - "SSE parse loop: one hoisted resource, while (! feof) { fgets; data: → yield | [DONE] → return | event: error → throw }"
  - "Test fake stack: config token test-token + Http::preventStrayRequests() + fixture() helper + string-body SSE fakes"

requirements-completed: [CHAT-01]

# Metrics
duration: 12min
completed: 2026-08-14
---

# Phase 09 Plan 02: AiService Chat Summary

**AiService gained a streamed `/chat/stream` client — `chatStream()` with retries 0 plus a testable SSE parser `chatStreamEvents()` over `Response::resource()` — behind a behavior-identical `request()` helper refactor, with a typed `AiServiceProviderException` and a 7-test fake-stack suite.**

## Performance

- **Duration:** 12 min
- **Started:** 2026-08-14T13:38:00Z (approx)
- **Completed:** 2026-08-14T13:50:55Z
- **Tasks:** 5 (all committed individually)
- **Files modified:** 4 (1 modified, 3 created)

## Accomplishments
- Extracted the request-builder chain in `send()` into the shared private `request($method, $path, $body, $timeout, $retries, $stream = false)` helper (Δ6/D-09); `search()`/`rebuildIndex()`/`health()` signatures and behavior unchanged — all 11 existing `AiServiceTest` cases stayed green as the regression net.
- Added `chatStream(string $query, ?string $mode = 'citations', ?string $corpus = null, int $topK = 5): Response` (Δ7/D-10): POSTs `/chat/stream` with `{query, mode, top_k}` plus `corpus` only when non-null, uses `retries: 0` (Δ10/T-03 — a retry would re-issue the POST and duplicate LLM generation), catches `ConnectionException` with the sanitized `logFailure()` path, runs `throwUnlessOk()` before touching the body, and returns the streamed `Response`.
- Added `chatStreamEvents(Response): \Generator` (Δ8/Δ11): reads SSE lines via `fgets` on a single hoisted `$response->resource()`, yields `data: ` payloads in order, terminates on `data: [DONE]`, and throws `AiServiceProviderException` on `event: error` with the sidecar JSON message (fallback text otherwise) — the error data line never yields as content (T-04).
- Created `AiServiceProviderException` (Δ9/D-12) as a verbatim 9-line `RuntimeException` clone of `AiServiceAuthException`.
- Shipped `AiServiceChatTest` (7 test methods covering the plan's 6 named tests — test 5 bundles the 401 and connection-failure cases) with the full fake stack: `Http::fake` string-body SSE, `Http::preventStrayRequests()`, `config(['services.ai_sidecar.token' => 'test-token'])`, fixture-based payload assertions — plus the byte-exact `chat-stream.txt` fixture (`data: CEIT \n\ndata: Library \n\ndata: [DONE]\n\n`).

## Task Commits

Each task was committed atomically:

1. **Task 1: Δ6 — shared request() helper refactor** - `15a9635f` (refactor)
2. **Task 2: Δ9 — AiServiceProviderException** - `5fd94ebf` (feat)
3. **Task 3: Δ7+Δ10 — chatStream() streamed POST, retries 0** - `872e7fa1` (feat)
4. **Task 4: Δ11 — chatStreamEvents() SSE parser** - `4cbeeb10` (feat)
5. **Task 5: AiServiceChatTest + chat-stream.txt fixture** - `5e676571` (test, includes parser resource-hoisting fix)

**Plan metadata:** `5e676571` includes the Task 5 summary file below (committed after this file).

## Files Created/Modified
- `app/Services/AiService.php` - Modified: `request()` helper extracted (Δ6); `chatStream()` added (Δ7/Δ10); `chatStreamEvents()` SSE parser added (Δ8/Δ11)
- `app/Exceptions/AiServiceProviderException.php` - Created: typed exception for sidecar `event: error` (Δ9)
- `tests/Feature/AiServiceChatTest.php` - Created: 7 tests, full fake stack (Δ6-Δ11 coverage)
- `tests/fixtures/ai-sidecar/chat-stream.txt` - Created: canonical SSE body fixture, 43 bytes, three data events ending `data: [DONE]\n\n`

## Decisions Made
- `chatStreamEvents()` hoists a single `$stream = $response->resource()` rather than re-calling `resource()` per `feof`/`fgets`/error-data read. Laravel 13's `StreamWrapper::getResource()` wraps the SAME underlying PSR stream each call; interleaved fresh wrappers share one pointer, and PHP's `feof()` probe-read on one wrapper drains the stream so a later wrapper's `fgets()` returns EOF. Hoisting makes the parser correct on real streamed responses and fakes alike (verified empirically in probes; failing tests reproduced exactly with the multi-resource form).
- Kept the plan's error-fallback text verbatim: `'The AI provider is temporarily unavailable.'` when the error event's JSON lacks a `message` key.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] chatStreamEvents() re-called `$response->resource()` per read, which reads EOF after the first chunk**
- **Found during:** Task 5 (AiServiceChatTest acceptance run — `it_reads_sse_chunks_in_order_and_stops_at_done` yielded only `['CEIT ']`; `it_throws_provider_exception_on_error_event` saw no `event: error` line)
- **Issue:** The plan specified a `while (! feof($response->resource()))` loop calling `fgets($response->resource())`. Each `resource()` call creates a fresh `StreamWrapper` over the same shared PSR body; PHP's `feof()` performs a probe-read on its wrapper, draining the shared stream so subsequent fresh wrappers read EOF immediately. Root cause isolated with 12 probe scripts: single-hoisted-resource reads work; multi-resource reads lose all data after the first wrapper touches the stream. (Secondary finding: `Http::fake()` merges stub callbacks per Factory instance and the first URL match wins — irrelevant across PHPUnit tests because each test gets a fresh application, but a trap for scripts and for later widget tests that should pass closures or fresh responses.)
- **Fix:** Hoist `$stream = $response->resource();` once at generator start; all `feof`/`fgets`/error-data reads use that one resource. Loop shape otherwise matches the plan.
- **Files modified:** app/Services/AiService.php
- **Verification:** `php artisan test --filter=AiServiceChatTest` 7 passed; `php artisan test --filter=AiServiceTest` 11 passed; full suite 530 passed / 3 skipped (1495 assertions)
- **Committed in:** 5e676571 (Task 5 commit — the fix landed with its proving tests)

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** The fix was necessary for correctness — the parser as specified would silently truncate every stream after the first chunk in real usage. No scope creep; no signature or contract changes.

## Issues Encountered
- Laravel 13's `Http\Client\Response::resource()` returns a NEW `StreamWrapper` per call over one shared PSR body — reading through interleaved wrappers loses data (see deviation 1). Documented here for the 09-04 widget: read via a single hoisted resource.
- `Http::fake()` accumulates stub callbacks on the Factory (first URL pattern match wins; Response-instance stubs are served by reference, so a reused instance appears drained). Across PHPUnit tests this is masked by fresh applications per test; not a defect to fix in this plan.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- `AiService` chat client + typed provider failure + testable SSE parser are in place with green tests — ready for 09-03 (ai_conversations/ai_messages schema + models), which 09-04's widget will use with `chatStream()`/`chatStreamEvents()`.
- No blockers.

---
*Phase: 09-rag-chat-policy-q-a*
*Completed: 2026-08-14*
