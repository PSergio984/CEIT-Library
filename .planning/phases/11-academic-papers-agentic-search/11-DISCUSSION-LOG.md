# Phase 11: Academic Papers & Agentic Search - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-15
**Phase:** 11-academic-papers-agentic-search
**Areas discussed:** Paper corpus content, Paper search UX, Agentic trigger, Agentic loop shape

---

## Paper Corpus Content

| Option | Description | Selected |
|--------|-------------|----------|
| Bibliographic-only | No schema change. Each paper's index doc composes title + authors + advisers + dean + department + type + year into richer text. 'Topic' = keywords found in title/authors. | ✓ |
| Add abstract field | Nullable abstract/summary column + admin form field. Requires data entry; corpus starts sparse. | |
| New details table | Separate related table (paper_details) if richer metadata anticipated later. | |

**User's choice:** Bibliographic-only
**Notes:** Papers have no body text today; the corpus indexes what exists.

| Option | Description | Selected |
|--------|-------------|----------|
| One rich doc per paper | Title + all names + department + paper_type + year composed; metadata filterable. | ✓ |
| Multi-chunk per paper | Split into title/authors/advisers chunks to weight fields differently. | |
| Keep current shape | Title-doubled shape unchanged, only new filters. | |

**User's choice:** One rich doc per paper

| Option | Description | Selected |
|--------|-------------|----------|
| Free-text over composed doc | 'Topic' = keywords matched against the rich doc. No taxonomy to maintain. | ✓ |
| Explicit topics taxonomy | Topics/keywords column or tags table; requires staff data entry. | |

**User's choice:** Free-text over composed doc

---

## Paper Search UX

| Option | Description | Selected |
|--------|-------------|----------|
| Tab/mode on existing page | Paper search becomes a mode on the student academic-papers page (like similar-books mode). | ✓ |
| New dedicated page | Separate route + page for paper search. | |
| Chat-only | Papers searchable only inside the chat widget. | |

**User's choice:** Tab/mode on existing page

| Option | Description | Selected |
|--------|-------------|----------|
| Filters + free text | Explicit controls for author, year range, adviser + free-text topic box. | ✓ |
| Natural language only | One box; users type "papers by Santos 2022"; needs parsing. | |
| Hybrid: minimal filters | Free-text box + existing page filters reused; author/adviser in free text only. | |

**User's choice:** Filters + free text

| Option | Description | Selected |
|--------|-------------|----------|
| Relevance-ranked | RRF rank order from the sidecar; filters narrow the pool. | ✓ |
| Year desc when filtered | Newest-first when a year filter/sort is active. | |
| User-selectable sort | Sort control (Relevance / Newest / Title). | |

**User's choice:** Relevance-ranked

---

## Agentic Trigger

| Option | Description | Selected |
|--------|-------------|----------|
| Model auto-detect | The model decides when one-shot retrieval is insufficient and emits tool calls. | ✓ |
| Explicit user toggle | "Deep search" toggle; loop always runs when enabled. | |
| On-user-request only | User asks in natural language ("search harder"). | |

**User's choice:** Model auto-detect

| Option | Description | Selected |
|--------|-------------|----------|
| Fail-closed to I-dont-know | On cap/error, answer with the grounding "I don't have enough information" (SEARCH-04). | ✓ |
| Fall back to one-shot answer | Return best one-shot answer with citations. | |

**User's choice:** Fail-closed to I-dont-know

| Option | Description | Selected |
|--------|-------------|----------|
| All queries, search-only tools | Any question may loop; search tools only, closed schemas. | ✓ |
| Research-questions only | Mode-classifier gates the loop to paper/catalog research questions. | |
| Search-only domains | Policy Q&A and availability stay strictly one-shot. | |

**User's choice:** All queries, search-only tools

---

## Agentic Loop Shape

| Option | Description | Selected |
|--------|-------------|----------|
| Single search tool | One tool exposing the closed `/search` contract; model varies params per step. | ✓ |
| Per-corpus tools | search_catalog / search_papers / search_policy split. | |
| Search + rewrite tool | Adds a query-rewrite helper between steps. | |

**User's choice:** Single search tool

| Option | Description | Selected |
|--------|-------------|----------|
| 3 rounds | Max 3 tool-call rounds per message (retrieval + 2 refinements). | ✓ |
| 2 rounds | Cheapest; may not cover multi-hop patterns. | |
| 5 rounds | More latitude; higher worst-case cost. | |

**User's choice:** 3 rounds

| Option | Description | Selected |
|--------|-------------|----------|
| Step activity lines | Compact per-step activity line streamed; final answer streams with citations. | ✓ |
| Silent | Only final answer streams. | |
| Visible tool trace | Collapsible trace of actual tool calls + results. | |

**User's choice:** Step activity lines

---

## the agent's Discretion

- Sidecar contract mechanics for new author/adviser filters (key names, semantics) — fit the closed-schema pattern (ADR 0004, `SEARCH_ALLOWED_KEYS`).
- Loop runtime location (sidecar vs Laravel) and exact SSE framing for step activity lines (ADR 0002/0004).
- Paper-tab composition with status filter force-exit and recommendations mode (Phase 10 pattern).

## Deferred Ideas

- Abstract/summary data source for papers (schema + admin entry) — rejected for this phase.
- Explicit topics/keywords taxonomy — rejected for this phase.
- Query-rewrite/refine helper tool — rejected (single search tool only).
- Open web search / multi-agent orchestration — locked out of scope.
