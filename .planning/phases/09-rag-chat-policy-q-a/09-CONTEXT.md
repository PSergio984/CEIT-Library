# Phase 9: RAG Chat & Policy Q&A - Context

**Gathered:** 2026-08-14
**Status:** Ready for planning
**Source:** Wayfinder map decisions (PSergio984/CEIT-Library issue #12, closed) — ADR 0001-0009 under `docs/adr/`, all human-approved and locked

<domain>
## Phase Boundary

Phase 9 delivers SEARCH-03, SEARCH-04, CHAT-01, CHAT-02, CHAT-04 on top of the Phase 8 hybrid-search foundation: a streamed chat endpoint in the FastAPI sidecar (`ceit-ai-sidecar`, port 8310), the Laravel `AiService` client extension, the `ai_conversations`/`ai_messages` persistence schema, and the in-app chat widget (floating launcher + drawer) with conversation list, grounded answers with numbered citations, and strict refusal behavior.

</domain>

<decisions>
## Implementation Decisions

### Endpoint contract (ADR 0004)
- **D-01**: `POST /chat/stream` is the only chat endpoint; one-shot `RagService.answer()` stays an internal test seam, never exposed over HTTP.
- **D-02**: Request schema locked: `{"query": str (required), "mode": "citations"|"question"|"rag" (default citations), "corpus": "catalog"|"policy" (optional; absent = both), "top_k": int 1-50 (default 5)}`. No temperature/model fields. No history field (single-turn).
- **D-03**: Response: SSE — `data: <chunk>` lines, `data: [DONE]` terminator (ADR 0002 framing).
- **D-04**: Mid-stream failure emits `event: error` with JSON `{"code": "provider_error", "message": <safe generic>}` — never raw exception; details in server logs.
- **D-05**: Global `X-Sidecar-Token` middleware gates `/chat/stream` like every endpoint; 401 error code is `auth_failed` (not `invalid_request`).
- **D-06**: Error taxonomy: 401 `auth_failed` / 422 `invalid_request` / stream `provider_error`. Fine-grained rate-limit mapping deferred to Phase 14.
- **D-07**: Server-side validation: `corpus` must be `catalog`, `policy`, or absent (else 422).
- **D-08**: No user identity ever reaches the sidecar; per-user boundary is Laravel-only.

### Laravel AiService (ADR 0004)
- **D-09**: Refactor request-building inside `send()` into a shared private helper.
- **D-10**: New `chatStream(string $query, ?string $mode = 'citations', ?string $corpus = null, int $topK = 5)` — POSTs `/chat/stream`, runs `throwUnlessOk` first, returns the streamed `Response`.
- **D-11**: Livewire component consumes via `Http::toStream()` → `$this->stream(content: $chunk, replace: false)` per chunk (NOTE: actual Livewire 4 signature is positional: `stream($content, $replace = false, $name = null)`).
- **D-12**: One new typed exception `AiServiceProviderException` for `event: error`; `AiServiceAuthException`/`AiServiceUnavailableException` unchanged (422 stays under the Unavailable catch-all).

### History schema (ADR 0005)
- **D-13**: `ai_conversations` — id, user_id (FK→users, cascade, NOT NULL), title (string 120, nullable), timestamps; index (user_id, updated_at).
- **D-14**: `ai_messages` — id, conversation_id (FK→ai_conversations, cascade), role (enum user/assistant), content (text), citations (JSON nullable — the `[{n, id, corpus, title, url, catalog_code}]` list), timestamps; index (conversation_id, id).
- **D-15**: Models `Conversation` + `Message` mapped to the `ai_` tables; single owner, auth-scoped loads; no guest conversations.
- **D-16**: Flat ordering, no threading; messages by (conversation_id, id) asc; conversation list by `updated_at` desc via `touch()`.
- **D-17**: Hard delete + cascade; no soft deletes, no retention TTL (Phase 14 sanitization adds its own migration).
- **D-18**: Auto-title at creation from first user message, truncated 120 chars; fallback "New conversation"; no rename UI.
- **D-19**: History loads for viewability only — never replayed to the model (ADR 0004 single-turn stands).

### Citations & grounding (ADR 0006)
- **D-20**: Citation payload built from a companion `AiService::search()` call with the same query/corpus/top_k (deterministic rrf) → `[{n, id, corpus, title, url, catalog_code}]`, persisted to `ai_messages.citations`.
- **D-21**: Catalog citations render as LINK chips → `/academic-papers/{id}` (title + catalog_code); policy citations render as NON-LINK chips (header title — no per-rule page in Phase 9).
- **D-22**: Full numbered source list renders under the answer (the numbered set the model worked from — no [N]-parsing of streamed text).
- **D-23**: Programmatic refusal ONLY on empty retrieval — single chunk `I don't have enough information` + `[DONE]`, no LLM call; no score threshold (BM25 scores incomparable).
- **D-24**: Refusal string locked verbatim: `I don't have enough information`; one grounding rule for both corpora.

### Guard (ADR 0007)
- **D-25**: Widget mounts behind existing `['auth', 'verified']` group; `CheckAccountStatus` handles suspended accounts; no new middleware.
- **D-26**: No role gating (CHAT-03 is Phase 12); no blocked-state UI; no user identifier sent to the sidecar.

### Widget shape (ADR 0008)
- **D-27**: Floating launcher FAB (circular primary, bottom-right) on every authenticated page; opens a right drawer (`w-full sm:w-96`).
- **D-28**: Bubble layout — user right (primary), assistant left (soft); answers stream into the bubble with a typing-dots indicator.
- **D-29**: Citation chips + Sources list under assistant answers (D-21/D-22); refusal renders in a normal bubble; failure = inline amber banner in the failed bubble with Retry that replaces the failed turn.

### Conversation list (ADR 0009)
- **D-30**: Drawer has two views (list ⇄ chat); list is the default view on open.
- **D-31**: Entry loads that conversation's Messages (asc, bubbles, citations re-rendered from JSON, scrolled down); drawer bound to it until New.
- **D-32**: New switches to empty chat view; `ai_conversations` row created lazily on first message (auto-title); list sorts by `updated_at` desc.
- **D-33**: Entries = title truncated ~40 chars (fallback "New conversation") + relative time; no delete/rename.

### Provider & prompts (ADR 0001, 0003)
- **D-34**: OpenRouter via openai SDK; default `meta-llama/llama-3.3-70b-instruct`; alternates deepseek-chat-v3.1 / gpt-5.4-mini; Groq one-line fallback; model config-only server-side (`.env` — `LLM_BASE_URL/LLM_API_KEY/LLM_MODEL/LLM_MAX_TOKENS`).
- **D-35**: Prompt modes ported into sidecar `app/rag.py`: rag + citations + question, domain-parameterized for the library; summarize NOT ported (CHAT-07 deferred).

### the agent's Discretion
- Livewire component/page class names and file layout for the widget (widget is a component, not a page).
- Migration filenames and the exact migration body (column order, index names) for ai_conversations/ai_messages.
- Exact `AiService::chatStream()` internals (stream-consumer helper placement, chunk emission).
- Drawer view-switch state shape (list/chat mode, selected conversation id) and Alpine/Mary UI usage.
- Route names, blade partial organization, and CSS approach for the widget (DaisyUI/Mary UI classes).
- How the companion `/search` call is wired into the chat flow (parallel vs sequential; the citation payload must bind to the same retrieval order).
</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Locked decisions (read all)
- `docs/adr/0001-llm-provider-openrouter.md` — provider, model, config keys
- `docs/adr/0002-chat-streaming-livewire-stream.md` — transport, SSE framing
- `docs/adr/0003-rag-prompt-mode-selection.md` — ported prompt modes
- `docs/adr/0004-sidecar-chat-endpoint-contract.md` — endpoint contract, taxonomy, AiService
- `docs/adr/0005-conversation-history-schema.md` — tables, ownership, ordering, deletion, titles
- `docs/adr/0006-citation-and-grounding-rules.md` — citation payload, rendering, refusal
- `docs/adr/0007-minimal-per-user-chat-guard.md` — auth gate, boundary
- `docs/adr/0008-chat-widget-shape.md` — widget verdict (variant A)
- `docs/adr/0009-conversation-list-ui-flow.md` — drawer view-switch, lazy creation

### Wayfinder research (sidecar + RAG grounding)
- `.planning/research/wayfinder/llm-provider.md` — provider comparison
- `.planning/research/wayfinder/rag-prompts.md` — prompt texts verbatim
- `.planning/research/wayfinder/streaming-transport.md` — transport comparison

### Sidecar implementation (source of truth for what exists)
- `C:\Users\admin\Herd\ceit-ai-sidecar\app\main.py` — `/chat/stream` endpoint (validation, envelope), `/search`, `/index/rebuild`, `/health`, token middleware
- `C:\Users\admin\Herd\ceit-ai-sidecar\app\rag.py` — RagService, PROMPTS, build_context, stream_events (NOTE: current error event sends bare exception type name — ADR 0004 requires the JSON `{code, message}` shape; current 401 code is `invalid_request` — ADR 0004 requires `auth_failed`; corpus value validation missing — ADR 0004 D-07)
- `C:\Users\admin\Herd\ceit-ai-sidecar\app\search.py` — `rrf_search(..., include_text=True)` (document text reaches the LLM)
- `C:\Users\admin\Herd\ceit-ai-sidecar\app\config.py` — settings (LLM_*, sidecar_token, cache_dir)

### Laravel app (existing seams)
- `app/Services/AiService.php` — send() gateway, typed exceptions, search/rebuildIndex/health
- `app/Exceptions/AiServiceAuthException.php`, `AiServiceUnavailableException.php` — existing typed exceptions
- `app/Services/CorpusExporter.php` — corpus doc shapes (`paper-{id}` / `policy-h{h}-r{r}` ids, url for catalog, catalog_code; NOTE R10 latent bug: rule_regulations has no `order` column)
- `app/Http/Middleware/CheckAccountStatus.php` — suspended-account gate
- `routes/web.php` — route conventions, `['auth', 'verified']` groups, `academic-paper.show`
- `CONTEXT.md` — glossary (Assistant, Conversation, Message, Citation, Grounding, Policy/Catalog question, Sidecar)

### Requirements
- `.planning/REQUIREMENTS.md` — SEARCH-03, SEARCH-04, CHAT-01, CHAT-02, CHAT-04
</canonical_refs>

<specifics>
## Specific Ideas

- Live smoke test findings (ticket "Provision the chat provider key and tokens"): streaming + grounded refusal verified against real OpenRouter; policy corpus is faker placeholder content (real rulebook data pending — known caveat, do not treat as a bug); catalog corpus is **bibliographic-only** (AcademicPaper has no abstract/content field — catalog chat grounds on title/authors/dept/year/code only).
- Sidecar server runs on `127.0.0.1:8310`; tests delete corpus files — re-run `php artisan ai:export-corpus` before live sessions.
- `SIDECAR_TOKEN=smoke-test-token` placeholder matched in both `.env` files — operator sets a real token before production.
- OpenRouter API key must be ROTATED (was pasted in chat); it lives only in `ceit-ai-sidecar\.env` (gitignored).
- Prototype reference (verdict source): branch `prototype/chat-widget` at commit `395244dc` — throwaway; delete after planning consumes it.
- Full test suite baseline: 523 passed / 3 skipped.
</specifics>

<deferred>
## Deferred Ideas

- summarize prompt mode (CHAT-07) — future milestone.
- Conversation context-window management / multi-turn model memory (reserved `history` field in ADR 0004 — out of Phase 9).
- Conversation rename/delete UI — no Phase 9 requirement.
- Per-rule show pages for policy citation links — no Phase 9 requirement.
- Role-aware access/answer depth (CHAT-03), agentic multi-step search (CHAT-05), librarian operational answers (CHAT-06) — Phase 12.
- Evaluation stack (EVAL-01..04) — Phase 13.
- Metrics, rate limits/cost guards, PII sanitization, docker deploy (OPS-01..04) — Phase 14.
- Milestone-wide: open web search, LLM-executed transactions, multi-agent orchestration, follow-up suggestion chips, memory-RAG.
</deferred>

---

*Phase: 09-rag-chat-policy-q-a*
*Context gathered: 2026-08-14 via wayfinder map decisions (ADRs 0001-0009)*
