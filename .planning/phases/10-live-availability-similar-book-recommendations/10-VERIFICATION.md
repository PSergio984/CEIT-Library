---
status: passed
phase: 10-live-availability-similar-book-recommendations
created: 2026-08-15
verifier: gsd-verifier
---

# Phase 10 Verification — Live Availability & Similar-Book Recommendations

## Goal vs Actual

**Goal:** Enrich search results and chat citations with live copy availability resolved from Inventory/BorrowTransaction (never LLM-generated), and add "books similar to X" recommendations.

**Actual:** Fully achieved.

- Live `X of Y available` + `Checked just now` caption renders from the `AvailabilityService::forPapers()` hydrator (single grouped query, `checked_at = now()` at fetch time) on all five card surfaces (rec-mobile, rec-desktop, hybrid-mobile, mobile-SQL, hybrid-desktop) and a color-cued `available/total` suffix on catalog citation chips. Availability is computed in Livewire `#[Computed]` properties post-response — it never enters any sidecar request body.
- "Books similar to X" works via the Similar button (all four result surfaces) and the "Similar to: {title}" recommendations mode with loading/unavailable/empty states; it reuses the deterministic sidecar `/search` (title-only query, no metadata filters, k=60 via `RRF_CANDIDATES`), fail-closed with no SQL/LLM fallback.

## Requirement Traceability

| Requirement | Plan | Status |
|---|---|---|
| SEARCH-02 (live availability) | 10-01 sidecar strict validation | accounted (frontmatter `requirements: [SEARCH-02]`) |
| SEARCH-02 | 10-02 availability hydration | accounted |
| SEARCH-02 | 10-05 citation availability | accounted |
| SEARCH-06 (similar books) | 10-03 similar-papers service | accounted |
| SEARCH-06 | 10-04 similar-button recommendations | accounted |

No requirement leakage or missing attribution.

## Per-Plan Must-Have Verification (17 truths, all confirmed against code)

### 10-01 sidecar strict validation (3/3)
- `_reject_unknown(payload, allowed)` is the FIRST statement of `search()` (main.py:100) and `chat_stream()` (main.py:131); `SEARCH_ALLOWED_KEYS = {query, filters, corpus, limit, k}`, `CHAT_ALLOWED_KEYS = {query, mode, corpus, top_k}` (main.py:59-60).
- `test_search_rejects_unknown_fields` / `test_search_rejects_exclude_field` assert `error.code == "invalid_request"` (test_api.py).
- Guard returns `None` on valid payloads — no response-shape change (no-contract posture).

### 10-02 availability hydration (4/4)
- `AvailabilityService::forPapers(array $ids)` = one grouped `whereIn` query (`COUNT(*) AS total, SUM(CASE WHEN status = "Available" ...) AS available`), `checked_at = now()`, returns `[id => {available, total, checked_at}]`. (AvailabilityService.php:15-44)
- `'X of Y available'` + muted `'Checked just now'` in all card spots (blade:118/192/279/358/471 and :119/193/280/359/472) with `?? 0` fallback; withCount remains only for `orderBy('status')` and the status filter (AcademicPaperIndex.php:164-171, 334-348); cards render from `$this->availability`.
- Captured `/search` bodies carry exactly the 5 keys (AiService.php:30-34) — hydration is Livewire-side only.
- Hydrator unit suite (mixed-status math, freshness) included in the 585 passing tests.

### 10-03 similar-papers service (3/3)
- `SimilarPapersService::for()` calls `(new AiService)->search($paper->title, [], 'catalog', $limit)` verbatim — title only, `filters = []`, `k` at 60 via `RRF_CANDIDATES`. (SimilarPapersService.php:23)
- Seed self-exclusion via `reject(fn ($id) => $id === $paper->id)` on mapped int id; rank order preserved via `keyBy` + re-map over the ordered id list.
- Fail-closed: `AiServiceUnavailableException|AiServiceAuthException` → empty collection + `unavailable = true`; empty retrieval/self-exclusion → empty + flag false; no SQL or LLM fallback.

### 10-04 similar-button recommendations (4/4)
- Similar button (`btn-sm btn-outline gap-2`, `o-sparkles`, label Similar, auto-width; View Details keeps `flex-1`) on all four surfaces: mobile hybrid (:209-212), mobile SQL (:296-299), desktop hybrid (:375-378), desktop table `actions` scope (:577-585).
- Recommendations mode in place: header bar `Showing similar books to: {title}` (`line-clamp-1`) + `Back to results` (:66-68); rec cards render through the card grid with distinct `rec-mobile-`/`rec-desktop-` wire:keys; no URL change.
- Three states: loading overlay `Finding similar books...` (`wire:target="showSimilar, backToResults"`), alert-warning `Recommendations unavailable right now` (:71-77), `x-empty-state` `No similar books found` (:79-83); Back visible in all three.
- All 8 `updated*` hooks + `clearFilters()` (:226-291) + `updatingPerPage()` (:83-86) call `exitRecommendationsMode()`; `backToResults()` restores the snapshot verbatim (search, 6 filters, hybridResults, aiSearchFailed, page, sortBy) via direct property sets (no hooks); Similar on recommended cards recurses; status filter still force-exits hybrid (:244-247).

### 10-05 citation availability (3/3)
- Chips render `available/total` suffix with `text-success` (>0) / `text-error` (0); policy chips and the sources partial unchanged (chat-widget.blade.php:52-53).
- `availabilityMap()` computed once per render across ALL messages' catalog citations (single `forPapers` call, keyed by catalog_code, chat-widget.blade.php:52); persisted 6-key citations payload never modified (guard: `it_persists_citation_payload` assertSame passes).
- `chatStream` bodies `{query, mode, top_k}` + corpus when non-null (AiService.php:56-59); `/search` companions exactly 5 keys — no availability keys anywhere.

## Review-Fix Confirmation (3/3 present)

| Finding | Fix commit | Verified in code |
|---|---|---|
| W-1 zero-inventory chip cue | `33b934d2 fix(10-01): fall back to red 0/0 cue for zero-inventory catalog citations` | availabilityMap injects 0/0 entries when paper absent from forPapers (ChatWidget.php:307-312); chips render red 0/0 |
| W-2 first-click loading overlay | `ad57f148 fix(10-02): show similar-books loading overlay on the first click` | overlay lives OUTSIDE the rec-mode guard with comment `(W-2)` (blade:49-52) |
| W-3 rec badges from shared availability | `245670e6 fix(10-03): derive recommendation badges from the shared availability computed` | rec-mobile/rec-desktop badges read `$this->availability[$paper->id]['available'] ?? 0` (blade:94-95, 168-169) |

## Never-LLM Guarantee

Confirmed end-to-end: `AiService::search()`/`chatStream()` build requests from exactly the ADR 0004 key sets (no availability key can exist in a valid payload), and the sidecar rejects any unknown key with 422 `invalid_request` as the first statement of both endpoints. Availability is computed only in Livewire `#[Computed]` properties after the response. Locked decisions D-01..D-18 honored; no ADR 0010/0011/0012 deviation found.

## Test Suite Results

| Suite | Expected | Actual |
|---|---|---|
| `php artisan test` (CEIT-Library) | 585 passed / 3 skipped | **585 passed / 3 skipped** (1641 assertions, 61.04s) |
| `uv run pytest -q` (ceit-ai-sidecar) | 49 passed / 1 skipped | **49 passed / 1 skipped** (22.74s) |

## Gaps

None. Phase 10 goal fully achieved; SEARCH-02 and SEARCH-06 satisfied.
