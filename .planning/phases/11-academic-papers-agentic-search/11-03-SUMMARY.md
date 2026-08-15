---
phase: 11-academic-papers-agentic-search
plan: 03
subsystem: api
tags: [agentic, function-calling, openai-sdk, fastapi, sse, pydantic, fail-closed]

# Dependency graph
requires:
  - phase: 11-01
    provides: rich single-title catalog corpus docs (authors/advisers/dean/department/year/paper_type/catalog_code in text + metadata)
  - phase: 11-02
    provides: author/adviser case-insensitive substring filter clauses in rrf_search().passes() and the closed-schema endpoint posture
provides:
  - AgenticLoop (app/agent.py): single `search` tool over the closed /search contract, MAX_TOOL_ROUNDS=3 cap, pydantic extra="forbid" arg validation, merge/dedupe/renumber citations, zero-token fail-closed refusal
  - /chat/stream routed through the agentic path: first call is non-streamed tool-eligible (tools=[SEARCH_TOOL], tool_choice="auto"); direct answer when no tool use; activity/citations SSE frames additive to ADR 0002
  - Endpoint-level proof: activity → chunks → citations → [DONE] ordering, chunk envelope {"c": ...} unchanged, single event: error frame mid-loop, zero-LLM refusal
affects: [11-05 (Laravel AiService chatStreamFrames + ChatWidget activity slot), 11-04, phase 13 eval]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "RagService-style injectable client (client/engine/model/max_tokens/prompts with settings fallbacks + lazy _ensure_client)"
    - "Tool-eligibility auto-detect (no classifier): tool-use in the first non-streamed call IS the trigger (D-07, ADR 0014)"
    - "Closed-schema tool-arg validation: JSON schema additionalProperties:false mirrored by pydantic extra='forbid' at both levels; malformed → one corrective turn → fail-closed"
    - "Fail-closed refusal: zero accumulated docs → canonical 'I don't have enough information' with zero LLM calls; cap path answers only from accumulated docs"
    - "Additive SSE event types (event: activity / event: citations) with static copy lines — never data-envelope wrappers (pitfall #1)"

key-files:
  created:
    - ceit-ai-sidecar/app/agent.py
    - ceit-ai-sidecar/tests/test_agentic_loop.py
  modified:
    - ceit-ai-sidecar/app/main.py
    - ceit-ai-sidecar/tests/test_chat_stream.py

key-decisions:
  - "Final answer after tool rounds is a fresh streamed grounded call (citations prompt + build_context over merged docs); the direct-answer path streams the decision call's content as word chunks with NO frames (Laravel falls back to companionCitations)"
  - "Endpoint top_k feeds the loop as default_top_k (tool calls without explicit top_k use it); corpus stays validation-only — the model chooses corpus per tool call via the closed schema"
  - "Malformed-args feedback = assistant tool-call message + tool message describing the schema violation (API-valid corrective turn); second consecutive malformed call → refusal, never executed, never guessed around"
  - "Activity line precedence for the first round: author → adviser → year range → corpus policy → corpus catalog → 'Searching papers…'; every subsequent round is 'Narrowing results…' (UI-SPEC copy, U+2026 ellipsis)"

patterns-established:
  - "Agentic loop as a module-level service with injectable client/engine (unit-testable with FakeCompletionsHolder + FakeEngine — no network)"
  - "Fake completions tool_calls variant: scripted tool_sequence per create() call, 'FAIL' sentinel for mid-loop provider failure"
  - "Citations binding: the frame payload IS the merged deduped doc set renumbered 1..N in merge order — never a fresh companion search (ADR 0006)"

requirements-completed: [CHAT-05]

# Metrics
duration: 34min
completed: 2026-08-15
---

# Phase 11 Plan 03: Agentic Search Loop Summary

**AgenticLoop with a single closed-schema `search` tool, 3-executed-search cap, fail-closed refusal, and additive activity/citations SSE frames — `/chat/stream` now serves the tool-eligible agentic path (CHAT-05)**

## Performance

- **Duration:** 34 min
- **Started:** 2026-08-15T17:35:00Z (approx)
- **Completed:** 2026-08-15T18:09:45Z
- **Tasks:** 3 (Task 1 TDD: RED → GREEN + test-fix commit)
- **Files modified:** 4 (2 created, 2 modified — all in the sidecar repo)

## Accomplishments

- `app/agent.py`: `SEARCH_TOOL` OpenAI function spec (query required, corpus enum, closed filter keys, top_k 1..50, `additionalProperties: false` top-level AND inside filters), `ToolArgs` pydantic mirror with `extra="forbid"`, `MAX_TOOL_ROUNDS = 3` counting executed searches, `merge_dedup` (first-seen order), `citation_payload` (ADR 0006 shape `{n, id, corpus, title, url, catalog_code}`), and `AgenticLoop.stream_agentic_events()` — tool-eligible non-streamed first call, one corrective turn for malformed args then fail-closed, empty-docs refusal with ZERO LLM calls, provider exception → single `event: error` + `[DONE]`
- `/chat/stream` (existing endpoint, no toggle, no new endpoint, no history field) routed through the agentic path: validation/422/401/error taxonomy verbatim; endpoint `top_k` becomes the loop's default for tool calls; 11-02's author/adviser filters ride through `rrf_search` inside the loop automatically
- 9 unit tests in `tests/test_agentic_loop.py` (5 plan-named cases + cap-with-zero-docs refusal, corpus/year activity copy, merge_dedup, ToolArgs closed-schema rejection) and 4 endpoint tests in `tests/test_chat_stream.py` (activity/citations frames on the wire, direct-answer no-tool-usage, zero-LLM refusal, single mid-loop error frame)
- Sidecar suite: **67 passed / 1 skipped** (baseline 55/1 + 12 new); ruff clean across `app/` and `tests/`

## Task Commits

Each task was committed atomically in the sidecar repo (`git -C ceit-ai-sidecar`):

1. **Task 1: app/agent.py — SEARCH_TOOL, ToolArgs, AgenticLoop** — RED `99c1302` (test), GREEN `f6cded2` (feat), test-fix `a95d788` (test)
2. **Task 2: main.py — /chat/stream agentic routing** — `a93fcc7` (feat; validation/error taxonomy preserved, one-shot tests adapted)
3. **Task 3: endpoint-level frame tests** — `0d44e49` (test)

**Plan metadata:** `docs(11-03): complete agentic loop plan` (Laravel repo, after SUMMARY)

## Files Created/Modified

- `ceit-ai-sidecar/app/agent.py` (NEW) - SEARCH_TOOL spec, ToolArgs/ToolFilterArgs (extra="forbid"), merge_dedup, citation_payload, activity_line copy table, AgenticLoop with MAX_TOOL_ROUNDS=3, stream_agentic_events yielding raw SSE strings
- `ceit-ai-sidecar/tests/test_agentic_loop.py` (NEW) - fake stack (FakeCompletionsHolder + tool_calls variant + FakeEngine recording rrf_search calls) and 9 unit tests
- `ceit-ai-sidecar/app/main.py` - `_get_agent()` accessor + `chat_stream()` one-shot body replaced by `stream_agentic_events(query, mode=mode, default_top_k=top_k)`; import added
- `ceit-ai-sidecar/tests/test_chat_stream.py` - fake stack extended with tool_calls variant/tool_sequence; make_client wires shared FakeEngine + holder into `_agent`; 2 one-shot tests adapted; 4 agentic endpoint tests

## Decisions Made

- Final answer after tool rounds = fresh streamed grounded call (citations prompt + `build_context` over merged docs); direct-answer (no tool use) streams the decision content as word chunks with no frames so Laravel's companion-citation fallback stays valid (ADR 0014)
- Endpoint `top_k` → loop `default_top_k`; endpoint `corpus` validated but not forwarded — the model chooses corpus per tool call (D-10)
- Corrective feedback is an API-valid assistant tool-call message + tool message describing the schema violation (pydantic error text); second consecutive malformed call → fail-closed, never executed
- Activity copy precedence locked per the plan's copy table; U+2026 ellipsis; static strings only (T-11-11)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Two pre-existing /chat/stream tests adapted to the ADR 0014 tool-eligible semantics**
- **Found during:** Task 2 (main.py agentic routing)
- **Issue:** `test_chat_stream_refuses_on_empty_retrieval_without_llm_call` and `test_chat_stream_feeds_search_results_into_prompt` asserted the old one-shot ordering (retrieval before any LLM call, results fed into `stream_events`). Under the mandated agentic path (first call tool-eligible), they could not pass "untouched" — the endpoint's behavior legitimately changed by design (ADR 0014, plan Task 2 acceptance requires the file green).
- **Fix:** Refusal test now scripts one tool round returning zero docs → canonical refusal + `[DONE]` + no streamed (final-answer) call; feeds-search test scripts a tool call and asserts the parsed args reach `rrf_search` with the endpoint's `top_k` as the loop default, plus `tools=[SEARCH_TOOL]`/`tool_choice="auto"` on the first call. Refusal test renamed to the plan's `test_empty_retrieval_refusal_is_zero_llm` in Task 3.
- **Files modified:** tests/test_chat_stream.py
- **Verification:** full suite green (67 passed / 1 skipped); validation/422/error-taxonomy tests untouched and green
- **Committed in:** a93fcc7 (adaptations), 0d44e49 (rename)

**2. [Rule 1 - Correctness] Test-only assertion fixes during Task 1 GREEN**
- **Found during:** Task 1 (GREEN run)
- **Issue:** The fake stack's word-split chunking (mirroring the old streamed deltas) made multi-word chunk-frame assertions fail; and the activity-copy variant test wrongly expected corpus/year copy on rounds 2-3 (plan: any subsequent round is "Narrowing results…"). The implementation was correct; the assertions were not.
- **Fix:** Assertions updated to word-level chunk frames; copy-variant test runs one loop per variant.
- **Files modified:** tests/test_agentic_loop.py
- **Verification:** pytest tests/test_agentic_loop.py → 9 passed; ruff clean
- **Committed in:** a95d788

---

**Total deviations:** 2 auto-fixed (1 missing-critical semantics superseded by the plan's own Task 3 spec, 1 test-assertion correctness)
**Impact on plan:** Both were necessary for the mandated acceptance criteria; no scope creep, no architectural change.

## Issues Encountered

- None beyond the two documented deviations. Live LLM calls were not attempted (per execution context: fakes only); `SIDECAR_LIVE_TEST=1` is not set, so the plan's manual/live verification item is skipped by design with this note.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Wire contract for 11-05 is now defined and endpoint-proven: `event: activity` / `data: {"text": "…"}`, `event: citations` / `data: [{n, id, corpus, title, url, catalog_code}]`, chunk envelope `{"c": "…"}` unchanged, `[DONE]` and single `event: error` taxonomy unchanged — Laravel `AiService::chatStreamFrames()` can parse additively and keep `chatStreamEvents()` for back-compat
- The 11-02 author/adviser filters work inside the loop (verified: tool args → `rrf_search` filters)
- 11-04 (paper tab) is independent — no shared files
- Blockers: none. Watch item: live tool-call reliability of the default Balanced OpenRouter routing (RESEARCH pitfall #10) — if malformed calls appear in production, flip routing mode at config level (ADR 0001 posture), not by adding retries

---
*Phase: 11-academic-papers-agentic-search*
*Completed: 2026-08-15*

## Self-Check: PASSED

- Files exist: `ceit-ai-sidecar/app/agent.py` ✓, `ceit-ai-sidecar/tests/test_agentic_loop.py` ✓, `ceit-ai-sidecar/app/main.py` ✓, `ceit-ai-sidecar/tests/test_chat_stream.py` ✓, this SUMMARY ✓
- Sidecar commits exist: 5 × `git -C ceit-ai-sidecar log --grep="11-03"` ✓
- Full suite `uv run pytest tests/` → 67 passed, 1 skipped ✓; `uv run ruff check app/ tests/` → clean ✓; `git -C ceit-ai-sidecar status --porcelain` → empty ✓
- Acceptance criteria: SEARCH_TOOL + `MAX_TOOL_ROUNDS = 3` + `ToolArgs` `extra="forbid"` + `merge_dedup`/`citation_payload` + `stream_agentic_events` ✓; 5 named unit tests + 4 endpoint tests ✓; `main.py` `_get_agent()` + `stream_agentic_events(query, mode=mode, default_top_k=top_k)` with `SEARCH_ALLOWED_KEYS`/`CHAT_ALLOWED_KEYS`/`search()` untouched ✓
- Stub scan: no stubs/placeholders/TODO markers in new code ✓
- Threat flags: T-11-08..T-11-13 all mitigated and covered by tests (closed-schema args, 3-round cap, corrective-turn fail-closed, static activity copy, single error frame, citations bound to merged docs) — no open HIGH-severity threats ✓
- Live LLM check skipped by design (`SIDECAR_LIVE_TEST=1` not set; fakes only) — noted in Issues Encountered ✓
