# Phase 11: Academic Papers & Agentic Search - Context

**Gathered:** 2026-08-15
**Status:** Ready for planning

<domain>
## Phase Boundary

Two capabilities, per SEARCH-05 and CHAT-05:

1. **Paper corpus with paper-specific chunking + topic/author/year/adviser search** — the existing `academic_papers` catalog gets a richer, paper-specific index document shape, new author/year-range/adviser search filters, and a dedicated paper-search tab on the student search page.
2. **Agentic multi-step search** — a capped function-calling loop over the existing closed search contract, auto-triggered when the model judges one-shot retrieval insufficient, streamed into the chat widget with step activity lines.

**Scope anchor:** papers index shape + paper search surface + agentic loop over existing contracts. No new corpora, no web search, no mutations, no multi-agent orchestration — those stay out of scope (REQUIREMENTS.md Out of Scope table).

</domain>

<decisions>
## Implementation Decisions

### Paper corpus content
- **D-01:** Bibliographic-only corpus — no abstract/summary column or table added. The index composes what exists in `academic_papers` today (title, year, paper_type, department, advisers, dean, authors).
- **D-02:** Paper-specific chunking = one rich index document per paper, composing title + author/adviser/dean names + department + paper_type + year into the searchable text. Metadata stays filterable via the existing `/search` filters mechanism. This upgrades the current title-doubled catalog doc shape (Phase 8) without adding body text.
- **D-03:** "Topic" = free-text keyword search over the composed document. No topics/keywords taxonomy column or tags table added.

### Paper search UX
- **D-04:** Paper search lives as a tab/mode on the existing student `AcademicPaperIndex` page — same pattern as the Phase 10 similar-books mode. One destination; reuses the existing card grid, detail modal, availability hydration, and hybrid search path.
- **D-05:** Expression = explicit filter controls for author, year range, and adviser, PLUS the free-text box for topic. Follows the Phase 8 filter pattern (`paper_type`, `department`, `publication_year`, `year_from`, `year_to` already exist in `/search`; author/adviser filters are new).
- **D-06:** Results ordered by hybrid relevance (sidecar RRF rank order preserved). No separate sort control.

### Agentic trigger
- **D-07:** The model auto-detects when one-shot retrieval is insufficient and enters the loop. No user toggle, no special phrase required.
- **D-08:** On loop cap or error, fail-closed to the grounding answer "I don't have enough information" (SEARCH-04 rule). Never guess, never fall back to a weaker ungrounded answer.
- **D-09:** Any question type may enter the loop, but the tool surface is search-only: no web access, no mutations, no multi-agent orchestration (REQUIREMENTS.md Out of Scope honored).

### Agentic loop shape
- **D-10:** One `search` tool exposing the existing closed `/search` contract — query + corpus (`catalog`|`policy`) + filters (department/paper_type/year range/author/adviser) + top_k. No per-corpus tool split, no query-rewrite helper.
- **D-11:** Maximum 3 tool-call rounds per user message (initial retrieval + 2 refinements). Bounded worst-case cost.
- **D-12:** The chat widget streams a compact activity line per step (e.g., "Searching papers by author…") and the final answer streams normally with numbered citations. No raw tool JSON, no collapsible trace.

### the agent's Discretion
- Sidecar contract mechanics for the new author/adviser filters (key names, filter semantics) — planner/researcher to fit the existing closed-schema pattern (ADR 0004 + sidecar `SEARCH_ALLOWED_KEYS`).
- Where the loop runtime lives (sidecar vs Laravel) and the exact SSE framing for step activity lines — planner decides within existing ADR 0002/0004 framing.
- Paper-tab composition with the existing status filter force-exit and recommendations mode (Phase 10 D-18 pattern) — planner applies the established mode-composition approach.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Requirements & roadmap
- `.planning/REQUIREMENTS.md` — SEARCH-05, CHAT-05 (this phase); Out of Scope table (web search, LLM mutations, multi-agent) locks the agentic toolset
- `.planning/ROADMAP.md` §Phase 11 — goal, success criteria (search by topic/author/year/adviser + open catalog page; multi-hop questions via capped loop)

### Locked contracts (ADRs — the existing sidecar/Laravel contracts the loop must reuse)
- `docs/adr/0004-sidecar-chat-endpoint-contract.md` — `/search` + `/chat/stream` schemas, closed keys, 422 on stray fields (the loop's `search` tool MUST stay inside this contract)
- `docs/adr/0006-citation-and-grounding-rules.md` — citation payload `{n, id, corpus, title, url, catalog_code}`, SEARCH-04 "I don't have enough information" grounding rule (the fail-closed target)
- `docs/adr/0002-chat-streaming-livewire-stream.md` — SSE framing the step activity lines must reuse
- `docs/adr/0005-conversation-history-schema.md` — where agentic messages/citations persist (Phase 9 decisions stand; loop adds no new history fields unless planning decides)
- `docs/adr/0008-chat-widget-shape.md` — widget structure the activity lines plug into

### Prior phase contexts (patterns to compose with)
- `.planning/phases/08-hybrid-search-foundation/08-CONTEXT.md` — corpus doc shape (title-doubled, `paper-{id}` ids, metadata, url), `/search` filters (paper_type, department, publication_year, year_from/year_to), `corpus` tag scheme
- `.planning/phases/09-rag-chat-policy-q-a/09-CONTEXT.md` — one-shot chat contract, citations binding, single-turn rule (the loop extends this without replaying history)
- `.planning/phases/10-live-availability-similar-book-recommendations/10-CONTEXT.md` — mode-composition pattern (similar-books mode), availability hydration (the paper tab reuses the shared card path), D-18 status-filter force-exit interaction

### New ADRs to write this phase
- Topic/author/year/adviser search + paper corpus shape (SEARCH-05) — to be crystallized as ADR 0013 during planning
- Agentic loop contract (CHAT-05) — ADR 0014

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Services/AiService.php` — `search(string $query, array $filters = [], ?string $corpus = 'catalog', int $limit = 10): array` with `RRF_CANDIDATES = 60`, typed exceptions; the single Laravel seam into the sidecar (loop + filters go through it)
- `app/Services/CorpusExporter.php` — catalog doc shape (`paper-{id}` ids, title-doubled text, metadata incl. department/paper_type, `url` = `/academic-papers/{id}`); the paper-specific shape (D-02) is an evolution of this exporter
- `app/Livewire/Pages/Student/AcademicPaperIndex.php` — hybrid search (`runHybridSearch`, `hybridResults`), filter force-exit, `availableYears/Departments/PaperTypes`, detail modal, Phase 10 recommendations mode (`showSimilar`/`backToResults`) — the paper tab composes with these
- `app/Livewire/ChatWidget.php` + `chat-widget-citations.blade.php` — message render array, streamed responses, citation chips (catalog link chips, policy non-link chips) — step activity lines integrate here
- `app/Models/AcademicPaper.php` + `research_advisers`/`technical_advisers`/`deans` tables — advisers/deans are FK'd tables (not strings); author/adviser filter data sources
- Sidecar `app/main.py` — `SEARCH_ALLOWED_KEYS = {"query", "filters", "corpus", "limit", "k"}`, filters: paper_type, department, publication_year, year_from, year_to; new author/adviser filters must stay inside the closed-schema + 422 posture

### Established Patterns
- Closed request schemas: any new search filter keys require sidecar tests asserting 422 on unknown fields (Phase 9 D-07 posture)
- `Http::fake` capture tests for sidecar payload assertions (Phase 9 `ChatWidgetTest`)
- Mode-composition in AcademicPaperIndex (status filter force-exits hybrid; recommendations mode snapshots state) — paper tab must follow the same composition rules
- Availability never LLM — the paper tab reuses `AvailabilityService` hydration via the shared card path (Phase 10 D-01..D-07)

### Integration Points
- `/search` sidecar endpoint — author/adviser filters + richer paper docs land here
- `AiService::search()` — the loop's single tool maps 1:1 onto this method
- AcademicPaperIndex page — new paper tab entry + filter controls
- ChatWidget streaming — activity line SSE events + final answer

</code_context>

<specifics>
## Specific Ideas

- Papers are bibliographic-only today (no abstract, no full text) — corpus content decisions (D-01..D-03) are bounded by what the DB actually holds; the rich doc shape is the honest maximum without new data entry.
- Sidecar runs on `127.0.0.1:8310`; `SIDECAR_TOKEN=smoke-test-token` placeholder in both `.env` files (operator sets a real token before production).
- Baseline suites: Laravel 585 passed / 3 skipped (~53s); sidecar 49 passed / 1 skipped (live smoke gated).

</specifics>

<deferred>
## Deferred Ideas

- Abstract/summary data source for papers (schema + admin entry) — explicitly rejected for this phase (D-01); revisit if staff want richer paper content (SEARCH-05 stays satisfied bibliographically).
- Explicit topics/keywords taxonomy (tags table) — rejected for this phase (D-03); candidate if topic precision testing shows free-text is insufficient.
- Open web search / multi-agent orchestration — locked out of scope (REQUIREMENTS.md); do not revisit.
- Query-rewrite/refine helper tool — rejected (D-10); single search tool only.

</deferred>

---

*Phase: 11-Academic Papers & Agentic Search*
*Context gathered: 2026-08-15*
