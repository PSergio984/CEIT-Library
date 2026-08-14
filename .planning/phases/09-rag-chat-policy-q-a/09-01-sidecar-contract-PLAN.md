---
phase: 09-rag-chat-policy-q-a
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - ceit-ai-sidecar/app/main.py
  - ceit-ai-sidecar/app/rag.py
  - ceit-ai-sidecar/tests/test_api.py
  - ceit-ai-sidecar/tests/test_chat_stream.py
  - ceit-ai-sidecar/tests/test_rag.py
  - ceit-ai-sidecar/tests/test_chat_stream_live.py
autonomous: true
requirements: [SEARCH-04, CHAT-04]
must_haves:
  truths:
    - "POST /chat/stream with corpus \"bogus\" returns 422 {\"error\":{\"code\":\"invalid_request\"}}; corpus catalog/policy/absent accepted (D-07, Δ3)"
    - "Empty retrieval yields exactly `data: I don't have enough information\n\n` then `data: [DONE]\n\n` with zero LLM calls (SEARCH-04, D-23/D-24, Δ4)"
    - "401 responses carry error.code `auth_failed`; provider failures emit `event: error` with JSON {\"code\":\"provider_error\",\"message\":...} — never an exception class name (D-05/D-04, Δ1/Δ2)"
    - "StreamingResponse carries Cache-Control: no-cache and X-Accel-Buffering: no (Δ5)"
  artifacts:
    - path: ceit-ai-sidecar/tests/test_chat_stream_live.py
      provides: "Skipped-by-default live chat smoke (SIDECAR_LIVE_CHAT_TEST=1) mirroring the SidecarLiveTest discipline"
      contains: "SIDECAR_LIVE_CHAT_TEST"
  key_links:
    - from: ceit-ai-sidecar/app/main.py
      to: ceit-ai-sidecar/app/rag.py
      via: "chat_stream wires rrf_search results into RagService.stream_events()"
      pattern: "stream_events"
---

<objective>
Bring the sidecar's chat surface into full ADR 0004/0006 compliance (Δ1–Δ5): JSON error events instead of leaked exception names (Δ1), 401 code `auth_failed` (Δ2), corpus value validation (Δ3), programmatic empty-retrieval refusal with no LLM call (Δ4), and stream response headers (Δ5). The two breaking test assertions change in the same commits as their fixes. A skipped-by-default live chat smoke test (`SIDECAR_LIVE_CHAT_TEST=1`) extends the Phase 8 live-test discipline to the LLM path — never run in CI by default.

Purpose: SEARCH-04 (grounded refusal) and CHAT-04 (policy Q&A grounded in the rulebook corpus) are enforced server-side here: refusal is the sidecar's programmatic contract, and corpus validation protects the policy/catalog corpora from malformed queries. The AiService client (09-02) and widget (09-04/09-05) compile against this fixed contract.

Output: Sidecar suite green (`uv run pytest` — 42 existing + new tests), Δ1–Δ5 shipped, one skipped-by-default live test.

Commit discipline: each task is one focused commit; the two breaking test updates ship bundled with their fix commits (Δ1 with test_chat_stream.py:206, Δ2 with test_api.py:60).
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
@docs/adr/0004-sidecar-chat-endpoint-contract.md
@docs/adr/0006-citation-and-grounding-rules.md
</context>

<threat_model>
ASVS L1. Block on HIGH severity threats. This plan changes the network-facing chat endpoint's error surface and refusal behavior.

| Threat | Severity | Mitigation in this plan |
|---|---|---|
| T-01 Provider failure details (exception class, trace) leak to the client via the SSE error event | HIGH | Δ1: error event carries only JSON `{"code": "provider_error", "message": "The AI provider is temporarily unavailable. Please try again."}`; `logger.error(repr(exc))` keeps details server-side only. Enforced by the updated test_chat_stream.py assertions (`"RuntimeError" in resp.text` removed, `"provider exploded" not in resp.text` kept). |
| T-02 Unauthenticated /chat/stream not distinguishable from malformed requests | HIGH | Δ2: 401 envelope code becomes `auth_failed` (constant-time `secrets.compare_digest` compare already in place); clients can branch on the code. Enforced by test_api.py (401 test asserts `auth_failed`) and the existing token-required test. |
| T-03 Arbitrary `corpus` values drive unbounded/mixed retrieval | MED | Δ3: `corpus` validated to `catalog`/`policy`/absent with 422 `invalid_request` otherwise. Enforced by a new 422 test. |
| T-04 Empty retrieval spends tokens hallucinating instead of refusing | HIGH | Δ4: programmatic branch in `stream_events()` (and `answer()`) yields the canonical refusal with zero client construction; tests assert the fake client records zero calls. |
| T-05 Proxies buffer the stream, killing perceived streaming | LOW | Δ5: `Cache-Control: no-cache` + `X-Accel-Buffering: no` on the `StreamingResponse`. |

No HIGH-severity threat is left without a mitigation — nothing blocks this plan.
</threat_model>

<tasks>

<task type="auto">
  <name>Task 1: Δ1 — JSON error event in rag.py + update breaking test (same commit)</name>
  <files>ceit-ai-sidecar/app/rag.py, ceit-ai-sidecar/tests/test_chat_stream.py, ceit-ai-sidecar/tests/test_rag.py</files>
  <read_first>ceit-ai-sidecar/app/rag.py, ceit-ai-sidecar/tests/test_chat_stream.py, ceit-ai-sidecar/tests/test_rag.py, .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.8 ANALOG C)</read_first>
  <action>
  - In `ceit-ai-sidecar/app/rag.py`, add `import json` and `import logging`; create a module logger via `logger = logging.getLogger(__name__)`.
  - In `RagService.stream_events()`, replace the current bare-exception yield (`yield f"event: error\ndata: {type(exc).__name__}\n\n"`) with: `logger.error(repr(exc))`, then `yield f"event: error\ndata: {json.dumps({'code': 'provider_error', 'message': 'The AI provider is temporarily unavailable. Please try again.'})}\n\n"`. Keep `yield "data: [DONE]\n\n"` as the LAST event.
  - Same commit: update `ceit-ai-sidecar/tests/test_chat_stream.py` `test_chat_stream_emits_error_event_on_provider_failure`: remove `assert "RuntimeError" in resp.text`; assert `"event: error" in resp.text`, `'"code": "provider_error"' in resp.text`, `"provider exploded" not in resp.text`, and the body still endswith `"data: [DONE]\n\n"`.
  - Same commit: extend `ceit-ai-sidecar/tests/test_rag.py` `test_stream_events_emits_error_event_on_provider_failure` to parse the error event's data line as JSON and assert `code == "provider_error"`.
  </action>
  <verify>cd C:\Users\admin\Herd\ceit-ai-sidecar; uv run pytest tests/test_chat_stream.py tests/test_rag.py</verify>
  <acceptance_criteria>
  - `ceit-ai-sidecar/app/rag.py` contains the literal `"code": "provider_error"` and `logger.error(repr(exc))`; the error event no longer interpolates `type(exc).__name__`
  - `ceit-ai-sidecar/tests/test_chat_stream.py` contains `'"code": "provider_error"' in resp.text` and does NOT contain `"RuntimeError" in resp.text`
  - `ceit-ai-sidecar/tests/test_rag.py` error-event test parses the data payload and asserts `code == "provider_error"`
  - `uv run pytest tests/test_chat_stream.py tests/test_rag.py` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 2: Δ2 — 401 error code auth_failed + update breaking test (same commit)</name>
  <files>ceit-ai-sidecar/app/main.py, ceit-ai-sidecar/tests/test_api.py</files>
  <read_first>ceit-ai-sidecar/app/main.py, ceit-ai-sidecar/tests/test_api.py, .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.8 ANALOG B)</read_first>
  <action>
  - In `ceit-ai-sidecar/app/main.py`, the `require_token` HTTP middleware's 401 JSONResponse: change `"code": "invalid_request"` to `"code": "auth_failed"` (message `"missing or invalid X-Sidecar-Token"` unchanged).
  - Same commit: update `ceit-ai-sidecar/tests/test_api.py` `test_search_without_token_is_401` — the assertion on `resp.json()["error"]["code"]` changes from `"invalid_request"` to `"auth_failed"`.
  </action>
  <verify>cd C:\Users\admin\Herd\ceit-ai-sidecar; uv run pytest tests/test_api.py</verify>
  <acceptance_criteria>
  - `ceit-ai-sidecar/app/main.py` contains `"code": "auth_failed"` inside the 401 JSONResponse body
  - `ceit-ai-sidecar/tests/test_api.py` asserts `resp.json()["error"]["code"] == "auth_failed"` and no longer references `"invalid_request"` for the 401 case
  - `uv run pytest tests/test_api.py` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 3: Δ3 + Δ5 — corpus value validation and stream headers in chat_stream</name>
  <files>ceit-ai-sidecar/app/main.py, ceit-ai-sidecar/tests/test_chat_stream.py</files>
  <read_first>ceit-ai-sidecar/app/main.py (chat_stream + _invalid helper), ceit-ai-sidecar/tests/test_chat_stream.py (make_client + test_chat_stream_rejects_non_numeric_top_k style)</read_first>
  <action>
  - In `ceit-ai-sidecar/app/main.py` `chat_stream()`, immediately after `corpus = payload.get("corpus") or None`, add: if `corpus` is not None and `corpus` not in ("catalog", "policy") → `return _invalid("'corpus' must be catalog, policy or omitted")`.
  - Change the return to `StreamingResponse(events, media_type="text/event-stream", headers={"Cache-Control": "no-cache", "X-Accel-Buffering": "no"})`.
  - Add to `ceit-ai-sidecar/tests/test_chat_stream.py`: (a) a test posting `{"query": "school ID", "corpus": "bogus"}` with the token → 422 and `resp.json()["error"]["code"] == "invalid_request"`; (b) extend the existing 200 streaming test to assert `resp.headers["cache-control"] == "no-cache"` and `resp.headers["x-accel-buffering"] == "no"`.
  </action>
  <verify>cd C:\Users\admin\Herd\ceit-ai-sidecar; uv run pytest tests/test_chat_stream.py</verify>
  <acceptance_criteria>
  - `ceit-ai-sidecar/app/main.py` contains the corpus membership check with message `'corpus' must be catalog, policy or omitted`
  - The `StreamingResponse` in `chat_stream` passes headers containing both `Cache-Control` and `X-Accel-Buffering`
  - New test posts `corpus: "bogus"` and asserts 422 + `"code": "invalid_request"`
  - `uv run pytest tests/test_chat_stream.py` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 4: Δ4 — programmatic empty-retrieval refusal, no LLM call</name>
  <files>ceit-ai-sidecar/app/rag.py, ceit-ai-sidecar/tests/test_chat_stream.py, ceit-ai-sidecar/tests/test_rag.py</files>
  <read_first>ceit-ai-sidecar/app/rag.py (stream_events + answer), ceit-ai-sidecar/tests/test_chat_stream.py (FakeCompletions — records no calls yet; extended in this task), ceit-ai-sidecar/tests/test_rag.py (fake_client fixture + FakeCompletions)</read_first>
  <action>
  - In `RagService.stream_events()`, add at the TOP (before any client construction or retrieval logic): `if not results: yield "data: I don't have enough information\n\n"; yield "data: [DONE]\n\n"; return`.
  - In `RagService.answer()`, add the same guard before `_ensure_client()`: `if not results: return "I don't have enough information"`.
  - No score threshold and no mode check — one grounding rule for both corpora (D-23/D-24).
  - Add to `ceit-ai-sidecar/tests/test_chat_stream.py`: `test_chat_stream_refuses_on_empty_retrieval_without_llm_call` using `make_client(tmp_path, corpus_path, [])` (empty engine results): assert `resp.status_code == 200`, `resp.text == "data: I don't have enough information\n\n" + "data: [DONE]\n\n"` exactly, and the injected fake client recorded zero calls (`main_mod._rag._client.chat.completions.calls == []`).
  - Same commit: extend `FakeCompletions` in `ceit-ai-sidecar/tests/test_chat_stream.py` (lines ~23-33) — it currently has no `calls` attribute (only `content`/`fail`), so the `main_mod._rag._client.chat.completions.calls == []` assertion would raise AttributeError: add `self.calls = []` in `__init__` and `self.calls.append(kwargs)` at the top of `create()`, mirroring the call-recording fake in test_rag.py.
  - Add to `ceit-ai-sidecar/tests/test_rag.py`: `list(service.stream_events("q", [], mode="citations")) == ["data: I don't have enough information\n\n", "data: [DONE]\n\n"]`, and `service.answer("q", []) == "I don't have enough information"` with the fake client recording zero calls.
  </action>
  <verify>cd C:\Users\admin\Herd\ceit-ai-sidecar; uv run pytest tests/test_chat_stream.py tests/test_rag.py</verify>
  <acceptance_criteria>
  - `ceit-ai-sidecar/app/rag.py` `stream_events` returns the refusal pair before constructing any client; `answer()` returns the canonical string on empty results
  - New chat-stream test asserts the exact body `data: I don't have enough information\n\n` + `data: [DONE]\n\n` and `main_mod._rag._client.chat.completions.calls == []`
  - `uv run pytest tests/test_chat_stream.py tests/test_rag.py` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 5: Skipped-by-default live chat smoke test (SIDECAR_LIVE_CHAT_TEST=1)</name>
  <files>ceit-ai-sidecar/tests/test_chat_stream_live.py</files>
  <read_first>ceit-ai-sidecar/tests/test_chat_stream.py (make_client pattern), C:\Users\admin\Herd\CEIT-Library\tests\Feature\SidecarLiveTest.php (env-gate discipline)</read_first>
  <action>
  - Create `ceit-ai-sidecar/tests/test_chat_stream_live.py`: module-level skip via `pytest.mark.skipif(os.environ.get("SIDECAR_LIVE_CHAT_TEST") != "1", reason="Set SIDECAR_LIVE_CHAT_TEST=1 to run the live LLM chat smoke.")`.
  - One test: POST `http://127.0.0.1:8310/chat/stream` with header `X-Sidecar-Token` from `os.environ["SIDECAR_TOKEN"]` and body `{"query": "school ID", "mode": "citations", "corpus": "policy", "top_k": 3}`; assert `status_code == 200`, content-type starts with `text/event-stream`, the body contains at least one `data: ` chunk, and endswith `data: [DONE]\n\n`.
  - No API key in the file; the key stays in the sidecar `.env` (gitignored). This test exercises the real provider only when explicitly enabled by the operator, after `php artisan ai:export-corpus` (tests delete corpus files).
  </action>
  <verify>cd C:\Users\admin\Herd\ceit-ai-sidecar; uv run pytest tests/</verify>
  <acceptance_criteria>
  - `ceit-ai-sidecar/tests/test_chat_stream_live.py` exists and contains the literal `SIDECAR_LIVE_CHAT_TEST`
  - The file contains no `LLM_API_KEY` literal and no real key value
  - `uv run pytest tests/` (default env) exits 0 with the live test reported as skipped
  </acceptance_criteria>
</task>

</tasks>

<verification>
- [ ] `cd C:\Users\admin\Herd\ceit-ai-sidecar; uv run pytest` — full sidecar suite green (42 existing + new tests + 1 skipped-by-default)
- [ ] `cd C:\Users\admin\Herd\ceit-ai-sidecar; uv run ruff check app/ tests/` — exits 0
- [ ] The only remaining `invalid_request` code in main.py is the 422 `_invalid` helper; the 401 path emits `auth_failed`
</verification>

<success_criteria>
- All 5 tasks complete; Δ1–Δ5 shipped with their tests in the same commits
- Empty-retrieval refusal is programmatic with zero LLM calls (SEARCH-04)
- `corpus: policy` flows through validation and the policy-forwarding prompt-wiring test still passes (CHAT-04 server side)
</success_criteria>

<output>
After completion, create `.planning/phases/09-rag-chat-policy-q-a/09-01-SUMMARY.md`
</output>
