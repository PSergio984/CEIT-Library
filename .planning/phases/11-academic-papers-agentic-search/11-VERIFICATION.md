---
phase: 11
status: passed
must_haves: 15/15
created: 2026-08-15
---

# Phase 11 Verification — Academic Papers & Agentic Search

**Verifier stance:** adversarial — SUMMARY claims treated as unproven until code behavior confirmed. All claims below were checked against source, not summaries. Full suites re-run; eval gate re-run; commit history checked.

## Goal (ROADMAP §Phase 11)

Add the academic paper corpus (AcademicPaper + authors + advisers) with paper-specific chunking and topic/author/year/adviser search, and enable agentic multi-step search (function-calling loop) when one-shot retrieval is insufficient.

**Requirements:** SEARCH-05, CHAT-05 — both checked `[x]` in REQUIREMENTS.md and mapped to Phase 11 in the traceability table (REQUIREMENTS.md:71,78).

## Roadmap Truths

| # | Truth | Verdict | Evidence |
|---|-------|---------|----------|
| R1 | User can search papers by topic, author, year, or adviser and open a paper's catalog page from the result | VERIFIED | Paper tab on AcademicPaperIndex: tab strip (blade:30-33), topic = free-text search, author/adviser selects (filters blade:121-133, gated `$wire->paperTabActive ?? false`), year range (pre-existing yearFrom/yearTo filters), results open the detail modal via `showPaperDetails` (blade:298,387,503; component:496-500). Sidecar author/adviser clauses proven by eval gate (see R5) |
| R2 | Assistant resolves multi-hop questions one-shot cannot, via capped agentic loop | VERIFIED | `AgenticLoop.stream_agentic_events()`: single non-streamed tool-eligible first call (agent.py:250-257) → args validated (`extra="forbid"`, agent.py:86-101) → `rrf_search` with filters (agent.py:291-298) → `merge_dedup` (agent.py:299) → max 3 executed rounds (agent.py:35,268) → final grounded streamed answer + citations frame (agent.py:315-317); empty docs → canonical refusal, zero LLM calls (agent.py:311-314). Endpoint tests green |

## Must-Have Truths (15/15 verified)

### 11-01 — Rich paper corpus shape (SEARCH-05)

| Truth | Verdict | Evidence |
|-------|---------|----------|
| Single title occurrence + authors:/research_adviser:/technical_adviser:/dean:/department:/publication_year:/paper_type:/catalog_code: segments | VERIFIED | `CorpusExporter.php:26-34` — `$segments = [$title.'.']` (title-doubling gone), all 8 labeled segments composed once |
| Metadata keys unchanged (catalog_code, department, publication_year int, paper_type, authors array, adviser/dean strings, url `/academic-papers/{id}`); corpus tag stays `catalog` | VERIFIED | `CorpusExporter.php:38,41-51` |
| schema_version stays 1; no abstract/summary/keywords keys | VERIFIED | `CorpusExporter.php:131`; locked-schema test asserts `assertArrayNotHasKey` for abstract/summary/keywords (`tests/Feature/ExportAiCorpusTest.php:97-99`) + metadata adviser values (81-82) |

### 11-02 — Author/adviser filters + golden set (SEARCH-05)

| Truth | Verdict | Evidence |
|-------|---------|----------|
| `passes()` author (case-insensitive substring over `metadata.authors`) and adviser (research OR technical, `AUTHOR_KEYS`) clauses, str()-guarded, pre-fusion | VERIFIED | `search.py:21,124-131` — applied to candidate set before RRF fusion (search.py:134-135) |
| POST /search accepts author/adviser in filters; top-level closed-schema 422 posture untouched | VERIFIED | `main.py:68` — `SEARCH_ALLOWED_KEYS` unchanged; `main.py:107-121` forwards filters verbatim; tests `test_search_endpoint_accepts_author_adviser_filters` (test_api.py:97) + `test_search_rejects_author_at_top_level` (test_api.py:124) green in run |
| conftest fixtures mirror rich single-title template with adviser/dean keys | VERIFIED | `tests/conftest.py:58-115` — single-title text + research_adviser/technical_adviser/dean metadata |

### 11-03 — Agentic loop (CHAT-05)

| Truth | Verdict | Evidence |
|-------|---------|----------|
| First /chat/stream LLM call is single non-streamed tool-eligible (`tools=[SEARCH_TOOL]`, `tool_choice="auto"`); tool-use is the auto-detect | VERIFIED | `agent.py:250-257`; direct-answer path streams content with no frames (agent.py:260-267) so companion fallback stays valid |
| MAX_TOOL_ROUNDS=3 counts executed searches; closed-schema arg validation before execution; malformed args get one corrective turn then fail-closed; empty docs → canonical refusal with ZERO LLM calls | VERIFIED | `agent.py:35,268-269` (cap checked top of loop, rounds++ only after executed search, agent.py:307); `agent.py:272-288` (ValidationError → corrective turn, `malformed_streak >= 2` → break); `agent.py:311-314` (refusal, no `_stream_final_answer` call) |
| Additive SSE framing: `event: activity` + `event: citations` (ADR 0006 shape, merged/deduped/renumbered) before [DONE]; chunk envelope and error taxonomy unchanged | VERIFIED | `agent.py:147-148,290` (activity), `agent.py:316` (citations via `citation_payload` agent.py:114-126), `agent.py:318-325` (single error frame); frame order activity → chunks → citations → [DONE] per loop body; `main.py:159` routes `/chat/stream` through the agentic path with validation/422/401 preserved (main.py:140-157) |

### 11-04 — Paper tab (SEARCH-05)

| Truth | Verdict | Evidence |
|-------|---------|----------|
| Tab/mode on existing page, no route change; tab switches pure prop sets with no reset/requery | VERIFIED | blade:30-33 — `wire:click="$set('paperTabActive', …)"`; no state reset in any handler |
| author/adviser appended ONLY in paper-tab mode; browse payloads byte-identical (five Phase 9/10 keys) | VERIFIED | `AcademicPaperIndex.php:360-371` — five-key base dict, `author`/`adviser` appended inside `if ($this->paperTabActive)`; locked by capture test (`tests/Feature/AcademicPaperIndexHybridTest.php`, in suite) |
| Status filter force-exit honored inside tab; author/adviser refine-only; results stay in sidecar RRF order, "Results ranked by relevance" caption | VERIFIED | `updatedStatusFilter` → `exitHybridMode` (272-280); `updatedAuthorFilter/updatedAdviserFilter` → `runHybridSearch` only (317-329); sidecar order preserved by id-ordered rehydration (382-407); caption blade:258-259,462-463; idle state blade:331-334,537-540 |

### 11-05 — Agentic chat widget (CHAT-05)

| Truth | Verdict | Evidence |
|-------|---------|----------|
| `chatStreamFrames()` typed frames (chunk/activity/citations) from same read loop; `chatStreamEvents()` untouched | VERIFIED | `AiService.php:155-219` (additive event line-pair branch at 186-195); `AiService.php:87-140` legacy parser unchanged (git log shows only additions) |
| Activity frames → persistent single-line activity slot; chunk frames → ans slot; citations frame shape-gated with `companionCitations()` fallback + persistence | VERIFIED | `ChatWidget.php:165-198` (stream routing), `ChatWidget.php:259-278` (`validCitationsPayload` — array_key_exists for nullable url/catalog_code), `ChatWidget.php:204-211` (persistence); blade `wire:stream="activity"` at chat-widget.blade.php:86 above `wire:stream="ans"` (92) |
| Refusal renders as normal assistant bubble; `event: error` still maps to error bubble + Retry | VERIFIED | Refusal arrives as chunk frames → `assistantBubble($accumulated, $citations)` with no failed flag (ChatWidget.php:197-204); `AiServiceProviderException` → `failed: true` error bubble (ChatWidget.php:214-220) |

## Artifacts Verified

- `app/Services/CorpusExporter.php` — rich shape (see above); only exporter change per git (0067891b: `[$title.'.', $title.'.']` → `[$title.'.']`)
- `app/Livewire/Pages/Student/AcademicPaperIndex.php` — paperTabActive/authorFilter/adviserFilter props (63-68), computeds (237-257), snapshot extension incl. new props (440-442), clearFilters resets them (341-342)
- `resources/views/livewire/pages/student/academic-paper-index.blade.php` — tab strip, idle state, caption, wire:targets incl. new props
- `resources/views/components/academic-paper-filters.blade.php` — gated selects (121-133), Alpine data (23-24), badges (171-178)
- `app/Services/AiService.php` — `search()` forwards filters verbatim (27-36); `chatStreamFrames()` (155-219)
- `app/Livewire/ChatWidget.php` + `chat-widget.blade.php` — activity slot, citations binding, fallback, refusal path
- `ceit-ai-sidecar/app/search.py` — AUTHOR_KEYS + additive clauses
- `ceit-ai-sidecar/app/main.py` — SEARCH_ALLOWED_KEYS untouched; agentic routing (159)
- `ceit-ai-sidecar/app/agent.py` — full loop implementation
- `ceit-ai-sidecar/tests/conftest.py`, `test_filters.py`, `test_api.py`, `test_agentic_loop.py`, `test_chat_stream.py` — all named tests present and green
- `ceit-ai-sidecar/data/golden_dataset.json` — 35 test cases, 5 negatives, author/adviser/year/topic cases present; snapshot 2026-08-15T09:54:16+00:00
- `tests/fixtures/ai-sidecar/chat-stream-agentic.txt` — activity + chunk + citations + [DONE] frames
- Commits: Laravel 0067891b/659bc8c7 (11-01), f7057b8f/924fff3a/6417e258/5384b793 (11-04), f8723157/2fa175b4/c702fb3c/9c7c2111 (11-05); sidecar 3ddefbe/fc7140c/5970a56/108dc68 (11-02), 99c1302/f6cded2/a95d788/a93fcc7/0d44e49 (11-03) — all present in git log

## Test Results (re-run 2026-08-15)

| Suite | Expected | Actual | Verdict |
|-------|----------|--------|---------|
| Laravel `php artisan test` | 598 passed / 3 skipped (585 baseline + 13) | **598 passed / 3 skipped** (1714 assertions, 54.94s) | MATCH |
| Sidecar `uv run pytest tests/ -q` | 67 passed / 1 skipped (49 baseline + 18) | **67 passed / 1 skipped** | MATCH |
| Sidecar eval gate `python -m app.eval --corpus catalog` | negative pass 100%, positives hit expected ids | **exit 0; negative pass rate 1.0; top-1 0.9259; P@5 0.6 / R@5 0.8414 / F1 0.6112**; author/adviser cases ("papers by Bill Beier", "papers by Georgette Swift", "papers advised by Chadd Legros") recall 1.00 | MATCH |
| `uv run ruff check app/ tests/` | clean | **All checks passed** | MATCH |

## Review Findings (11-REVIEW.md) — All WARNING-class, none BLOCKER

- **WR-1 multi-tool-call** (agent.py:270-271, 156-168): confirmed — `tool_calls[0]` executed but all calls appended; unmatched `tool_call_id` can 400 on stricter providers. Latent robustness gap; no test emits parallel calls. Not a success-criteria blocker.
- **WR-2 context blow-up** (agent.py:300-306): confirmed — full `json.dumps(results)` incl. `text` into tool message; worst case ~3 MB. Cost/latency risk, grounding unaffected (final prompt truncates via `build_context`). Warning.
- **WR-3 unescaped activity HTML** (ChatWidget.php:177): confirmed — `$frame['payload']['text']` concatenated unescaped into `$this->stream()`. Safe today only by static-copy producer trust (agent.py:129-144). XSS-hygiene warning; sidecar change or regression would be needed to exploit.
- **WR-4 inert badges in Browse mode** (AcademicPaperIndex.php:317-329 + filters blade:171-178): confirmed — badges render unconditionally on `$wire.authorFilter`/`$wire.adviserFilter` while payload passthrough is tab-gated; stale values survive tab switches. UX-only warning; browse payloads remain byte-identical (tests lock this), so no functional corruption.

IN-1..IN-8 (cosmetic/robustness) confirmed consistent with review; none affect the success criteria.

## Cross-Phase Regression

- Full Laravel suite green — phases 8/9/10 tests (ExportAiCorpusTest, ReconcileAiIndexTest, HybridTest, SimilarTest, ChatWidgetTest) all pass; no breakage from the exporter shape change or the paper tab.
- Sidecar suite green — Phase 10 422-posture tests (`test_search_rejects_unknown_fields`, `/chat/stream` closed keys) still pass; ADR 0004 contract intact; ADR 0006 citation shape preserved in the citations frame and the Laravel shape gate.

## Findings (verifier)

**Blockers:** None.

**Warnings:**
1. WR-1 multi-tool-call / `content: ""` on assistant tool-call messages — worst case a multi-call response turns into "provider temporarily unavailable" with zero answer; untested.
2. WR-2 unbounded tool-message text — cost/context risk on policy queries.
3. WR-3 unescaped activity text in `$this->stream()` — injection-safe only by producer trust.
4. WR-4 stale author/adviser badges advertised in Browse mode (inert values) — UX mismatch.
5. Live LLM path not exercised (fakes only; `SIDECAR_LIVE_TEST` unset) — loop behavior verified against scripted completions; real OpenRouter tool-call reliability remains a production watch item (per 11-03).

## Summary

Phase goal and both ROADMAP success criteria are achieved in code, goal-backward verified. All 15 must-have truths VERIFIED at EXISTS+SUBSTANTIVE+WIRED levels: exporter shape locked by test, filters live end to end (Laravel payload → sidecar `passes()` → eval gate proves recall), the paper tab is wired and payload-contract tested both directions, and the agentic loop implements the capped function-calling loop with closed-schema validation, zero-LLM fail-closed refusal, and additive SSE frames consumed by the widget with citations persistence. Both suites and the eval gate match the claimed baselines exactly; ruff clean; traceability table updated. The four 11-REVIEW warnings are quality/robustness gaps, not blockers.
