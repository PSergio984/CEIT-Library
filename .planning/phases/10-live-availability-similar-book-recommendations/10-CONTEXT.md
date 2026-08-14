# Phase 10: Live Availability & Similar-Book Recommendations - Context

**Gathered:** 2026-08-14
**Status:** Ready for planning
**Source:** Wayfinder map decisions (PSergio984/CEIT-Library issue #23, closed) — ADR 0010-0012 under `docs/adr/`, all human-approved and locked

<domain>
## Phase Boundary

Phase 10 delivers SEARCH-02 and SEARCH-06 on top of the Phase 9 chat foundation: live copy availability (copies available/total, never LLM-generated) hydrated onto search result cards and chat citation chips, and "books similar to X" recommendations surfaced through a Similar button on search results. Both reuse the existing deterministic sidecar `/search` (ADR 0004 contract, ADR 0006 companion binding) — no new sidecar endpoint, no new LLM involvement.

</domain>

<decisions>
## Implementation Decisions

### Live availability hydration contract (ADR 0010)
- **D-01**: Hydration shape per catalog id is `{available, total, checked_at}` — `total` = all `Inventory` copy rows, `available` = rows with `status = 'Available'` only (existing app semantics; Reserved/Unavailable never count), `checked_at` = `now()` at fetch time, never persisted.
- **D-02**: One grouped `whereIn` query per render — `Inventory::whereIn('academic_paper_id', $ids)->selectRaw('academic_paper_id, COUNT(*) AS total, SUM(CASE WHEN status = "Available" THEN 1 ELSE 0 END) AS available')->groupBy('academic_paper_id')` — inside a shared `AvailabilityService::forPapers(array $ids): array`, the single source of truth for the shape; never per-row.
- **D-03**: Call sites — chat citation chips (the ≤5 catalog ids from the parsed citation payload when a message renders) and the student search page (paginator ids + hybrid result ids, after the query runs). The search page keeps its query-level `withCount` for `orderBy('status')` sorting and the availability status filter only; display moves through the hydrator.
- **D-04**: Surfaces — result cards show "X of Y available" in all three render spots (SQL list, hybrid list, detail modal); catalog citation chips carry a compact "2/3" suffix beside `catalog_code` with a color cue (green ≥1, gray/red 0); policy chips unchanged (no availability concept).
- **D-05**: Timestamp renders as a static muted caption "Checked just now" on result cards only; chips omit it (space-constrained, "2/3" already conveys live-ness).
- **D-06**: Never-LLM guarantee — availability exists only in `AvailabilityService` (Inventory query); the sidecar request schemas stay closed to the ADR 0004 contract keys (`query`, `mode`, `corpus`, `top_k`) so a stray availability field 422s; hydration applies post-response in Livewire/views only.
- **D-07**: Test proof — (1) `Http::fake()` capture asserts `AiService::search()`/`chatStream()` bodies carry exactly the ADR 0004 keys; (2) citation payload built from `/search` contains exactly ADR 0006 keys `{n, id, corpus, title, url, catalog_code}`; (3) sidecar tests assert `/search` + `/chat/stream` reject unknown fields with 422; (4) hydrator unit test covers mixed copy statuses and `checked_at` ≈ now; (5) render-level test shows numbers while captured sidecar payloads contain none.

### Similar-books mechanism (ADR 0011)
- **D-08**: Laravel service `SimilarPapersService::for(AcademicPaper $paper, int $limit = 10): Collection<AcademicPaper>` — builds the query from the paper's title only, calls `AiService::search()` verbatim (`corpus: 'catalog'`, `limit: 10`, k at the 60-candidate RRF pool constant), no metadata filters.
- **D-09**: Query construction — title only; no authors/advisers/department terms (author terms bias toward same-author output; metadata affinity is already in the doc text/embedding).
- **D-10**: No metadata filters (neither department nor paper_type) — ranking self-selects affinity; sidecar filter support remains a one-line upgrade path if quality testing demands it.
- **D-11**: Self-exclusion client-side by id — drop the seed paper's `paper-N` id from the ranked list before mapping to models; zero sidecar change (RRF pool of 60 absorbs the wasted rank slot).
- **D-12**: Returns the same `AcademicPaper` collection shape the search page renders (sidecar rank order preserved) — callers reuse existing cards; ADR 0010 hydrator feeds them through the shared card path.
- **D-13**: Empty case fails closed, no fallback mechanism — sidecar down (Unavailable/Auth) → empty result + flag, caller renders "Recommendations unavailable right now"; empty or self-exclusion-emptied → empty collection, caller renders the empty state ("No similar books found"); no SQL fallback, no LLM fallback (SEARCH-06's "recommendation results" is a deterministic list, never an LLM answer).

### Similar-button UX (ADR 0012)
- **D-14**: Secondary "Similar" button (outline, icon) on every result-listing surface — card action row next to "View Details" (View Details keeps `flex-1` primary, Similar is auto-width secondary; all card render spots incl. hybrid grid) and the desktop SQL table's `actions` column scope next to "View". Detail modal deferred.
- **D-15**: Click = replace-with-back, in place — recommendations mode with header bar "Showing similar books to: X" + "Back to results" button; recommendation cards render through the existing card grid (identical markup, hydrated per ADR 0010); no URL change; back restores prior state exactly (search text, filters, hybrid-vs-SQL mode, pagination page).
- **D-16**: States — loading via the existing overlay pattern ("Finding similar books..."); sidecar down renders the alert-warning banner pattern ("Recommendations unavailable right now"), no fallback list; empty/self-exclusion-emptied renders the existing `x-empty-state` ("No similar books found"); all three keep "Back to results" visible.
- **D-17**: No separate mobile flow — the button is already in the card action row; header bar stacks with `line-clamp-1` title truncation; back pinned at the top of the list.
- **D-18**: Composition — entering snapshots the prior results state, exiting restores it verbatim; editing search/filters while in the mode exits it immediately and runs the normal query (status filter still force-exits hybrid per the existing rule); Similar on a recommended card replaces the list with that paper's recommendations (recursive, same mechanism); status badges render via the hydrated shape; the status *filter* applies to searches only.

### the agent's Discretion
- `AvailabilityService` class layout, method signatures, and file placement (e.g., `app/Services/AvailabilityService.php`), including how `checked_at` is captured and returned.
- `SimilarPapersService` class layout and internal steps (query construction, id mapping, model loading order), and whether it lives as its own service or a method on an existing one.
- Livewire state shape for recommendations mode (e.g., `recommendedFor` paper id, snapshot properties vs re-computation on back) — must satisfy D-15's exact-restore and D-18's yield-on-change.
- Blade partial organization for the recommendations header bar and the second action button; exact Mary UI / DaisyUI class choices for the secondary Similar button.
- How the citation chip availability suffix (D-04) is injected into `chat-widget-citations.blade.php` — the chips render from persisted `citations` JSON; hydration may need a wrapper/render-time enrichment without altering the persisted payload.
- Test file layout for the D-07 suite (which feature tests, which unit tests, sidecar test location in the sidecar repo).
</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Locked decisions (read all)
- `docs/adr/0010-live-availability-hydration-contract.md` — shape, grouped query, surfaces, timestamp, never-LLM + tests
- `docs/adr/0011-similar-books-mechanism.md` — title-as-query, verbatim search call, self-exclusion, fail-closed
- `docs/adr/0012-similar-button-ux.md` — placement, replace-with-back, states, mobile, composition
- `docs/adr/0004-sidecar-chat-endpoint-contract.md` — closed request schema (the 422 enforcement), no new endpoint posture
- `docs/adr/0006-citation-and-grounding-rules.md` — companion `/search` binding, citation payload shape `{n, id, corpus, title, url, catalog_code}`

### Requirements
- `.planning/REQUIREMENTS.md` — SEARCH-02, SEARCH-06

### Laravel app (existing seams — source of truth for what exists)
- `app/Services/AiService.php` — `search(string $query, array $filters = [], ?string $corpus = 'catalog', int $limit = 10): array`, `RRF_CANDIDATES = 60`, typed exceptions
- `app/Services/CorpusExporter.php` — catalog doc shape (`paper-{id}` ids, title-doubled text, metadata incl. department/paper_type, `url` = `/academic-papers/{id}`)
- `app/Models/AcademicPaper.php` — `available_copies_count`/`total_copies_count` accessors, `copies()` hasMany, status semantics
- `app/Models/Inventory.php` — `status` in {Available, Reserved, Unavailable}, `isAvailable()`
- `app/Livewire/Pages/Student/AcademicPaperIndex.php` — hybrid search (`runHybridSearch`, `hybridResults`), `withCount(['copies as available_copies' => ...])`, status filter force-exits hybrid, `availableYears/Departments/PaperTypes`, detail modal
- `resources/views/livewire/pages/student/academic-paper-index.blade.php` — mobile card view, desktop hybrid card grid, desktop MaryUI table (`actions` scope), detail modal
- `resources/views/livewire/chat-widget-citations.blade.php` — catalog link chips (title + `catalog_code`), policy non-link chips
- `app/Livewire/ChatWidget.php` — message render array (`role`/`content`/`citations`/`failed`/`error`), citation chip partial wiring
- `routes/web.php` — `academic-paper.show` (`/academic-papers/{id}`)
- `CONTEXT.md` — glossary (Availability, Similar books added this session)

### Sidecar (read-only reference — NO contract changes in this phase)
- `C:\Users\admin\Herd\ceit-ai-sidecar\app\main.py` — `/search` (filters: paper_type, department, publication_year, year_from, year_to), `/chat/stream`, token middleware
- `C:\Users\admin\Herd\ceit-ai-sidecar\app\search.py` — `rrf_search`, deterministic ranking
</canonical_refs>

<specifics>
## Specific Ideas

- Baseline test suites: Laravel 557 passed / 3 skipped (~52s); sidecar 46 passed / 1 skipped (gated live smoke). Pint clean — scope `pint` to touched files only.
- Existing availability UI today: search cards render `{{ $paper->available_copies }} available` (three spots) — Phase 10 upgrades to "X of Y available" + "Checked just now" caption and adds the citation chip suffix.
- `aiSearchFailed` alert-warning banner + `x-empty-state` component are the established state patterns to reuse (D-16).
- The `status` filter force-exits hybrid mode (`updatedStatusFilter`) — recommendations mode must compose with this (D-18).
- Sidecar runs on `127.0.0.1:8310`; `SIDECAR_TOKEN=smoke-test-token` placeholder in both `.env` files (operator sets a real token before production).
- `Http::fake` is the established pattern for sidecar capture tests (Phase 9 `ChatWidgetTest`).
- Catalog corpus is bibliographic-only (no abstract) — title-as-query carries the same signal as cosine (ADR 0011 grounding).
- Per Phase 9 verification: sidecar payload must never gain an availability field — sidecar tests asserting 422 on unknown fields protect this (D-07 #3).
</specifics>

<deferred>
## Deferred Ideas

- Chat-widget interception of "similar to X" — routed to the shared mechanism, never through the LLM; graduates after the Similar-button surface exists (map fog).
- Availability caching/TTL if profiling shows N+1 pain on large result sets — not built pre-emptively (map fog).
- Whether "similar" ever extends beyond the catalog corpus — SEARCH-06 says "books", catalog-only today (map fog).
- Dedicated embedding-cosine `/similar` sidecar endpoint — upgrade path if quality testing demands it, not pre-emptive (map out of scope).
- LLM-generated availability — explicitly forbidden by SEARCH-02 (map out of scope).
- Agentic multi-step search (CHAT-05) — Phase 11.
</deferred>

---

*Phase: 10-live-availability-similar-book-recommendations*
*Context gathered: 2026-08-14 via wayfinder map decisions (ADRs 0010-0012)*
