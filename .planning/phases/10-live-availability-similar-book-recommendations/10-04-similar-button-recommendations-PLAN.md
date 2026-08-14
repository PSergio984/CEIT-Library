---
phase: 10-live-availability-similar-book-recommendations
plan: 04
type: execute
wave: 2
depends_on: ["10-02", "10-03"]
files_modified:
  - app/Livewire/Pages/Student/AcademicPaperIndex.php
  - resources/views/livewire/pages/student/academic-paper-index.blade.php
  - tests/Feature/AcademicPaperIndexSimilarTest.php
autonomous: true
requirements: [SEARCH-06]
must_haves:
  truths:
    - "Secondary Similar button (x-mary-button, class `btn-sm btn-outline gap-2`, icon o-sparkles, label Similar, auto-width — View Details keeps flex-1) renders on all four surfaces: mobile hybrid card, mobile SQL card, desktop hybrid card, and the desktop table `actions` scope beside View (D-14, ADR 0012)"
    - "Clicking Similar enters recommendations mode in place: header bar 'Showing similar books to: {title}' (line-clamp-1) + 'Back to results'; recommendation cards render through the existing card grid markup with distinct rec- wire:keys; no URL change (D-15/D-17)"
    - "States: loading overlay 'Finding similar books...' (wire:target showSimilar), alert-warning banner 'Recommendations unavailable right now' on sidecar down, x-empty-state 'No similar books found' on empty — 'Back to results' visible in all three (D-16)"
    - "Every updated* hook + clearFilters + updatingPerPage exits recommendations mode immediately and runs the normal query; Back restores the snapshot verbatim (search, 6 filters, hybridResults + aiSearchFailed, pagination page, sortBy) without firing hooks; Similar on a recommended card recurses; the status filter still force-exits hybrid (D-18)"
  artifacts:
    - path: app/Livewire/Pages/Student/AcademicPaperIndex.php
      provides: "Recommendations-mode state (recommendedFor/recommendations/recommendationsUnavailable + snapshot), showSimilar()/backToResults(), exitRecommendationsMode() funnel, availability computed extended with recommendation ids"
      contains: "showSimilar"
    - path: resources/views/livewire/pages/student/academic-paper-index.blade.php
      provides: "4 Similar buttons, recommendations header bar, three state renderings, rec- wire:keys"
      contains: "Showing similar books to"
    - path: tests/Feature/AcademicPaperIndexSimilarTest.php
      provides: "D-15/D-18 component tests — mode entry/header/cards, exact restore, yield-on-change, recursion, unavailable banner, empty state"
      contains: "it_restores_prior_state_on_back"
  key_links:
    - from: app/Livewire/Pages/Student/AcademicPaperIndex.php
      to: app/Services/SimilarPapersService.php
      via: "showSimilar() calls (new SimilarPapersService)->for($paper, 10) and branches on ->unavailable"
      pattern: "SimilarPapersService"
    - from: resources/views/livewire/pages/student/academic-paper-index.blade.php
      to: app/Services/AvailabilityService.php
      via: "recommendation cards read the shared #[Computed] availability map (10-02) — X of Y hydrated per ADR 0010"
      pattern: "availability"
---

<objective>
Surface the ADR 0012 Similar-button UX on the student search page (D-14..D-18): a secondary "Similar" button on all four result-listing surfaces, replace-with-back recommendations mode driven by `SimilarPapersService` (10-03) with cards rendered through the existing card grid hydrated by the shared `availability` computed (10-02), the header bar with "Back to results", the three states (loading overlay / unavailable banner / empty state — Back visible in all), mobile behavior (no separate flow, `line-clamp-1` header, back pinned), and the full composition contract: snapshot-on-enter, verbatim restore, yield-on-change across every `updated*` hook, recursion, and status-filter interplay.

Purpose: SEARCH-06's "get recommendation results" UI — the deterministic list from 10-03, presented in place with exact state restore and no URL change.

Output: Similar buttons everywhere, working recommendations mode with all states, green `AcademicPaperIndexSimilarTest`. Depends on 10-02 (the `$availability` computed + X-of-Y cards) and 10-03 (the mechanism).

Commit discipline: each task is one focused commit.
</objective>

<execution_context>
@$HOME/.codex/get-shit-done/workflows/execute-plan.md
@$HOME/.codex/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/10-live-availability-similar-book-recommendations/10-RESEARCH.md
@.planning/phases/10-live-availability-similar-book-recommendations/10-CONTEXT.md
@.planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md
@.planning/phases/10-live-availability-similar-book-recommendations/10-VALIDATION.md
@docs/adr/0012-similar-button-ux.md
@docs/adr/0011-similar-books-mechanism.md
@app/Livewire/Pages/Student/AcademicPaperIndex.php
@resources/views/livewire/pages/student/academic-paper-index.blade.php
@app/Services/SimilarPapersService.php
@tests/Feature/AcademicPaperIndexHybridTest.php
@tests/fixtures/ai-sidecar/search.json
</context>

<threat_model>
ASVS L1. Block on HIGH severity threats. This plan adds a client-driven mode state machine and outbound sidecar calls to an authenticated student page.

| Threat | Severity | Mitigation in this plan |
|---|---|---|
| T-01 Snapshot restore fires `updated*` hooks, re-running queries and losing the exact state D-15 promises | MED | Restore happens via direct property assignment inside `backToResults()` (server-side) — Livewire hooks fire only on client-side property updates; the restore test asserts `Http::assertNothingSent()` after Back. |
| T-02 Mode state and snapshot diverge after a yield-point exit (stale snapshot resurrects dead state) | MED | `exitRecommendationsMode()` clears mode props AND the snapshot together; it is called at the top of every yield point (8 `updated*` hooks + `clearFilters` + `updatingPerPage` = 10) so any query change abandons the snapshot. |
| T-03 Unbounded recommendation payload or recursion loops | LOW | `SimilarPapersService::for()` is called with the default `$limit = 10`; recursion is one user click at a time (a new `showSimilar` call replaces the list — no automated loop). |
| T-04 Title text rendered unescaped in the header bar / cards (XSS) | MED | All titles render through Blade `{{ }}` escaping (existing card markup) and the header bar uses `{{ $paper->title }}` — no `{!! !!}` anywhere in the new markup. |
| T-05 DOM diffing reuses wrong nodes when the list swaps (wire:key collision) | LOW | Recommendation grids use a distinct `rec-mobile-{id}` / `rec-desktop-{id}` wire:key prefix vs `mobile-paper-{id}` / `hybrid-desktop-{id}` (RESEARCH.md landmine #10). |

No HIGH-severity threat is left without a mitigation — nothing blocks this plan.
</threat_model>

<tasks>

<task type="auto">
  <name>Task 1: Recommendations-mode state + showSimilar() + backToResults()</name>
  <files>app/Livewire/Pages/Student/AcademicPaperIndex.php</files>
  <read_first>app/Livewire/Pages/Student/AcademicPaperIndex.php (mode-as-property idiom at 57-60, runHybridSearch pipeline at 280-333, showPaperDetails dispatch at 345-349, the availability computed from 10-02), app/Services/SimilarPapersService.php (for() + unavailable flag from 10-03), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.3)</read_first>
  <action>
  - Add `use App\Services\SimilarPapersService;`.
  - Add public props next to the hybrid-state block (line 57-60): `public ?int $recommendedFor = null;`, `public ?array $recommendations = null;`, `public bool $recommendationsUnavailable = false;`, `public ?string $recommendedTitle = null;` (the seed paper's title for the header bar — must be a PUBLIC prop: the view cannot read private state).
  - Add a private snapshot prop `private array $recommendationsSnapshot = [];`.
  - `public function showSimilar(int $paperId): void`:
    1. Snapshot BEFORE entering mode: `$this->recommendationsSnapshot = ['search' => $this->search, 'statusFilter' => ..., 'departmentFilter' => ..., 'paperTypeFilter' => ..., 'yearFilter' => ..., 'yearFromFilter' => ..., 'yearToFilter' => ..., 'hybridResults' => $this->hybridResults, 'aiSearchFailed' => $this->aiSearchFailed, 'page' => $this->getPage('academic-papers-index'), 'sortBy' => $this->sortBy]` — every one of the six filters, both hybrid props (array references are fine — nothing mutates the prior list while in mode), the pagination page via the WithPagination `getPage('academic-papers-index')` helper, and `sortBy`.
    2. `$paper = AcademicPaper::find($paperId); if (! $paper) { return; }` (unknown id no-ops — the button only renders for loaded papers).
    3. Set `$this->recommendedFor = $paperId; $this->recommendedTitle = $paper->title;` (mode active — blade branches on `recommendedFor`; the header bar reads `recommendedTitle`).
    4. `$service = new SimilarPapersService; $this->recommendations = $service->for($paper, 10)->all();` and `$this->recommendationsUnavailable = $service->unavailable;` — when the flag is true set `$this->recommendations = []` as well (fail-closed rendering keyed off the flag).
    - No URL change, no navigation, no page reset (D-15).
  - `public function backToResults(): void`:
    - Guard: early return when `$this->recommendedFor === null` (not in mode).
    - Restore via DIRECT property assignment from the snapshot (`$this->search = $snapshot['search'];` ... all six filters, `$this->hybridResults = $snapshot['hybridResults'];`, `$this->aiSearchFailed = $snapshot['aiSearchFailed'];`, `$this->setPage($snapshot['page'], 'academic-papers-index');`, `$this->sortBy = $snapshot['sortBy'];`) — do NOT call `runHybridSearch()`, do NOT reset the page (restore is verbatim, D-15).
    - Then clear mode: `$this->recommendedFor = null; $this->recommendations = null; $this->recommendationsUnavailable = false; $this->recommendedTitle = null; $this->recommendationsSnapshot = [];`.
  </action>
  <verify>php -l app/Livewire/Pages/Student/AcademicPaperIndex.php</verify>
  <acceptance_criteria>
  - The four mode props (`recommendedFor`, `recommendations`, `recommendationsUnavailable`, `recommendedTitle`) + private snapshot exist; `showSimilar(int $paperId)` snapshots all of search/6 filters/hybridResults/aiSearchFailed/page/sortBy, then sets `recommendedFor` + `recommendedTitle` from the found paper, then calls `(new SimilarPapersService)->for($paper, 10)` and copies `->unavailable` into `recommendationsUnavailable`
  - `backToResults()` assigns every snapshot key back to its property, uses `setPage(...)` for the page, never calls `runHybridSearch()`, and clears all three mode props + the snapshot
  - `php -l` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 2: exitRecommendationsMode() funneled through every yield point</name>
  <files>app/Livewire/Pages/Student/AcademicPaperIndex.php</files>
  <read_first>app/Livewire/Pages/Student/AcademicPaperIndex.php (the 10 yield points: updatedDept 212-215, updatedSearch 217-221, updatedStatusFilter 223-230, updatedYearFilter 232-236, updatedDepartmentFilter 238-242, updatedPaperTypeFilter 244-248, updatedYearFromFilter 250-254, updatedYearToFilter 256-260, clearFilters 263-274, updatingPerPage 70-73), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.3)</read_first>
  <action>
  - Add `private function exitRecommendationsMode(): void` — clears `recommendedFor`, `recommendations`, `recommendationsUnavailable`, `recommendationsSnapshot` (all four; abandon the snapshot, D-18).
  - Call `$this->exitRecommendationsMode();` as the FIRST statement of: `updatedDept()`, `updatedSearch()`, `updatedStatusFilter()`, `updatedYearFilter()`, `updatedDepartmentFilter()`, `updatedPaperTypeFilter()`, `updatedYearFromFilter()`, `updatedYearToFilter()`, `clearFilters()`, and `updatingPerPage()`.
  - Do NOT modify the existing bodies after the guard — `updatedSearch` still resets the page + runs the hybrid search (normal query runs, D-18), `updatedStatusFilter` still calls `exitHybridMode()` (status filter keeps force-exiting hybrid per the existing rule), `updatingPerPage` still only resets the page.
  - Do NOT add the exit inside `runHybridSearch()`'s guard or `exitHybridMode()` (the explicit per-hook calls cover every path; keeping the funnel out of `exitHybridMode` preserves the status-filter rule's single source).
  </action>
  <verify>php -l app/Livewire/Pages/Student/AcademicPaperIndex.php</verify>
  <acceptance_criteria>
  - `exitRecommendationsMode()` exists and nulls all four mode/snapshot members
  - Every one of the 10 yield points (8 `updated*` hooks + `clearFilters` + `updatingPerPage`) contains `$this->exitRecommendationsMode();` as its first statement
  - `updatedStatusFilter` still contains its existing `exitHybridMode()` call; `runHybridSearch()` and `exitHybridMode()` bodies are unchanged
  - `php -l` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 3: Availability computed extended with recommendation ids</name>
  <files>app/Livewire/Pages/Student/AcademicPaperIndex.php</files>
  <read_first>app/Livewire/Pages/Student/AcademicPaperIndex.php (the #[Computed] availability from 10-02 Task 3), .planning/phases/10-live-availability-similar-book-recommendations/10-RESEARCH.md (landmine #4 — one grouped call per render)</read_first>
  <action>
  - Extend the `#[Computed] availability()` map from 10-02: merge a third id source — `collect($this->recommendations ?? [])->pluck('id')` — into the same unique ids array before the single `forPapers($ids)` call. Paginator ids + hybrid ids + recommendation ids in ONE grouped query per render (D-02/D-12; recommendation cards get X-of-Y via the shared card path).
  - The computed must tolerate `recommendations === null` (normal search mode) — `?? []` guard.
  </action>
  <verify>php -l app/Livewire/Pages/Student/AcademicPaperIndex.php</verify>
  <acceptance_criteria>
  - `availability()` merges `$this->academicPapers` ids + `$this->hybridResults` ids + `$this->recommendations` ids (with `?? []`) into one `forPapers()` call
  - `php -l` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 4: Blade — four Similar buttons</name>
  <files>resources/views/livewire/pages/student/academic-paper-index.blade.php</files>
  <read_first>resources/views/livewire/pages/student/academic-paper-index.blade.php (card action rows at 96-120 (mobile hybrid), 165-189 (mobile SQL), 268-292 (desktop hybrid) — View Details keeps `btn-sm btn-primary gap-2 flex-1`; table actions scope at 349-371), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.4)</read_first>
  <action>
  - In each of the three card action rows, INSIDE the `@if($this->canBorrow)` branch immediately after the existing `x-mary-button` (View Details), add a second button: `<x-mary-button wire:click="showSimilar({{ $paper->id }})" class="btn-sm btn-outline gap-2" icon="o-sparkles" label="Similar" spinner wire:loading.attr="disabled" wire:target="showSimilar({{ $paper->id }})" />` — `btn-outline` secondary, NO `flex-1` (auto-width secondary beside the flex-1 primary, D-14).
  - In the desktop `@scope('actions', $row)` (blade 349-371), inside the `@if($this->canBorrow)` branch beside the View button, add the same button with `$row->id` in both wire attrs.
  - Do NOT touch the low-credit `@else` branches, the modal, or the detail-modal-deferred decision (D-14 — modal gets no button this phase).
  </action>
  <verify>php artisan view:cache</verify>
  <acceptance_criteria>
  - The file contains exactly four `wire:click="showSimilar(...)` occurrences: three with `$paper->id`, one with `$row->id`, each `x-mary-button` carrying `class="btn-sm btn-outline gap-2"` and `icon="o-sparkles"` and `label="Similar"`, and NONE with `flex-1`
  - No `showSimilar` inside the detail modal markup
  - `php artisan view:cache` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 5: Blade — recommendations mode render: header bar, cards, three states</name>
  <files>resources/views/livewire/pages/student/academic-paper-index.blade.php</files>
  <read_first>resources/views/livewire/pages/student/academic-paper-index.blade.php (the mode-branch point: hybrid branch at 61/232 and mobile/desktop card markup 86-120 + 258-292 to mirror, wire:key scheme at 64/133/236, banner at 38-45, empty states at 122-129/294-303, loading overlay at 52-59, filters component include near the top), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.4 states)</read_first>
  <action>
  - Add a mode branch that takes precedence over the hybrid/SQL split: wrap the results area with `@if (! is_null($this->recommendedFor)) ... @else` (existing hybrid/SQL branches) `@endif`.
  - Header bar (inside the mode branch, ABOVE the card list, D-15/D-17): a flex row with `Back to results` first (`<button type="button" wire:click="backToResults" class="btn btn-sm btn-outline gap-2">` with an arrow-left icon) and a `line-clamp-1` title span: `<span class="line-clamp-1 text-sm font-medium text-base-content/70">Showing similar books to: {{ $this->recommendedTitle ?? '' }}</span>` — reads the public `recommendedTitle` prop set in `showSimilar()`, escaped via `{{ }}` (D-17 truncation).
  - Loading overlay: `wire:loading.flex wire:target="showSimilar"` with the existing overlay classes (52-59) and label `Finding similar books...`.
  - Unavailable banner when `$this->recommendationsUnavailable`: `<div class="alert alert-warning text-sm mb-4">` + inline heroicon SVG + `<span>Recommendations unavailable right now</span>` (D-16, banner pattern 38-45). No fallback list.
  - Empty state when `$this->recommendations` is empty and NOT unavailable: `<x-empty-state icon="o-document-magnifying-glass" title="No similar books found" message="..." :show-action="false" size="sm" />` (D-16).
  - Cards: when recommendations are non-empty, render the SAME card markup as the hybrid grid — a mobile list (`block xl:hidden`) and desktop grid (`hidden xl:grid grid-cols-2 gap-6`) mirroring lines 86-120 and 258-292 — with wire:keys `rec-mobile-{id}` and `rec-desktop-{id}` (distinct prefix, landmine #10), the action row WITH both buttons (View Details + Similar — recursion, D-18), and the X-of-Y Copies cell reading the shared `$availability` map (from 10-02 Task 4 markup).
  - Keep `Back to results` visible in ALL three states (header bar renders unconditionally inside the mode branch, D-16).
  - Hide the SQL paginator inside the mode branch (no pagination in recommendations mode).
  </action>
  <verify>php artisan view:cache</verify>
  <acceptance_criteria>
  - The blade contains the literal `Showing similar books to:` (inside an escaped `{{ }}`), a `Back to results` button wired to `backToResults`, the literal `Finding similar books...`, the literal `Recommendations unavailable right now`, and the literal `No similar books found`
  - Recommendation card loops use `rec-mobile-` / `rec-desktop-` wire:key prefixes; the Copies cell inside them reads `$availability[$paper->id]`
  - `Back to results` markup sits OUTSIDE the three state conditionals (always rendered in mode)
  - No `{!!` raw-echo in any new markup; `php artisan view:cache` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 6: AcademicPaperIndexSimilarTest — mode entry, restore, yield, recursion, states</name>
  <files>tests/Feature/AcademicPaperIndexSimilarTest.php</files>
  <read_first>tests/Feature/AcademicPaperIndexHybridTest.php (whole file — seedPapers/forceFill, twoResultSearch, Livewire::test conventions, ConnectionException fake at 149, Http::assertNothingSent at 178, Inventory seeding at 187-191), app/Services/SimilarPapersService.php (10-03), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (sections 2.9 + 3.7)</read_first>
  <action>
  - Create `tests/Feature/AcademicPaperIndexSimilarTest.php` (PHPUnit #[Test] attributes, RefreshDatabase, `Http::preventStrayRequests()` in every test, `config(['services.ai_sidecar.token' => 'test-token'])`). Seed 77 + 78 with `forceFill`; Inventory rows for paper 78: 2 Available of 3 (to prove hydrated numbers on recommendation cards). Fake `http://127.0.0.1:8310/search` with the two-result fixture.
  - `it_enters_recommendations_mode_with_header_and_cards`: `Livewire::test(AcademicPaperIndex::class)->set('search', 'water pump')->call('runHybridSearch')->call('showSimilar', 77)` → `assertSet('recommendedFor', 77)`, `assertSet('recommendations.0.id', 78)`, `assertSee('Showing similar books to:')`, `assertSee('Back to results')`, `assertSee('2 of 3 available')` (hydrated via the shared availability computed).
  - `it_restores_prior_state_exactly_on_back`: enter with search text + a filter + hybrid results, `call('backToResults')` → `assertSet('search', ...)`, `assertSet('statusFilter', ...)`, `assertSet('recommendedFor', null)`, `assertSet('hybridResults', fn ($r) => $r[0]->id === 77)`, `assertSet('page', 1)` (pageName 'academic-papers-index') — and `Http::assertNothingSent()` after the back call (restore runs no query).
  - `it_exits_mode_when_search_is_edited`: enter mode, `set('search', 'pump')` → `assertSet('recommendedFor', null)` and a fresh `/search` request was sent (mode abandoned, normal query runs, D-18).
  - `it_exits_mode_on_status_filter_and_still_exits_hybrid`: enter mode from hybrid, `set('statusFilter', 'Available')` → `assertSet('recommendedFor', null)` AND `assertSet('hybridResults', null)` (status filter force-exits hybrid per the existing rule).
  - `it_recurses_similar_on_a_recommended_card`: in mode (recommendedFor 77), `call('showSimilar', 78)` → `assertSet('recommendedFor', 78)` and the list is replaced (assertSet recommendations.0 id != 77).
  - `it_shows_unavailable_banner_when_sidecar_is_down`: `Http::fake(['http://127.0.0.1:8310/*' => fn () => throw new ConnectionException('Connection refused')])`; `call('showSimilar', 77)` → `assertSet('recommendationsUnavailable', true)`, `assertSee('Recommendations unavailable right now')`, `assertSee('Back to results')`.
  - `it_shows_empty_state_when_no_similar_books`: fake `/search` returning `{"query": "...", "total": 0, "results": []}` → `assertSee('No similar books found')` + `assertSee('Back to results')`, `assertSet('recommendationsUnavailable', false)`.
  </action>
  <verify>php artisan test --filter=AcademicPaperIndexSimilarTest</verify>
  <acceptance_criteria>
  - All 7 tests pass: `php artisan test --filter=AcademicPaperIndexSimilarTest` exits 0
  - The back-restore test proves verbatim restore (search/filter/hybrid/page) AND `Http::assertNothingSent()` — no hooks fired, no query re-ran
  - The yield tests prove mode exit on search edit and on status filter (with hybrid still force-exited); the recursion test proves `recommendedFor` swaps to the new seed
  - The two state tests prove banner/empty rendering with `Back to results` present in both
  - No real API key value anywhere; `Http::preventStrayRequests()` present in every test
  </acceptance_criteria>
</task>

</tasks>

<verification>
- [ ] `php artisan test --filter=AcademicPaperIndexSimilarTest` — 7 passing
- [ ] `php artisan test --filter=AcademicPaperIndexHybridTest` — upstream wave-1 seams stay green (10-02 changed the same component/blade)
- [ ] `php artisan test` — full Laravel suite green (557 passed / 3 skipped baseline)
- [ ] `vendor/bin/pint app/Livewire/Pages/Student/AcademicPaperIndex.php resources/views/livewire/pages/student/academic-paper-index.blade.php tests/Feature/AcademicPaperIndexSimilarTest.php` — exits 0
</verification>

<success_criteria>
- All 6 tasks complete
- SEARCH-06 UI: Similar on every result surface → recommendations mode with header + Back, hydrated cards, all three states with Back always visible, exact restore, immediate exit on any query change, recursion — no URL change
- 10-03's deterministic mechanism is fully surfaced; 10-02's hydration feeds the recommendation cards through the shared card path
</success_criteria>

<output>
After completion, create `.planning/phases/10-live-availability-similar-book-recommendations/10-04-SUMMARY.md`
</output>
