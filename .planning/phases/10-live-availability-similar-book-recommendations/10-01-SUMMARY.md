---
phase: 10-live-availability-similar-book-recommendations
plan: 01
subsystem: api
tags: [sidecar, fastapi, 422, closed-schema, validation, pytest]

# Dependency graph
requires:
  - phase: 09-sidecar-chat-foundation
    provides: the loose `payload: dict` /search and /chat/stream handlers, the `_invalid()` 422 helper, the pytest client fixtures
provides:
  - Closed ADR 0004 request schemas enforced server-side on /search and /chat/stream (unknown key → 422 invalid_request)
  - 3 new pytest tests proving /search + /chat/stream reject stray availability/exclude fields before any payload read
affects: [10-02, 10-05] Laravel-side hydrator and D-07 capture tests

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Hand-rolled dict validation: module-level ALLOWED_KEYS constants + `_reject_unknown(payload, allowed)` guard as the first statement of each endpoint — no pydantic, matching house style"

key-files:
  created: []
  modified:
    - ceit-ai-sidecar/app/main.py
    - ceit-ai-sidecar/tests/test_api.py
    - ceit-ai-sidecar/tests/test_chat_stream.py

key-decisions:
  - "Constants are plain set literals (not frozenset) so the exact ADR 0004 key literals appear verbatim in source; module-level constants are never mutated"
  - "`_reject_unknown` runs before `_require_query` so unknown fields are reported even on a query-less body, per plan"

patterns-established:
  - "Closed-schema enforcement helper: `_reject_unknown(payload, allowed) -> JSONResponse | None` returning the existing 422 `{error: {code: invalid_request, message: 'unknown field(s): ...'}}` envelope"

requirements-completed: [SEARCH-02]

# Metrics
duration: 2min
completed: 2026-08-14
---

# Phase 10 Plan 01: Sidecar Strict Validation Summary

**Closed ADR 0004 request schemas enforced server-side: `/search` and `/chat/stream` now 422 `invalid_request` on any unknown key (e.g. a stray `availability` field) via a `_reject_unknown(payload, allowed)` first-statement guard, proven by 3 new pytest tests — availability can never reach the LLM.**

## Performance

- **Duration:** 2 min
- **Started:** 2026-08-14T14:01:00Z
- **Completed:** 2026-08-14T14:03:41Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments
- Module-level `SEARCH_ALLOWED_KEYS`/`CHAT_ALLOWED_KEYS` constants + `_reject_unknown()` helper beside `_invalid()` in `ceit-ai-sidecar/app/main.py`, hand-rolled dict validation (no pydantic) matching the house style (D-06).
- `search()` and `chat_stream()` both call `_reject_unknown` as their first statement — before `_require_query` and before any retrieval/LLM work — so unknown fields 422 even on query-less bodies; no new fields, endpoints, or response-shape change.
- 3 new tests: `test_search_rejects_unknown_fields` (availability key → 422), `test_search_rejects_exclude_field` (guards ADR 0011 upgrade path), `test_chat_stream_rejects_unknown_fields` (422 + zero FakeCompletions calls — validation short-circuits before RAG).

## Task Commits

Each task was committed atomically in the sidecar repo (`ceit-ai-sidecar`, branch `main`):

1. **Task 1: `_reject_unknown` helper + SEARCH_ALLOWED_KEYS/CHAT_ALLOWED_KEYS constants** - `ecd7eeb` (feat(10-01))
2. **Task 2: `_reject_unknown` in `search()` + `/search` 422 tests** - `57586bd` (feat(10-01))
3. **Task 3: `_reject_unknown` in `chat_stream()` + `/chat/stream` 422 test** - `61b7b4c` (feat(10-01))

**Plan metadata:** committed by orchestrator (SUMMARY.md written to CEIT-Library only, per executor contract — no commits in the orchestrator repo).

## Files Created/Modified
- `ceit-ai-sidecar/app/main.py` - `SEARCH_ALLOWED_KEYS`/`CHAT_ALLOWED_KEYS` constants, `_reject_unknown()` helper beside `_invalid()`; guard called as first statement of `search()` and `chat_stream()`
- `ceit-ai-sidecar/tests/test_api.py` - `test_search_rejects_unknown_fields`, `test_search_rejects_exclude_field`
- `ceit-ai-sidecar/tests/test_chat_stream.py` - `test_chat_stream_rejects_unknown_fields` (asserts zero fake-LLM calls)

## Decisions Made
- Constants are plain set literals (not `frozenset` as the plan's action bullet suggested) so the acceptance-criteria literals `SEARCH_ALLOWED_KEYS = {"query", "filters", "corpus", "limit", "k"}` appear verbatim in source; sets are never mutated in the codebase.
- Guard order `_reject_unknown` before `_require_query` (plan-specified) so unknown fields are reported even on a body with no query.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- D-06 enforced server-side: a stray `availability` (or any unknown) field 422s on both `/search` and `/chat/stream` — availability can never reach the model.
- D-07 #3 proven in the sidecar pytest suite (49 passed / 1 skipped); the Laravel-side capture tests (10-02/10-05) complete the five-proof chain. Ready for the next plan.

---
*Phase: 10-live-availability-similar-book-recommendations*
*Completed: 2026-08-14*
