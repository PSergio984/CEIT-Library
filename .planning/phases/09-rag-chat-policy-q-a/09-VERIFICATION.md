---
phase: 9
phase_slug: rag-chat-policy-q-a
status: passed
date: 2026-08-14
verifier: gsd-verifier
scope: research-only — no code or planning files modified
---

# Phase 9 — RAG Chat & Policy Q&A: Verification Results

## Summary

All 5 plans (26 tasks) executed and committed atomically; all 5 requirements (SEARCH-03, SEARCH-04, CHAT-01, CHAT-02, CHAT-04) delivered per summaries and confirmed by on-disk spot checks plus green full suites in both repos (Laravel 552 passed / 3 skipped, 1549 assertions; sidecar 46 passed / 1 skipped). No missing tasks, no failed self-checks, no unblocking caveats.

## Completion Table per Plan

| Plan | Tasks done / total | Self-check | Commits (all present in git log) |
|------|--------------------|------------|----------------------------------|
| 09-01 sidecar-contract | 5 / 5 | No FAILED marker (none present in summary format) | `4c96106` fix Δ1 · `40f84cd` fix Δ2 · `90b0531` feat Δ3+Δ5 · `60eb122` feat Δ4 · `6686e4c` test live smoke (sidecar repo) |
| 09-02 aiservice-chat | 5 / 5 | No FAILED marker | `15a9635f` refactor Δ6 · `5fd94ebf` feat Δ9 · `872e7fa1` feat Δ7+Δ10 · `4cbeeb10` feat Δ11 · `5e676571` test (incl. parser fix) |
| 09-03 conversation-schema | 5 / 5 | No FAILED marker | `7e934d19` · `d29ea815` · `16948249` · `22e39794` · `aaf03c7e` (migrations, models, factories, tests) |
| 09-04 chat-widget | 7 / 7 | No FAILED marker | `48610079` · `034acbff` · `037fc9e2` · `84b3e71f` · `420dfa0c` · `adc3df3e` · `a1cdcf94` |
| 09-05 citations-grounding | 4 / 4 | No FAILED marker | `fc537e4c` · `ba041c5d` · (Task 3 zero production delta — documented, no empty commit) · `7b4ce2c3` |

**Total: 26/26 tasks accounted for.** Task 3 of 09-05 (refusal rendering) shipped zero delta by plan's own text ("no further view change needed") — the guard landed in Task 1 and the view guard in Task 2, proven by Task 4's refusal test. Acceptable, documented as deviation 3 in 09-05-SUMMARY.

## Requirements Coverage

| REQ-ID | Requirement (REQUIREMENTS.md) | Delivered where | Evidence |
|--------|-------------------------------|-----------------|----------|
| SEARCH-03 | Chat answers include numbered [N] citations linked to real retrieved catalog records | 09-05 Tasks 1–2, 4 | Companion `AiService::search()` before `chatStream()` with corpus null + top_k 5 (D-20); `[{n,id,corpus,title,url,catalog_code}]` payload persisted to `ai_messages.citations`; catalog chips link to `/academic-papers/{id}`; Sources `<ol>` partial. Verified: `app/Livewire/ChatWidget.php` (companionCitations, citations at lines 154–173), both partials exist, `chat-widget-citations.blade.php`/`chat-widget-sources.blade.php` on disk. |
| SEARCH-04 | Assistant answers "I don't have enough information" instead of guessing when retrieval finds nothing | 09-01 Task 4 (Δ4); 09-05 Task 3 | `rag.py:125-126` (`answer`) and `rag.py:157-158` (`stream_events`) short-circuit `if not results:` with the canonical string, zero `create()` calls asserted by tests; widget renders it as a normal bubble with no Sources. |
| CHAT-01 | User can chat with the assistant through an in-app widget with streamed responses | 09-02 Tasks 1–5; 09-04 Tasks 1–7 | `AiService::chatStream()` (POST `/chat/stream`, retries 0) + `chatStreamEvents()` SSE generator over one hoisted `Response::resource()`; `ChatWidget` Livewire component streams chunks via `$this->stream($chunk, false, 'ans')`; mounted behind `@auth` in both `app.blade.php:280` and `admin.blade.php:205`; ChatWidgetTest 9 + AiServiceChatTest 7 green. |
| CHAT-02 | Chat history persists and remains viewable across sessions | 09-03 Tasks 1–5; 09-04 Tasks 3, 5 | `ai_conversations`/`ai_messages` migrations (cascade FKs, role enum, citations json, composite indexes), Conversation/Message models (makeTitle 120-char, touch-on-save ordering), factories, ConversationMessageTest 8 green; widget lazy-creates conversations, auth-scoped loads, list default view with persisted history re-rendered (never replayed to sidecar, D-19). |
| CHAT-04 | User can ask library policy questions and get answers grounded in the rulebook corpus | 09-01 Task 3 (Δ3); 09-05 Tasks 2, 4 | `corpus` validated to `catalog`/`policy`/absent (`main.py:124-125`), policy flows through `/chat/stream`; policy citations render as non-link chips with `(rulebook)` suffix (D-21/D-22); policy-flow test in ChatWidgetTest. |

## Test Evidence (run by verifier on 2026-08-14)

| Suite | Command | Result |
|-------|---------|--------|
| Laravel (full) | `php artisan test` | **552 passed, 3 skipped** (1549 assertions), 52.84s — matches 09-05-SUMMARY claim |
| Sidecar (full) | `uv run pytest` | **46 passed, 1 skipped** (19.26s) — 1 skipped = gated live smoke; matches 09-01/09-05-SUMMARY claim |
| Ruff | `uv run ruff check app/ tests/` | Clean per 09-01-SUMMARY (declared; suite green) |

Suite progression is internally consistent: 523 → 530 (+7 AiServiceChat) → 538 (+8 ConversationMessage) → 547 (+9 ChatWidget) → 552 (+5 citations).

## On-Disk Spot Checks (16/16 present)

- Sidecar: `app/main.py` contains `auth_failed` (line 74) with `invalid_request` remaining only on the 422 `_invalid` helper (line 55) — matches plan acceptance; corpus membership check (line 124); `app/rag.py` refusal branches (lines 125–126, 157–158); `tests/test_chat_stream_live.py` gated by `SIDECAR_LIVE_CHAT_TEST=1`.
- Laravel: `AiService.php` has `chatStream()` (line 47), `chatStreamEvents()` (line 74), shared `request()` helper (line 138), no `toStream` matches; `AiServiceProviderException.php` exists; both `2026_08_14_*` migrations exist; `Conversation.php` + `Message.php` exist; `ChatWidget.php` + `chat-widget.blade.php` exist; citation chips + Sources partials exist; `ai_messages.citations` persistence confirmed in the widget send path (lines 154–173); `@auth` mounts in both layouts; fixtures (`chat-stream.txt`, 43-byte 3-event SSE) and all three new test files exist.
- `app/Console/Commands/ExportAiCorpus.php` (`ai:export-corpus`) exists — the live-smoke prerequisite command.

## Caveats (all documented, non-blocking)

1. **Policy corpus is faker placeholder data** — sidecar `data/` holds only `golden_dataset.json`; catalog/policy corpus files are generated by `php artisan ai:export-corpus` and deleted by test runs (documented in 09-01/09-04/09-05 summaries and 09-VALIDATION.md LLM Isolation contract). Policy Q&A grounding is structurally wired (corpus validation, policy prompts, policy chips) but end-to-end quality depends on real corpus data.
2. **Catalog corpus is bibliographic-only** — citation payload carries title/catalog_code/url; no live availability (that is Phase 10, SEARCH-02).
3. **Live smoke deferred to operator** — `test_chat_stream_live.py` shipped (09-01 Task 5) but NOT executed: requires rotated OpenRouter key + `php artisan ai:export-corpus` first, per phase gate in 09-05-PLAN and 09-VALIDATION.md.
4. **Prototype branch deletion pending** — `prototype/chat-widget` @ `395244dc` (verdict markup source) is "NOT in working tree" and referenced by 09-04/09-05 plans; deletion awaits planning consumption.
5. **Tracking note (out of verifier scope)** — REQUIREMENTS.md rows and ROADMAP Phase 9 still show `Pending`; per instructions only 09-VERIFICATION.md was written, tracking updates remain with the phase gate.

## Verdict

**PASSED** — Phase 9 (RAG Chat & Policy Q&A) is complete: 26/26 tasks delivered with atomic commits, all 5 requirements (SEARCH-03, SEARCH-04, CHAT-01, CHAT-02, CHAT-04) demonstrably delivered and spot-verified on disk, full suites green in both repos (552/3 Laravel, 46/1 sidecar), no FAILED self-check markers, and all caveats documented and non-blocking. Remaining gate items (live smoke with rotated key + corpus export, requirement tracking flip) are operator actions, not defects.
