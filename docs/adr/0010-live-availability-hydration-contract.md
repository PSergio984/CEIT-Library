# Live availability hydration: grouped query, never the LLM

SEARCH-02 ("search results show live availability status, copies available/total, sourced from Inventory/BorrowTransaction, never from the LLM") is pinned by a Laravel-side hydration contract built on the app's existing copy-status semantics. Availability is a **read-time hydration** concern: computed from `inventory` rows when a result set renders, never persisted, never carried by the sidecar or the model.

**The hydration shape is `{available, total, checked_at}` per catalog id.** `total` is every `Inventory` copy row for the paper (the existing `total_copies_count` semantics); `available` counts rows with `status = 'Available'` only — `Reserved` and `Unavailable` never count, matching the app's established semantics (`AcademicPaper::getAvailableCopiesCountAttribute`, `Inventory::isAvailable()`). `checked_at` is `now()` captured at fetch time and never persisted — the counts are computed live per render, so the value is true by construction.

**One grouped query per render, never per row.** A shared service, `AvailabilityService::forPapers(array $ids): array`, is the single source of truth for the shape, built on a single `whereIn` grouped query:

```php
Inventory::whereIn('academic_paper_id', $ids)
    ->selectRaw('academic_paper_id,
        COUNT(*) AS total,
        SUM(CASE WHEN status = "Available" THEN 1 ELSE 0 END) AS available')
    ->groupBy('academic_paper_id');
```

Call sites: the chat citation chips (the ≤5 catalog ids from the parsed citation payload when a message renders — ADR 0006's companion `/search` binding gives the ids) and the student search page (the paginator's ids plus the hybrid results' ids, after the query runs). The search page keeps its query-level `withCount` for `orderBy('status')` sorting and the availability status filter only; **display** moves through the hydrator so the shape and `checked_at` are identical across surfaces.

**Both surfaces show it.** Result cards read "X of Y available" in all three render spots (SQL list, hybrid list, detail modal — upgraded from today's "X available"). Catalog citation chips carry a compact "2/3" suffix beside the `catalog_code` with a color cue (green when ≥1 available, gray/red at zero). Policy chips are unchanged — policy records have no availability concept. The timestamp renders as a static muted caption "Checked just now" on result cards only; chips omit it (space-constrained, and the "2/3" already conveys live-ness).

**The never-LLM guarantee is enforced by construction and proven by tests.** Availability exists in exactly one place — `AvailabilityService` over `inventory` — and the sidecar request schemas stay closed to the ADR 0004 contract keys (`query`, `mode`, `corpus`, `top_k`), so a stray availability field 422s rather than reaching the prompt. Hydration applies post-response in Livewire/views only. Test proof: (1) `Http::fake()` capture asserts `AiService::search()` and `chatStream()` request bodies carry exactly the ADR 0004 keys; (2) the citation payload built from `/search` contains exactly the ADR 0006 keys `{n, id, corpus, title, url, catalog_code}`; (3) sidecar tests assert `/search` and `/chat/stream` reject unknown fields with 422; (4) a hydrator unit test covers mixed copy statuses and `checked_at` ≈ now; (5) a render-level test shows the numbers on screen while captured sidecar payloads contain none.

_Considered (rejected):_ storing `checked_at` in a DB column (the counts are per-render; persistence implies staleness); re-deriving availability through the LLM or sidecar (explicitly forbidden by SEARCH-02 and ADR 0004); per-row counts on the chat widget (N+1); a caching/TTL layer pre-emptively (deferred until profiling shows N+1 pain on large result sets).
