---
phase: 09-rag-chat-policy-q-a
plan: 01
subsystem: api
tags: [fastapi, sse, streaming, error-handling, validation, refusal, openai-sdk]

# Dependency graph
requires:
  - phase: 08-hybrid-search-foundation
    provides: HybridSearch RRF engine, /search endpoint, token middleware, index lifecycle
provides:
  - ADR 0004/0006-compliant /chat/stream: JSON provider_error event, auth_failed 401 code, corpus validation, programmatic empty-retrieval refusal with zero LLM calls, no-buffer stream headers
  - Skipped-by-default live chat smoke test (SIDECAR_LIVE_CHAT_TEST=1)
affects: [09-02 AiService chatStream client, 09-04/09-05 widget + citations binding]

# Tech tracking
tech-stack:
  added: [none — stdlib json/logging only]
  patterns:
    - "SSE error events carry JSON payloads, never exception class names; details go to logger.error(repr(exc)) server-side only"
    - "Programmatic refusal branch guards LLM construction: empty retrieval yields the canonical string with zero provider calls"
    - "Env-gated live smoke tests (SIDECAR_LIVE_CHAT_TEST=1) extend the Phase 8 SidecarLiveTest discipline to the LLM path"

key-files:
  created:
    - ceit-ai-sidecar/tests/test_chat_stream_live.py
  modified:
    - ceit-ai-sidecar/app/rag.py
    - ceit-ai-sidecar/app/main.py
    - ceit-ai-sidecar/tests/test_chat_stream.py
    - ceit-ai-sidecar/tests/test_api.py
    - ceit-ai-sidecar/tests/test_rag.py

key-decisions:
  - "Error event carries JSON {\"code\": \"provider_error\", \"message\": <safe generic text>}; exception repr goes to server logs only (T-01)"
  - "401 envelope code changed from invalid_request to auth_failed — auth is not a validation error (T-02)"
  - "corpus validated to catalog/policy/absent with 422 invalid_request — protects the policy/catalog corpora from malformed queries (T-03)"
  - "Empty-retrieval refusal is programmatic before any client construction: `if not results:` in both stream_events() and answer(); no score threshold, one grounding rule for both corpora (D-23/D-24)"
  - "StreamingResponse carries Cache-Control: no-cache and X-Accel-Buffering: no for proxy no-buffering (T-05)"

patterns-established:
  - "Breaking test assertions ship in the same commit as their fix (test_api.py 401 code with Δ2, test_chat_stream.py error body with Δ1)"

requirements-completed: [SEARCH-04, CHAT-04]

# Metrics
duration: 38min
completed: 2026-08-14
---

# Phase 9 Plan 1: Sidecar Chat Contract Summary

**JSON provider_error SSE events, auth_failed 401 code, corpus value validation, programmatic empty-retrieval refusal with zero LLM calls, and no-buffer stream headers on POST /chat/stream — bringing the sidecar chat surface into full ADR 0004/0006 compliance (Δ1–Δ5) with a skipped-by-default live smoke test**

## Performance

- **Duration:** 38 min
- **Started:** 2026-08-14T11:05:00Z
- **Completed:** 2026-08-14T11:43:00Z
- **Tasks:** 5
- **Files modified:** 6 (5 modified + 1 created)

## Accomplishments
- Δ1: provider failures now emit `event: error` carrying JSON `{"code": "provider_error", "message": "The AI provider is temporarily unavailable. Please try again."}` — the exception class name no longer leaks to the client; `logger.error(repr(exc))` keeps details server-side
- Δ2: unauthenticated requests get 401 with `"code": "auth_failed"` (was `invalid_request`), letting clients branch auth vs validation errors
- Δ3: `corpus` is validated to `catalog`/`policy`/absent; `corpus: "bogus"` → 422 `invalid_request` (new test)
- Δ4: `RagService.stream_events()` and `answer()` short-circuit on empty retrieval — exact body `data: I don't have enough information\n\n` + `data: [DONE]\n\n` with the fake client recording zero `create()` calls (SEARCH-04 guaranteed, zero token cost)
- Δ5: `StreamingResponse` carries `Cache-Control: no-cache` + `X-Accel-Buffering: no`; existing streaming test now asserts both headers
- Added `tests/test_chat_stream_live.py` — real-provider chat smoke, skipped unless `SIDECAR_LIVE_CHAT_TEST=1`, no API key in the file (key stays in gitignored `.env`)

## Task Commits

Each task was committed atomically (sidecar repo `ceit-ai-sidecar`, branch main):

1. **Task 1: Δ1 — JSON error event in rag.py + update breaking test** - `4c96106` (fix)
2. **Task 2: Δ2 — 401 error code auth_failed + update breaking test** - `40f84cd` (fix)
3. **Task 3: Δ3 + Δ5 — corpus value validation and stream headers in chat_stream** - `90b0531` (feat)
4. **Task 4: Δ4 — programmatic empty-retrieval refusal, no LLM call** - `60eb122` (feat)
5. **Task 5: Skipped-by-default live chat smoke test** - `6686e4c` (test)

## Files Created/Modified
- `ceit-ai-sidecar/app/rag.py` - Added `json`/`logging` imports + module logger; error event now JSON payload with `logger.error(repr(exc))`; `if not results:` refusal guard at top of `stream_events()` and before `_ensure_client()` in `answer()`; module docstring updated (refusal is now programmatic on empty retrieval, ADR 0006)
- `ceit-ai-sidecar/app/main.py` - 401 code `invalid_request` → `auth_failed`; corpus membership check in `chat_stream`; `StreamingResponse` with `Cache-Control`/`X-Accel-Buffering` headers
- `ceit-ai-sidecar/tests/test_chat_stream.py` - Error-event test asserts `'"code": "provider_error"'` (dropped `"RuntimeError" in resp.text`); header assertions on streaming test; new 422 bogus-corpus test; new empty-retrieval refusal test asserting exact body + zero calls; `FakeCompletions` now records `calls`
- `ceit-ai-sidecar/tests/test_api.py` - 401 test asserts `auth_failed` instead of `invalid_request`
- `ceit-ai-sidecar/tests/test_rag.py` - Error-event test parses the data line as JSON, asserts `code == "provider_error"`; new stream_events + answer refusal tests with zero-call assertions
- `ceit-ai-sidecar/tests/test_chat_stream_live.py` - New: env-gated (`SIDECAR_LIVE_CHAT_TEST=1`) live chat smoke over the real provider

## Decisions Made
None beyond the locked plan — every Δ shipped exactly as specified. One doc-accuracy touch: the `rag.py` module docstring's "refusal is prompt-only… separate decision tracked" paragraph was rewritten to state the now-implemented programmatic empty-retrieval refusal, in the Task 4 commit.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] JSON parse of error event picked the wrong line**
- **Found during:** Task 1 (Δ1 — JSON error event + tests)
- **Issue:** Initial `splitlines()[-1]` on the `event: error\ndata: {...}\n\n` payload yields an empty string (trailing blank line), so `json.loads` raised `JSONDecodeError` — 1 failing test after the first implementation pass
- **Fix:** Parse the line with the `data: ` prefix via `next(line for line in ... if line.startswith("data: "))` — robust to any trailing framing
- **Files modified:** tests/test_rag.py
- **Verification:** `uv run pytest tests/test_chat_stream.py tests/test_rag.py` → 19 passed; full suite green
- **Committed in:** `4c96106` (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Test-only correctness fix within the planned task's scope. No scope creep, no API surface change.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required. (The live smoke test is operator-gated: set `SIDECAR_LIVE_CHAT_TEST=1`, run `php artisan ai:export-corpus` first since tests delete corpus files, and keep the provider key in the gitignored sidecar `.env`.)

## Next Phase Readiness
- `/chat/stream` now serves the ADR 0004/0006 contract — the `AiService.chatStream()` client (plan 09-02) can compile against `auth_failed` / `invalid_request` / `provider_error` / the canonical refusal string
- `corpus: policy` flows through validation; the policy-forwarding prompt-wiring test (`test_chat_stream_feeds_search_results_into_prompt`) still passes (CHAT-04 server side)
- Full sidecar suite: **46 passed, 1 skipped** (live smoke), ruff clean, `invalid_request` remains only on the 422 `_invalid` helper

---
*Phase: 09-rag-chat-policy-q-a*
*Completed: 2026-08-14*
