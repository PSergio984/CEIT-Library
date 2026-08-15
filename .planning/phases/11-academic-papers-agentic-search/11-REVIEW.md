---
phase: 11
status: clean
depth: standard
files_reviewed: 18
findings:
  critical: 0
  warning: 4
  info: 8
  total: 12
fixed: WR-1, WR-2, WR-3
resolved_date: 2026-08-15
---

# Phase 11 Review — Academic Papers & Agentic Search

## Fix Round (2026-08-15)

WR-1, WR-2, WR-3 fixed post-review (sidecar `b590493`, Laravel `4d4607c9`):
- **WR-1 fixed** — loop executes ALL parallel tool calls within the round cap (sliced to `MAX_TOOL_ROUNDS - rounds`), only executed calls enter messages (no unmatched `tool_call_id` 400s), assistant tool message sets `content: None` per OpenAI contract. Tests: `test_multiple_tool_calls_execute_all_with_no_unmatched_ids`, `test_parallel_calls_beyond_cap_drop_remainder_without_unmatched_ids`.
- **WR-2 fixed** — tool-result messages truncate doc text to 600 chars + `…` (`_truncate_docs_for_tool()`, reusing `MAX_DOC_CHARS` from the one-shot path); full docs still feed the final prompt. Test: `test_tool_result_messages_truncate_long_doc_text`.
- **WR-3 fixed** — activity frame text escaped at the stream render boundary (`e($frame['payload']['text'])`) in ChatWidget. Test: `it_escapes_activity_frame_text`.
- **WR-4 accepted** — author/adviser badges render inertly in Browse mode (UX-only; payload tests prove browse back-compat byte-identical). Not a defect.

Post-fix suites: sidecar 70 passed / 1 skipped (ruff clean), Laravel 599 passed / 3 skipped; eval gate: negative pass rate 1.0, top-1 0.9259, F1 0.6112.

## Two-Axis Review — Findings & Fix Round 2 (2026-08-15)

Standards axis: 0 hard violations, 6 judgement calls (worst: duplicated `chatStreamFrames()` generator).
Spec axis: 5 findings (worst: request `corpus` inert on the agentic path).

All Spec findings fixed (sidecar `d51256c`, Laravel `cc684789`), Standards resolved:
- **S-1 fixed** — 11-UI-SPEC.md copy table synced to the approved strings: corpus rows now `Searching policy documents…` / `Searching the catalog…` (matching `agent.py` exactly, U+2026 incl.); plan-approved copy locked.
- **S-2 fixed** — `ChatWidgetTest::it_renders_fail_closed_refusal_as_normal_bubble` now fakes/asserts the ADR 0006 verbatim short string (`I don't have enough information`); UI-SPEC refusal rows updated.
- **S-3 fixed** — `activity_line()` reordered: filters (author/adviser/year) and corpus copy checked BEFORE the `executed_rounds > 0` refinement fallback, so a filtered refinement keeps its per-filter line. Tests: `test_activity_and_citations_frame_ordering` (two author lines), `test_activity_copy_lines_for_corpus_and_year`.
- **S-4 fixed** — `runHybridSearch()` name-as-query: in the paper tab, an author/adviser selection with no usable topic becomes the query (ADR 0011 precedent) while the name filter still narrows; browse mode byte-identical, status force-exit unchanged. Test: `it_searches_by_author_or_adviser_alone_in_paper_tab`.
- **S-5 fixed** — request `corpus` threaded through: `main.py` → `stream_agentic_events(..., corpus=...)` → `effective_corpus = args.corpus or corpus` for `rrf_search` + activity copy; explicit tool corpus wins; absent request corpus = both (unchanged). Tests: `test_request_corpus_is_default_when_tool_omits_it`, `test_request_corpus_does_not_override_explicit_tool_corpus`.
- **Standards-1 fixed** — `availableAuthors()`/`availableAdvisers()` typed `: Collection`.
- **Standards-3 fixed** — `chunk_frame()` extracted to `rag.py`, shared by one-shot + agentic paths.
- **Standards-4 fixed** — `rag.CITATION_KEYS` / `AiService::CITATION_KEYS` shared literals per side; `citation_payload()` + `ChatWidget::validCitationsPayload()` consume them.
- **Standards-2 accepted** — `chatStreamFrames()` duplication deliberately byte-locked (plan 11-05); left as-is.

Re-review verdict: all 5 Spec findings FIXED, no new drift, no scope creep; Standards: no hard violations in the new commits (two trivial nits: duplicated `effective_corpus` expression, redundant `(string)` cast — both judgement calls, left).

Post-fix suites: sidecar 72 passed / 1 skipped (ruff clean), Laravel 600 passed / 3 skipped; eval gate: negative pass rate 1.0, top-1 0.9259, F1 0.6112.

## Two-Axis Review Round 2 — Fix Round 2 (2026-08-15)

Re-review found all 5 spec findings RESOLVED + 4 standards nits (judgement calls); all nits fixed (sidecar `5f71f0f`, Laravel `a7d987ad`):
- **nit 1 fixed** — `effective_corpus` single owner: `activity_line(args, executed_rounds, effective_corpus)` no longer re-derives `args.corpus or corpus`; the loop resolves once and passes it. Test: `test_activity_line_uses_the_effective_corpus_it_is_given`.
- **nit 2 fixed** — `citation_payload()` explicit key→value dict literal (was `zip(CITATION_KEYS, positional_tuple)`); `_citation_values` deleted; shape locked by `test_citation_payload_keys_match_contract_and_values_are_attributed`; `rag.CITATION_KEYS` + `AiService::CITATION_KEYS` mirrors unchanged.
- **nit 3 fixed** — browse mode ships verbatim `$this->search` (byte-identical pre-S-4 payload); trim/name-as-query is paper-tab-only. Test: `it_sends_verbatim_search_in_browse_mode`.
- **nit 4 fixed** — UI-SPEC documents author-over-adviser query precedence + gate-exit when neither set, matching the code.

Round-2 verdict: both axes clean — 0 hard violations, 0 spec drift, 0 scope creep. One mild judgement call: the six citation key names now exist twice (rag.py literal + dict literal) — explicit-over-positional trade-off, test-enforced.

Post-fix suites: sidecar 74 passed / 1 skipped (ruff clean), Laravel 601 passed / 3 skipped; eval gate unchanged: negative pass rate 1.0, top-1 0.9259, F1 0.6112.

Reviewed 2026-08-15. Scope: paper corpus doc shape (CorpusExporter), author/adviser
filters (sidecar `passes()` + endpoint acceptance), agentic loop (sidecar `agent.py`,
routed `/chat/stream`), paper tab (AcademicPaperIndex + filters component + blade),
agentic chat frames (AiService::chatStreamFrames + ChatWidget activity slot /
citations binding), and their test suites (Laravel + sidecar).

## Verified clean (no findings)

- **Loop cap:** `rounds >= MAX_TOOL_ROUNDS` is checked at the top of every
  iteration; `rounds` increments only after an executed search; malformed-args
  iterations are bounded by `malformed_streak >= 2`. Worst case is 3 executed
  searches + ≤3 corrective turns — rounds can never exceed 3 (`app/agent.py:268-269`,
  covered by `test_loop_caps_at_three_rounds*`).
- **Docs growth:** `merge_dedup` appends only new ids; each round returns ≤ top_k
  (≤50) docs → accumulated set ≤ 150. Bounded (`app/agent.py:299`).
- **Closed-schema posture:** `ToolArgs`/`ToolFilterArgs` use `extra="forbid"` at
  both levels; unknown top-level endpoint keys still 422 (`_reject_unknown`);
  `/chat/stream` mode/corpus/top_k validation unchanged on the agentic route.
  No path where unvalidated model output reaches `rrf_search`.
- **SSE framing:** all payloads pass through `json.dumps` (newlines escaped);
  activity lines are static copy; the refusal line is a bare data frame the
  Laravel parser yields as a plain chunk (matches the pre-existing one-shot path).
- **PII/logging:** `logger.error(repr(exc))` and the sanitized Laravel failure log
  never contain queries, tokens, or bodies; exporter test asserts no inventory/user
  PII in the corpus.
- **Browse back-compat:** author/adviser keys are appended only when
  `paperTabActive`; capture test locks the five-key browse payload
  (`it_does_not_send_author_adviser_keys_outside_paper_tab`).
- **Frame ordering:** activity → chunks → citations → `[DONE]`; single `event: error`
  on mid-loop failure; citations frame carries the six ADR 0006 keys and is
  shape-gated client-side with the `companionCitations()` fallback (T-11-19).
- **Exporter:** single-title doc shape locked (title-doubling dropped), segments
  composed once, `sanitize()` caps applied throughout.

---

### WR-1 — Multi-tool-call responses break the round (unmatched tool_call_id)

- **Severity:** warning
- **File:** `app/agent.py:270-271` (call selection), `app/agent.py:156-168` (`_assistant_tool_message`)
- **Description:** When the model emits TWO+ tool calls in one response (allowed by
  the OpenAI contract; `SEARCH_TOOL` does not set `parallel_tool_calls: false`),
  `call = tool_calls[0]` executes only the first, but `_assistant_tool_message(msg,
  tool_calls)` appends ALL calls to `messages`. The second `tool_call_id` never
  receives a `role: "tool"` response, and most OpenAI-compatible providers 400 the
  next request for the unmatched id — the outer `except` then turns the whole turn
  into `provider_error` ("AI provider temporarily unavailable") with zero answer.
  Related: the message content is `msg.content or ""` — a non-null string alongside
  `tool_calls` is rejected by stricter providers (contract requires `content: null`
  on assistant tool-call messages). Neither case is covered by the test suite (all
  fakes emit exactly one call).
- **Suggested fix:** either execute every call (each bounded by the same
  validation + cap), or strip the unexecuted calls from the appended message;
  set `content` to `None` when `tool_calls` is present. Add a fake emitting two
  parallel tool calls asserting the round completes.

### WR-2 — Full document text shipped into tool-result messages (context blow-up)

- **Severity:** warning
- **File:** `app/agent.py:300-306`
- **Description:** `json.dumps(results)` puts the FULL result objects — including
  the `text` field, up to 20 000 chars for policy regulations — into the model
  context on every round. Worst case (3 rounds × top_k 50 × 20 KB) approaches
  3 MB of context per turn; even typical policy queries multiply provider cost and
  risk context-limit failures. The one-shot path truncates via
  `build_context`/`MAX_DOC_CHARS = 600`; the loop's tool message has no equivalent
  bound.
- **Suggested fix:** truncate `text` per doc in the tool message (reuse
  `MAX_DOC_CHARS` truncation or a smaller cap) and/or include only `id`-keyed
  metadata; the final prompt already gets the truncated context, so grounding is
  unaffected.

### WR-3 — Activity frame text streamed as unescaped HTML

- **Severity:** warning
- **File:** `app/Livewire/ChatWidget.php:176-179`
- **Description:** `$frame['payload']['text']` is concatenated into an HTML string
  passed to `$this->stream(...)` (inserted via `insertAdjacentHTML`-style
  streaming) without `e()` escaping. Safe today only because the sidecar emits
  static copy (T-11-22's "rendered as plain text" is true by producer trust, not
  by construction); a future sidecar change or a compromised/regressed frame could
  inject markup into every student's chat DOM. The `ans` chunk slot has the same
  inherited pattern from Phase 9.
- **Suggested fix:** wrap the text in `e()` (`'<span>'.e($frame['payload']['text'] ?? '').'</span>'`).

### WR-4 — Stale author/adviser filters linger inertly in Browse mode

- **Severity:** warning
- **File:** `app/Livewire/Pages/Student/AcademicPaperIndex.php:368-371`; `resources/views/components/academic-paper-filters.blade.php:171-178`
- **Description:** switching to the Browse tab hides the author/adviser selects but
  keeps the values. The Active Filters badges ("Author: X", "Adviser: X") still
  render in Browse mode while the values are inert: the SQL path has no
  author/adviser `when()` clauses and `runHybridSearch()` excludes the keys outside
  the paper tab — the page claims active filters that are not applied, and the
  current `hybridResults` remain author-filtered until the next search trigger.
- **Suggested fix:** reset `authorFilter`/`adviserFilter` when `paperTabActive`
  flips to `false` (via an `updatedPaperTabActive()` hook), or gate the badges on
  `paperTabActive` so inert filters are never advertised.

---

### IN-1 — Single-sided year ranges render "None" in the activity line

- **Severity:** info
- **File:** `app/agent.py:138-139`
- **Description:** `f"Searching papers from {year_from}–{year_to}…"` renders
  "Searching papers from 2015–None…" when the model emits only `year_from`
  (schema-valid). Also, minor copy drift vs UI-SPEC (en dash vs hyphen, "Searching
  the catalog…" vs spec "Looking in the catalog."). Cosmetic.
- **Suggested fix:** handle single-sided ranges ("Searching papers from 2015…" /
  "…to 2020…"); align the copy table with UI-SPEC or update UI-SPEC.

### IN-2 — `int()` casts in `passes()` can 500 the /search endpoint on junk filters

- **Severity:** info
- **File:** `app/search.py:118-123`
- **Description:** `int(f["year_from"])` / `int(f["publication_year"])` raise
  `ValueError` → unhandled 500 on a direct `/search` POST with non-numeric values
  (pre-existing Phase 8 pattern; the new author/adviser clauses are correctly
  `str()`-guarded and tested with junk). The agentic path is safe (pydantic int).
- **Suggested fix:** int-guard the year clauses for parity (junk → clean empty
  result, matching `test_filter_author_non_string_junk_does_not_raise`).

### IN-3 — Malformed activity/citations frame silently skipped

- **Severity:** info
- **File:** `app/Services/AiService.php:186-195`
- **Description:** if the `data:` line after `event: activity`/`event: citations`
  is missing or malformed, the parser silently `continue`s — no error, no
  fallback signal (the widget's `validCitationsPayload` catches bad payloads, so
  the citations case is safe; the activity case just loses a line). Acceptable
  robustness, but the silent skip hides wire-level drift.
- **Suggested fix:** at minimum log a warning when the expected data line is absent.

### IN-4 — Refusal bubble can render companion citation chips

- **Severity:** info
- **File:** `app/Livewire/ChatWidget.php:155, 204`
- **Description:** the fail-closed refusal ("I don't have enough information")
  streams as a plain chunk, while `companionCitations()` (a separate /search on
  the raw question) can still return results — chips/sources then render beneath
  a refusal that claims no information. The sidecar is the refusal authority
  (D-23), but the widget could suppress chips when the answer is the canonical
  refusal copy.
- **Suggested fix:** detect the refusal payload (or empty agentic docs) and keep
  `citations = null` for render + persistence.

### IN-5 — `availableAdvisers` does not filter null names

- **Severity:** info
- **File:** `app/Livewire/Pages/Student/AcademicPaperIndex.php:248-257`
- **Description:** unlike `availableDepartments`/`availableAuthors`
  (`->filter()`), `availableAdvisers` keeps null names; a null adviser row yields a
  "null"-labeled option in the select.
- **Suggested fix:** add `->filter()` before `->unique()`.

### IN-6 — Five copy-pasted button surfaces in the paper index blade

- **Severity:** info
- **File:** `resources/views/livewire/pages/student/academic-paper-index.blade.php`
- **Description:** the Phase 10 concern stands and grew: the "View Details +
  Similar / Can't Borrow" block is duplicated across rec-mobile, rec-desktop,
  hybrid-mobile, hybrid-desktop, and the SQL table scope (5 surfaces, ~150 lines).
- **Suggested fix:** extract a shared card/action partial parameterized by the
  paper + wire keys.

### IN-7 — Clearing the status filter does not re-enter hybrid mode

- **Severity:** info
- **File:** `app/Livewire/Pages/Student/AcademicPaperIndex.php:272-280`
- **Description:** `updatedStatusFilter()` force-exits hybrid mode (correct,
  D-18), but when the filter is cleared to `''` hybrid mode stays off until the
  search box is retriggered — now visible in the paper tab where the user expects
  AI-ranked results back. Inherited Phase 10 pattern, unchanged by this phase.
- **Suggested fix:** when `statusFilter` becomes `''`, re-run `runHybridSearch()`.

### IN-8 — Engine errors mislabeled as provider errors

- **Severity:** info
- **File:** `app/agent.py:318-324`
- **Description:** the broad `except Exception` wraps `rrf_search` too — an index
  failure surfaces as "AI provider temporarily unavailable", which is misleading
  for operators; the taxonomy is correct for clients, but consider distinguishing
  engine errors in the logged repr.
- **Suggested fix:** separate try/except around the search call with its own error
  code (or at least a distinct log message).

---

## Summary

No critical findings. The security posture of the agentic loop is solid: the 3-round
cap is enforced exactly, tool args are validated (`extra="forbid"`) before any
execution, the fail-closed refusal is zero-LLM, SSE framing is injection-safe, and
the closed-schema 422 posture is preserved end to end. The four warnings are all
robustness/UX gaps rather than exploitable flaws: multi-call handling (WR-1), tool
message size (WR-2), unescaped streamed HTML (WR-3), and inert filter badges in
Browse mode (WR-4). Test coverage is strong (cap, ordering, taxonomy, back-compat,
snapshot, malformed frames); the multi-tool-call and junk-year-filter cases are the
main coverage gaps.
