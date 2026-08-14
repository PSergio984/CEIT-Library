---
phase: 9
slug: rag-chat-policy-q-a
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-08-14
---

# Phase 9 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 13 (Laravel `php artisan test`) + pytest (sidecar `uv run pytest`) |
| **Config file** | `phpunit.xml` (Laravel) / `pyproject.toml` (sidecar, uv) |
| **Quick run command** | `php artisan test --filter <Class>` / `uv run pytest tests/test_chat_stream.py` |
| **Full suite command** | `php artisan test` (523 pass / 3 skip, ~51s) + `uv run pytest` (42 tests, sidecar root) |
| **Estimated runtime** | ~55s combined |

---

## Sampling Rate

- **After every task commit:** Run the task's seam file(s) from §Per-Task Verification Map (100% of the touched seam).
- **After every plan wave:** Full `php artisan test` once per wave end (sidecar suite runs fully per contract-fix task — it's seconds).
- **Before `$gsd-verify-work`:** Full suite must be green (both repos).
- **Max feedback latency:** ~60s (full Laravel suite); seconds for seam files.

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| Δ1-Δ5 sidecar contract fixes | TBD | 1 | SEARCH-04, CHAT-04 | D-04..D-07, D-23 | 401 `auth_failed`; JSON error event; corpus validated; empty-retrieval refusal, no LLM call | pytest | `uv run pytest` (whole sidecar suite) | no | pending |
| AiService refactor + chatStream | TBD | 1 | CHAT-01 | D-09..D-12 | Shared request helper; typed Provider exception; fake SSE via `Http::fake` + `fgets($response->resource())` | PHPUnit | `php artisan test --filter AiServiceTest` + new `AiServiceChatTest` | no | pending |
| Schema + models | TBD | 1 | CHAT-02 | D-13..D-19 | Lazy create; auto-title (120); flat ordering + touch(); cascade; auth scoping | PHPUnit | `php artisan test --filter ConversationTest` + `MessageTest`; `migrate:fresh --env=testing` | no | pending |
| Widget + drawer + list | TBD | 2 | CHAT-01, CHAT-02 | D-25..D-33 | Widget persists messages; list default view; entry loads messages asc; failure banner + Retry replaces turn; launcher behind auth | PHPUnit Livewire::test | new widget test file | no | pending |
| Citations binding | TBD | 2 | SEARCH-03 | D-20..D-22 | Companion `/search` same corpus+top_k; payload `{n,id,corpus,title,url,catalog_code}`; catalog link chip / policy non-link | PHPUnit + pytest | binding test + widget render test | no | pending |
| LLM isolation | — | all | all | — | Fake clients only; `Http::preventStrayRequests()`; no real key in fixtures; live smoke gated `SIDECAR_LIVE_TEST=1` | both | full suites | yes | enforced |

**Breaking tests to update in the same commit as the fixes:** `ceit-ai-sidecar/tests/test_api.py:60` (401 code) and `ceit-ai-sidecar/tests/test_chat_stream.py:206` (error event body).

---

## LLM Isolation Contract

1. Sidecar: fake-client injection is the only chat test path (42 tests, zero OpenRouter calls today); extend the smoke discipline into a skipped-by-default `test_chat_stream_live.py` gated by `SIDECAR_LIVE_CHAT_TEST=1`.
2. Laravel: `Http::fake()` + `Http::preventStrayRequests()` in every widget/AiService test — stray requests to `http://127.0.0.1:8310/*` fail loudly.
3. Never commit the real API key; `config(['services.ai_sidecar.token' => 'test-token'])` per test.
4. Live smoke only after `php artisan ai:export-corpus` (tests delete corpus files) and with a rotated key.
