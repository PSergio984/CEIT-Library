---
status: issues
severity: warning
---

# Phase 9 — RAG Chat & Policy Q&A: Code Review

**Reviewer:** gsd-code-reviewer
**Date:** 2026-08-14
**Scope:** Sidecar `app/rag.py`, `app/main.py` + tests; Laravel `AiService`, `AiServiceProviderException`, Conversation/Message models + migrations + factories, `ChatWidget` + blades + layouts, Feature tests, SSE fixture.
**Method:** Per-file read against locked decisions D-01..D-35 (09-CONTEXT.md), ADRs 0002/0004/0005/0006/0008/0009, and 09-VERIFICATION.md claims.

## Verified against the contract (passed checks)

- **Refusal (D-23/D-24):** `rag.py:125-126,157-159` short-circuit empty retrieval with the verbatim string, zero `create()` calls — asserted by `test_rag.py:179-197` and `test_chat_stream.py:114-123` (`calls == []`). Widget renders it as a normal bubble with `citations = null` (ChatWidgetTest `it_renders_refusal_without_sources`).
- **SSE framing (ADR 0002):** `data: <chunk>` + `data: [DONE]`; `event: error` carries JSON `{code, message}` with a safe generic message, raw exception never leaked (`rag.py:164-170`, asserted `test_chat_stream.py:224-235`).
- **Auth/corpus validation (D-05/D-06/D-07):** `main.py:69-78` 401 `auth_failed` via `compare_digest`; `main.py:123-131` corpus 422 `invalid_request` + top_k 1-50. Asserted in `test_api.py:56-60` and `test_chat_stream.py:164-183`.
- **AiService (D-10/D-11/D-12):** `chatStream()` payload shape, retries 0, `throwUnlessOk` before streaming, `Response::resource()` (not `toStream`), `$this->stream($chunk, false, 'ans')` positional — all match the amended ADR 0004/0002. 7 tests cover payload, token header, SSE order, error-event, 401, connection failure, no-retry.
- **Binding order (D-20):** widget companion `search($q, [], null, 5)` vs `/chat/stream` (`top_k=5`, no corpus, k=60 both) — asserted by ChatWidgetTest `it_binds_companion_search_to_chat_parameters`; RRF is a pure function of index state so ordering is deterministic across the two calls. Payload shape `{n,id,corpus,title,url,catalog_code}` asserted verbatim in `it_persists_citation_payload`.
- **Chips/Sources (D-21/D-22):** catalog → link chip + `<ol>` Sources with catalog_code; policy → non-link chip + `(rulebook)` — asserted with `assertSeeHtml`/`assertDontSeeHtml`.
- **Lazy creation/titles (D-18/D-32/D-33):** `Conversation::makeTitle` 120-char mb-truncation + fallback, ~40-char list trim + relative time — all asserted.
- **Retry replaces failed turn (D-29):** unset failed bubble + `persistUser:false`; test asserts exactly 2 rows after retry.
- **IDOR on read (D-15):** `openConversation` is auth-scoped; cross-user test green. **But see W-4 for the write path.**
- **XSS:** all user/LLM/citation text rendered via `{{ }}` (escaped); streaming path goes through `wire:stream` textContent. No `{!! !!}` in any chat blade.
- **Tests genuinely assert locked behaviors** (refusal-no-LLM-call, binding order, IDOR-read, retry row count); live smoke is env-gated with a rotated-key prerequisite.

## Findings (5 warnings, 9 info)

### WARNING

- **W-1 — SSE corruption when a delta contains a newline.** `ceit-ai-sidecar/app/rag.py:163` emits `data: {delta}\n\n` with no sanitization; a provider delta containing `\n` (LLM answers routinely include paragraph breaks, and these deltas are common) splits the event across two lines. `CEIT-Library/app/Services/AiService.php:87-94` parses line-by-line and silently **drops** the continuation line (no `data: ` prefix → falls through, no accumulation), corrupting the persisted answer text. The fixture and all parser tests use newline-free chunks, so the path is untested. Fix: sanitize deltas sidecar-side (`delta.replace("\n", " ")` or `\n`→escape), and/or accumulate `data:` continuation lines per the SSE spec in the parser — minimum is the sidecar, since ADR 0002 framing is its contract.
- **W-2 — EOF-before-`[DONE]` treated as success: silently truncated answers persisted.** `AiService.php:78-107` ends the generator cleanly when `fgets` returns false; `ChatWidget.php:160-174` then persists `$accumulated` (partial text) + citations as a successful assistant message — no failed flag, no Retry. If the sidecar hard-crashes or the connection RSTs mid-stream, a Guzzle `ConnectionException` thrown during iteration is **not** in the widget's typed catch (`ChatWidget.php:177`) → unhandled Livewire 500. Fix: track whether `[DONE]` was seen; clean EOF without it → throw `AiServiceProviderException`; add connection-error types to the widget catch (or wrap iteration in a mapping try/catch).
- **W-3 — Incremental streaming and the typing-dots indicator never reach the client.** `chat-widget.blade.php:43-47,62-70` gates `<div wire:stream="ans">` and the dots on `$streaming && $loop->last`, but `$streaming` is only ever true *server-side during the action* — the final render (after `finally` resets it) and every prior client snapshot carry `streaming=false`, so the stream target element never exists in the DOM. Livewire's `supportStreaming` buffers stream events until the target exists, then drops them → chunks never render; the full answer pops in at once at action end (final render shows `messages[last].content`), and D-28's dots never show. The "streamed responses" headline (CHAT-01) is therefore not actually streamed to the user. This also leaves the send button enabled for the whole stream (feeds W-5). Fix: render the `wire:stream="ans"` target unconditionally in the last assistant bubble (e.g., always include it while `$streaming || $last->failed`), or keep a permanently-present stream container.
- **W-4 — Cross-user **write** via client-hydratable `activeConversationId`.** `ChatWidget.php:49-67` correctly auth-scopes reads (D-15), but Livewire re-hydrates **all public properties** from the wire payload. An authenticated user can submit `send()` with `activeConversationId` set to another user's conversation id: `streamQuestion` (`ChatWidget.php:127-142`) skips the lazy-create branch (id non-null), never re-validates ownership, and `Message::create` inserts the attacker's user+assistant rows into the victim's conversation (integrity pollution, plus `touch()` reorders the victim's list). No read disclosure, but it defeats the D-15 per-user boundary. Fix: re-verify ownership inside `streamQuestion` (`Conversation::where('user_id', auth()->id())->whereKey($id)->exists()`) before persisting, or keep `activeConversationId` out of client-controlled state.
- **W-5 — Double-send race bypasses the `$this->streaming` guard.** `ChatWidget.php:77-88` guards on `$this->streaming`, but Livewire queues interactions during an in-flight request, each replaying its own stale snapshot — and since `streaming=true` never reaches a client render (W-3), the send button/textarea are never disabled mid-stream (blade `@if ($streaming)` never activates client-side). A Send click during a 20-60s stream is queued, replays `send()` with `streaming=false`, and produces a duplicate user message, a **second** lazy-created conversation (payload's `activeConversationId` is stale/null), and a duplicate LLM call. Fix: `wire:loading.attr="disabled"` on the send button/textarea plus a server-side idempotency marker (e.g., `lastSentAt` timestamp guard).

### INFO

- **I-1 — Dead parameters in the D-09 refactor.** `AiService::request()` (`AiService.php:138-151`) accepts `$method`, `$path`, `$body` but never uses them (callers at 56, 118 pass them). Trim the signature or drop the arguments.
- **I-2 — Malformed error event silently swallowed.** `AiService.php:99-106`: if `event: error` is not immediately followed by a `data:` line, the parser skips both and the stream continues to `[DONE]` → empty answer persisted as success. Add an else-throw so an unexpected frame shape fails loudly (companion to W-2).
- **I-3 — Hardcoded `provider_error` code in failed-bubble state.** `ChatWidget.php:180-183` labels every failure `provider_error`, including `AiServiceAuthException` (401) and Unavailable (500). Harmless today (code isn't rendered) but misleading for any future UI/logging use; set from the exception type.
- **I-4 — Auth failure message leaks internal config detail to end users.** `AiService.php:157` — "invalid SIDECAR_TOKEN." is shown verbatim in the amber banner (D-29). Use a generic message ("The AI assistant is temporarily unavailable."); details belong in `logFailure` only.
- **I-5 — Sequential companion search delays first token.** `ChatWidget.php:154-158` runs `search()` (timeout 10s × 2 retries) *before* opening the chat stream; a slow/degrading sidecar delays the first streamed byte by up to ~30s worst case (and W-3 hides the wait entirely). Acceptable under the plan's discretion note, but consider firing the companion call in parallel or in a deferred/after state.
- **I-6 — No auto-scroll.** D-31 requires entries "scrolled down"; the drawer (`chat-widget.blade.php:24`) has no scroll-to-bottom behavior on open/send, so new content can sit below the fold in long conversations.
- **I-7 — Pre-existing unguarded int casts in `/search`.** `main.py:94-95`: `int(payload.get("limit", 10))` / `int(payload.get("k", 60))` raise unhandled `ValueError` → 500 on non-numeric input (unlike `/chat/stream` which validates). Outside this phase's diff; worth aligning with the chat endpoint's validation when next touched.
- **I-8 — PII at rest.** `ai_messages.content` stores verbatim user queries and full answers; citations store titles/codes. Matches locked D-17 (hard delete, no TTL; Phase 14 sanitization pending) — flagged for awareness, not a defect.
- **I-9 — Test-gap note.** Parser tests cover ordering/error-event/DONE but not W-1's newline deltas or W-2's EOF-without-DONE; a `SupportStreaming`-level test (or a browser smoke via `webapp-testing`) would have caught W-3. Existing suites otherwise assert the locked behaviors well, and the gated live smoke (`test_chat_stream_live.py`, `SIDECAR_LIVE_CHAT_TEST=1`) is appropriately excluded from CI.

## Fix round (2026-08-14, after review)

All 5 warnings and 3 INFOs (I-1, I-4, I-6) fixed in one pass; I-2 folded into the W-2 parser restructure; I-3 applied (honest error codes); I-5/I-7/I-8/I-9 documented as deferred/awareness:

- **W-1 (SSE newline corruption)** — sidecar now JSON-encodes chunk payloads (`{"c": "<delta>"}`, ensure_ascii=False); Laravel parser decodes; newline-preservation test added. Sidecar suite 46 passed / 1 skipped.
- **W-2 (silent truncation)** — parser tracks `[DONE]`; clean EOF without it throws `AiServiceProviderException`; malformed `event: error` also throws; widget catches `ConnectionException`.
- **W-3 (streaming never reached the client)** — replaced the conditionally-rendered `wire:stream` target with a permanently-mounted stream slot (0-height when idle; Livewire 4 drops chunks when the target is absent — verified in `livewire.js` `if (!targetEl) return`); typing dots now stream as the first chunk.
- **W-4 (cross-user write)** — `streamQuestion` re-verifies ownership of the client-hydratable `activeConversationId` before persisting, else falls back to a fresh conversation; cross-user write test added.
- **W-5 (double-send race)** — `wire:loading.attr="disabled"` on the send button + textarea (in-flight request disables them client-side).
- **I-1** — `request()` signature trimmed of dead `$method/$path/$body` params.
- **I-3** — error codes now map from the exception type (`auth_failed` / `unavailable` / `provider_error`).
- **I-4** — 401 exception message made generic (details stay in `logFailure` only).
- **I-6** — auto-scroll via MutationObserver on the drawer message list.

**Re-test:** full Laravel suite 556 passed / 3 skipped (552 + 4 new tests); sidecar 46 passed / 1 skipped; Pint clean. Commits: sidecar `fix: JSON-encode SSE chunk payloads…`; Laravel `fix(phase-9): apply code-review findings W-1..W-5, I-1, I-3, I-4, I-6`.

## Verdict

The backend contract (sidecar endpoint, error taxonomy, AiService, persistence schema, auth-scoped reads) is implemented faithfully with strong tests; **no CRITICAL findings**. The material issues are concentrated in the widget's streaming path: W-3 (streaming never displayed — CHAT-01's headline behavior), W-2 (silent truncation on mid-stream loss), W-5 (double-send), W-1 (newline corruption), and W-4 (ownership re-validation missing on the write path). All five have small, localized fixes; W-3/W-5 are worth verifying in a real browser after fixing, ideally through the deferred live smoke.
