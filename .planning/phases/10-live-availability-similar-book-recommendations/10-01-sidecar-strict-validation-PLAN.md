---
phase: 10-live-availability-similar-book-recommendations
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - ceit-ai-sidecar/app/main.py
  - ceit-ai-sidecar/tests/test_api.py
  - ceit-ai-sidecar/tests/test_chat_stream.py
autonomous: true
requirements: [SEARCH-02]
must_haves:
  truths:
    - "POST /search with any key outside {query, filters, corpus, limit, k} returns 422 {\"error\":{\"code\":\"invalid_request\"}} — enforced by a _reject_unknown(payload, allowed) helper as the FIRST statement of search() in ceit-ai-sidecar/app/main.py (D-06/D-07 #3, SEARCH-02)"
    - "POST /chat/stream with any key outside {query, mode, corpus, top_k} returns 422 invalid_request — same helper applied as the first statement of chat_stream() (D-06/D-07 #3)"
    - "Enforcement adds no new fields, no new endpoints, and no response-shape change: valid-payload responses from /search and /chat/stream are byte-identical to today (ADR 0004 no-contract-change posture)"
  artifacts:
    - path: ceit-ai-sidecar/app/main.py
      provides: "Module-level SEARCH_ALLOWED_KEYS/CHAT_ALLOWED_KEYS constants + _reject_unknown() helper beside _invalid(), called first in both endpoints"
      contains: "SEARCH_ALLOWED_KEYS"
    - path: ceit-ai-sidecar/tests/test_api.py
      provides: "test_search_rejects_unknown_fields — the /search half of D-07 #3"
      contains: "test_search_rejects_unknown_fields"
    - path: ceit-ai-sidecar/tests/test_chat_stream.py
      provides: "test_chat_stream_rejects_unknown_fields — the /chat/stream half of D-07 #3"
      contains: "test_chat_stream_rejects_unknown_fields"
  key_links:
    - from: ceit-ai-sidecar/app/main.py
      to: ceit-ai-sidecar/app/main.py
      via: "search() and chat_stream() call _reject_unknown(payload, ...) before any payload.get() read"
      pattern: "_reject_unknown"
    - from: ceit-ai-sidecar/tests/test_api.py
      to: ceit-ai-sidecar/app/main.py
      via: "422 assertion on a payload carrying an availability key proves a stray availability field can never reach the prompt"
      pattern: "invalid_request"
---

<objective>
Enforce the ADR 0004 closed request schemas on the sidecar (D-06/D-07 #3): reject any unknown field on `/search` (allowed keys `query, filters, corpus, limit, k`) and `/chat/stream` (allowed keys `query, mode, corpus, top_k`) with the existing 422 `invalid_request` envelope, and prove it with pytest tests in `tests/test_api.py` and `tests/test_chat_stream.py`. This is the server-side half of the never-LLM guarantee: a stray `availability` field in a request must 422 rather than be silently ignored, so availability can never reach the model — only the Laravel-side hydrator (10-02/10-05) supplies it, post-response.

Purpose: SEARCH-02's "never from the LLM" is enforced by construction. Today both handlers take a loose `payload: dict` and silently ignore every unknown key (`main.py:88-95` reads `query`/`filters`/`corpus`/`limit`/`k`; `main.py:110-131` reads `query`/`mode`/`corpus`/`top_k`). The 422 tests below FAIL until the validation lands — ship validation and tests in the same commits.

Output: Sidecar suite green with 2 new tests; closed-schema enforcement live on both endpoints. This is an implementation change to enforce the existing contract — no new fields, no new endpoints, no response-shape change (the Laravel `AiService::search()`/`chatStream()` clients post exactly the allowed key sets today, so no Laravel test can break).

Commit discipline: each task is one focused commit; each fix ships bundled with its test.
</objective>

<execution_context>
@$HOME/.codex/get-shit-done/workflows/execute-plan.md
@$HOME/.codex/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/10-live-availability-similar-book-recommendations/10-RESEARCH.md
@.planning/phases/10-live-availability-similar-book-recommendations/10-CONTEXT.md
@.planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md
@.planning/phases/10-live-availability-similar-book-recommendations/10-VALIDATION.md
@docs/adr/0010-live-availability-hydration-contract.md
@docs/adr/0004-sidecar-chat-endpoint-contract.md
@ceit-ai-sidecar/app/main.py
@ceit-ai-sidecar/tests/test_api.py
@ceit-ai-sidecar/tests/test_chat_stream.py
</context>

<threat_model>
ASVS L1. Block on HIGH severity threats. This plan changes the validation surface of two network-facing sidecar endpoints.

| Threat | Severity | Mitigation in this plan |
|---|---|---|
| T-01 Stray availability/other fields silently accepted into /search and /chat/stream — the never-LLM guarantee has no server-side enforcement | HIGH | `_reject_unknown(payload, allowed)` runs as the first statement of both handlers, before any `payload.get()`; unknown fields → 422 `invalid_request` naming the offending keys. Enforced by the two new pytest tests. |
| T-02 Validation bypass via non-dict JSON body (e.g. JSON array/string) | MED | FastAPI's `payload: dict` typed parameter already 400/422s non-object bodies at the framework boundary — unchanged, covered by existing request-shape tests. |
| T-03 Rejection message leaks internal implementation detail | LOW | 422 message lists only the unknown field names (`unknown field(s): ...`), same `_invalid()` envelope style as all existing 422s — no stack/class info. |
| T-04 Valid clients break because a legitimately-used key is missing from an allowed set | HIGH | Allowed sets are copied from the actual Laravel clients: `AiService::search()` posts `{query, filters, corpus, limit, k}` (`AiService.php:29-35`) and `chatStream()` posts `{query, mode, top_k}` + `corpus` only when non-null (`AiService.php:56-60`). Any 09-xx-era test posting only those keys stays green — the full pytest suite run in verification catches drift. |

No HIGH-severity threat is left without a mitigation — nothing blocks this plan.
</threat_model>

<tasks>

<task type="auto">
  <name>Task 1: _reject_unknown helper + SEARCH_ALLOWED_KEYS/CHAT_ALLOWED_KEYS constants in main.py</name>
  <files>ceit-ai-sidecar/app/main.py</files>
  <read_first>ceit-ai-sidecar/app/main.py (current handlers at 87-140 and the _invalid helper at 52-56 — no pydantic models anywhere, hand-rolled dict validation is the house style), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.6)</read_first>
  <action>
  - In `ceit-ai-sidecar/app/main.py`, add module-level constants beside `_invalid()`: `SEARCH_ALLOWED_KEYS = {"query", "filters", "corpus", "limit", "k"}` and `CHAT_ALLOWED_KEYS = {"query", "mode", "corpus", "top_k"}` (frozensets of str — exact ADR 0004 key sets, verified against `AiService.php:29-35` and `AiService.php:56-60`).
  - Add a module-level helper immediately after `_invalid()` (main.py:52-56): `def _reject_unknown(payload: dict, allowed: set[str]) -> JSONResponse | None:` — compute `unknown = set(payload) - allowed`; if non-empty return `_invalid(f"unknown field(s): {', '.join(sorted(unknown))}")`; else return None. Hand-rolled style matching `_require_query` — no FastAPI machinery, no pydantic.
  - Do NOT touch `_require_query`, the token middleware, `/health`, `/index/rebuild`, or `/metrics`.
  </action>
  <verify>cd C:\Users\admin\Herd\ceit-ai-sidecar; uv run ruff check app/main.py</verify>
  <acceptance_criteria>
  - `ceit-ai-sidecar/app/main.py` contains the literals `SEARCH_ALLOWED_KEYS = {"query", "filters", "corpus", "limit", "k"}` and `CHAT_ALLOWED_KEYS = {"query", "mode", "corpus", "top_k"}`
  - The file contains `def _reject_unknown(payload: dict, allowed: set[str])` with `_invalid(f"unknown field(s): ...")` in its body
  - `uv run ruff check app/main.py` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 2: Apply _reject_unknown in search() — /search half of D-06</name>
  <files>ceit-ai-sidecar/app/main.py, ceit-ai-sidecar/tests/test_api.py</files>
  <read_first>ceit-ai-sidecar/app/main.py (search handler at 87-106), ceit-ai-sidecar/tests/test_api.py (client fixture at 16-53, the missing-query 422 test at 79-82 — copy its shape), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.6 + 2.7)</read_first>
  <action>
  - In `search()` (main.py:88), make `_reject_unknown` the first statement: `rejected = _reject_unknown(payload, SEARCH_ALLOWED_KEYS)` then `if rejected: return rejected` — BEFORE `_require_query(payload)` so unknown fields are reported even on a query-less body.
  - In `ceit-ai-sidecar/tests/test_api.py`, add `test_search_rejects_unknown_fields(client)` in the same style as the existing 422 test (client fixture from conftest, header `X-Sidecar-Token: test-token`): POST `/search` with `json={"query": "water pump", "availability": {"77": {"available": 1, "total": 2}}}` → assert `resp.status_code == 422` and `resp.json()["error"]["code"] == "invalid_request"`; assert the message contains `"unknown field(s)"`.
  - Same commit: add a second test `test_search_rejects_exclude_field(client)` posting `json={"query": "water pump", "exclude": ["paper-77"]}` → 422 `invalid_request` (guards the ADR 0011 upgrade path from silently being accepted before it exists).
  - Assert `_get_engine()` is never reached: the test client fixture wraps a real app — the 422 must return before `rrf_search` executes (engine state untouched, visible as an immediate non-200).
  </action>
  <verify>cd C:\Users\admin\Herd\ceit-ai-sidecar; uv run pytest tests/test_api.py</verify>
  <acceptance_criteria>
  - `search()` body starts with the `_reject_unknown(payload, SEARCH_ALLOWED_KEYS)` guard before `_require_query`
  - `tests/test_api.py` contains `test_search_rejects_unknown_fields` asserting 422 + `resp.json()["error"]["code"] == "invalid_request"` and a message containing `"unknown field(s)"`, and `test_search_rejects_exclude_field` asserting 422 on an `exclude` key
  - All pre-existing tests in `tests/test_api.py` still pass: `uv run pytest tests/test_api.py` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 3: Apply _reject_unknown in chat_stream() + /chat/stream 422 test</name>
  <files>ceit-ai-sidecar/app/main.py, ceit-ai-sidecar/tests/test_chat_stream.py</files>
  <read_first>ceit-ai-sidecar/app/main.py (chat_stream handler at 109-140), ceit-ai-sidecar/tests/test_chat_stream.py (make_client + FakeEngine/FakeCompletions fixtures at 23-105, the 422 test patterns at 126-183 — validation runs before retrieval so engine results are never consulted), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.7)</read_first>
  <action>
  - In `chat_stream()` (main.py:110), make `_reject_unknown` the first statement: `rejected = _reject_unknown(payload, CHAT_ALLOWED_KEYS)` then `if rejected: return rejected` — BEFORE `_require_query(payload)`, same ordering as search.
  - In `ceit-ai-sidecar/tests/test_chat_stream.py`, add `test_chat_stream_rejects_unknown_fields` using the existing `make_client(tmp_path, corpus_path, SSE_DOCS)` fixture with `FakeEngine`/`FakeCompletions`: POST `/chat/stream` with `json={"query": "school ID", "availability": "1/2"}` + token header → assert `resp.status_code == 422`, `resp.json()["error"]["code"] == "invalid_request"`, message contains `"unknown field(s)"`, and the FakeCompletions recorded zero calls (validation short-circuits before any RAG work).
  </action>
  <verify>cd C:\Users\admin\Herd\ceit-ai-sidecar; uv run pytest tests/test_chat_stream.py</verify>
  <acceptance_criteria>
  - `chat_stream()` body starts with the `_reject_unknown(payload, CHAT_ALLOWED_KEYS)` guard before `_require_query`
  - `tests/test_chat_stream.py` contains `test_chat_stream_rejects_unknown_fields` asserting 422 + `invalid_request` on an `availability` key and zero fake-LLM calls
  - All pre-existing `tests/test_chat_stream.py` tests still pass: `uv run pytest tests/test_chat_stream.py` exits 0
  </acceptance_criteria>
</task>

</tasks>

<verification>
- [ ] `cd C:\Users\admin\Herd\ceit-ai-sidecar; uv run pytest tests/` — full sidecar suite green (46 passed / 1 skipped baseline + 2 new tests)
- [ ] `cd C:\Users\admin\Herd\ceit-ai-sidecar; uv run ruff check app/ tests/` — exits 0
- [ ] A valid `/search` body `{"query": "x", "filters": {}, "corpus": "catalog", "limit": 10, "k": 60}` and a valid `/chat/stream` body `{"query": "x", "mode": "citations", "corpus": null, "top_k": 5}` still return their normal envelopes (no response-shape change)
- [ ] The only `invalid_request` sources remain the 422 helper and the corpus/top_k value checks — 401 still emits `auth_failed`
</verification>

<success_criteria>
- All 3 tasks complete; validation lands with its tests in the same commits
- D-06 enforced: a stray `availability` (or any unknown) field 422s on both `/search` and `/chat/stream` — availability can never reach the model
- D-07 #3 proven in the sidecar pytest suite; the Laravel-side capture tests (10-02/10-05) complete the five-proof chain
</success_criteria>

<output>
After completion, create `.planning/phases/10-live-availability-similar-book-recommendations/10-01-SUMMARY.md`
</output>
