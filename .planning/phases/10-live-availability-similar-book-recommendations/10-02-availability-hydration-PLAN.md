---
phase: 10-live-availability-similar-book-recommendations
plan: 02
type: execute
wave: 1
depends_on: ["10-01"]
files_modified:
  - app/Services/AvailabilityService.php
  - app/Livewire/Pages/Student/AcademicPaperIndex.php
  - resources/views/livewire/pages/student/academic-paper-index.blade.php
  - tests/Unit/AvailabilityServiceTest.php
  - tests/Feature/AiServiceTest.php
  - tests/Feature/AcademicPaperIndexHybridTest.php
autonomous: true
requirements: [SEARCH-02]
must_haves:
  truths:
    - "AvailabilityService::forPapers(array $ids): array returns [id => {available, total, checked_at}] from ONE grouped whereIn query (COUNT(*) AS total, SUM(CASE WHEN status = \"Available\" ...) AS available) — the single source of truth for the shape (D-01/D-02, ADR 0010 verbatim)"
    - "Student search page renders 'X of Y available' + the static muted caption 'Checked just now' in all three card spots (mobile hybrid, mobile SQL, desktop hybrid); the query-level withCount stays ONLY for orderBy('status') and the status filter (D-03/D-04/D-05)"
    - "Captured /search request bodies carry exactly {query, filters, corpus, limit, k} with no availability keys — Hydration applies post-response in Livewire only (D-06/D-07 #1, SEARCH-02 never-LLM)"
    - "checked_at = now() at fetch time, never persisted; hydrator unit test proves mixed-status math (Available/Reserved/Unavailable) and checked_at freshness within 5s (D-01/D-07 #4)"
  artifacts:
    - path: app/Services/AvailabilityService.php
      provides: "New read-only service: forPapers(array $ids): array — grouped inventory hydration, single call per render"
      contains: "forPapers"
    - path: tests/Unit/AvailabilityServiceTest.php
      provides: "D-07 #4 — mixed copy statuses, total = all rows, checked_at ≈ now"
      contains: "checked_at"
    - path: tests/Feature/AiServiceTest.php
      provides: "D-07 #1 search half — exact-key-set capture assertion on the /search body"
      contains: "array_keys"
  key_links:
    - from: app/Livewire/Pages/Student/AcademicPaperIndex.php
      to: app/Services/AvailabilityService.php
      via: "a #[Computed] availability map combines paginator ids + hybrid ids into one forPapers() call"
      pattern: "forPapers"
    - from: resources/views/livewire/pages/student/academic-paper-index.blade.php
      to: app/Livewire/Pages/Student/AcademicPaperIndex.php
      via: "the three Copies cells read $availability[$paper->id] with a '0 of 0' fallback"
      pattern: "of "
---

<objective>
Build `AvailabilityService::forPapers(array $ids): array` (D-01/D-02 — the grouped `whereIn` hydration, single source of truth, `checked_at = now()` never persisted) and wire it into the student search page (D-03/D-04/D-05): a `#[Computed] availability` map on `AcademicPaperIndex` combining the SQL-paginator ids and the hybrid-result ids into one grouped call, the three card Copies cells upgrading from `{{ $paper->available_copies }} available` to "X of Y available" + the static "Checked just now" caption, and the D-07 test proofs #1 (search-side capture), #4 (hydrator unit test) and #5 (search-page render test). The chat-chip half of hydration (D-03 call site #1, D-04 chip suffix) lands in 10-05 on top of this service.

Purpose: SEARCH-02's "live availability status, copies available/total, sourced from Inventory, never from the LLM" — the counts resolve from `inventory` rows per render; the sidecar payloads can never carry them (10-01 + capture tests).

Output: Service + search-page hydration + green AvailabilityServiceTest/AiServiceTest/AcademicPaperIndexHybridTest. 10-04's recommendations cards consume the same `$availability` computed through the shared card path.

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
@docs/adr/0010-live-availability-hydration-contract.md
@docs/adr/0004-sidecar-chat-endpoint-contract.md
@app/Services/AiService.php
@app/Models/AcademicPaper.php
@app/Models/Inventory.php
@app/Livewire/Pages/Student/AcademicPaperIndex.php
@resources/views/livewire/pages/student/academic-paper-index.blade.php
@tests/Feature/AcademicPaperIndexHybridTest.php
@tests/Feature/AiServiceTest.php
@tests/fixtures/ai-sidecar/search.json
</context>

<threat_model>
ASVS L1. Block on HIGH severity threats. This plan adds a DB-read hydration service and changes what the search page displays.

| Threat | Severity | Mitigation in this plan |
|---|---|---|
| T-01 Availability value reaches the sidecar payload — the never-LLM guarantee breaks | HIGH | No service/component code touches the request bodies; the `#[Computed] availability` map is built post-search from Inventory rows. Enforced by the strengthened capture assertion (`array_keys($request->data()) === ['query','filters','corpus','limit','k']` + `! array_key_exists('available', ...)`) in AiServiceTest and the page-level render test's capture closure, plus the sidecar 422 enforcement from 10-01. |
| T-02 Per-row hydration reintroduces N+1 (the per-row `getAvailableCopiesCountAttribute` accessors exist at `AcademicPaper.php:73-82`) | MED | The only query is one `whereIn` + `groupBy` inside `forPapers()`; the computed gathers ALL page ids into one call. AvailabilityServiceTest has no per-row call path to pass. |
| T-03 Wrong copy semantics leak into `total`/`available` (e.g. treating `Reserved` as available, or using `logical_copies_count`) | MED | `available` counts `status = 'Available'` only (`Inventory::isAvailable()` semantics, `Inventory.php:56-59`); `total` counts all `inventory` rows for the paper; `logical_copies_count` (`max('copy_number')`, `AcademicPaper.php:85-88`) is never used. Proven by the unit test's mixed-status assertions. |
| T-04 Stale counts rendered (caching the availability map) | MED | The map is a plain `#[Computed]` (recomputed per render) — NOT `#[Computed(persist: true, cache: true)]` (reserved for static filter options at `AcademicPaperIndex.php:174,190,201`). |

No HIGH-severity threat is left without a mitigation — nothing blocks this plan.
</threat_model>

<tasks>

<task type="auto">
  <name>Task 1: AvailabilityService::forPapers() grouped hydration</name>
  <files>app/Services/AvailabilityService.php</files>
  <read_first>app/Services/AiService.php (service conventions: plain class, no DI container, `new` instantiation, public consts), app/Models/Inventory.php (status semantics, isAvailable() at 56-59), app/Models/AcademicPaper.php (getTotalCopiesCountAttribute at 73-82 — semantics to match, NOT the per-row query), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.1)</read_first>
  <action>
  - Create `app/Services/AvailabilityService.php` (namespace `App\Services`), plain class — no DI container, no constructor dependencies, mirroring `AiService` conventions.
  - Method `public function forPapers(array $ids): array`:
    - Normalize ids: `$ids = collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();` — early-return `[]` when empty (no query issued).
    - One grouped query per D-02/ADR 0010 verbatim: `Inventory::whereIn('academic_paper_id', $ids)->selectRaw('academic_paper_id, COUNT(*) AS total, SUM(CASE WHEN status = "Available" THEN 1 ELSE 0 END) AS available')->groupBy('academic_paper_id')` — cast `available`/`total` to int, key the result by `academic_paper_id` (int).
    - Each entry: `['available' => (int), 'total' => (int), 'checked_at' => now()]` — `checked_at` captured once per call (fetch time), never written anywhere (D-01/D-05).
    - Papers with zero Inventory rows are absent from the map (callers use the `?? ['available' => 0, 'total' => 0]` fallback).
    - Do NOT touch `academic_papers.status`; do NOT read `BorrowTransaction` (inventory.status is the single source of truth — RESEARCH.md section 1.1).
  </action>
  <verify>php -l app/Services/AvailabilityService.php</verify>
  <acceptance_criteria>
  - File exists; `forPapers(array $ids): array` returns `[int id => ['available' => int, 'total' => int, 'checked_at' => \Illuminate\Support\Carbon]]`
  - The body contains the exact `whereIn('academic_paper_id', $ids)` + `selectRaw('academic_paper_id, COUNT(*) AS total, SUM(CASE WHEN status = "Available" THEN 1 ELSE 0 END) AS available')` + `groupBy('academic_paper_id')` chain
  - `forPapers([])` returns `[]` without issuing a query (assert via DB query listener in Task 2's test)
  - `php -l` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 2: AvailabilityServiceTest unit suite (D-07 #4)</name>
  <files>tests/Unit/AvailabilityServiceTest.php</files>
  <read_first>tests/Unit/AcademicPaperTest.php (Unit style: plain test_* methods, namespace Tests\Unit), tests/TestCase.php (RefreshDatabase + in-memory SQLite already provided at 10-42), database/factories/InventoryFactory.php (statuses Available/Reserved/Unavailable), database/factories/AcademicPaperFactory.php (requires seeded Dean/ResearchAdviser/TechnicalAdviser — replicate the hand-seeding from AcademicPaperIndexHybridTest.php:37-71), app/Services/AvailabilityService.php (Task 1 state)</read_first>
  <action>
  - Create `tests/Unit/AvailabilityServiceTest.php` (`namespace Tests\Unit`, `use RefreshDatabase;` if not inherited — verify against tests/TestCase.php).
  - `test_hydrates_mixed_copy_statuses`: three papers (seed the three advisers, then `AcademicPaper::factory()->create()`), each with explicit `Inventory::factory()->create(['academic_paper_id' => $p->id, 'status' => 'X'])` rows — paper A: 2 Available + 1 Reserved + 1 Unavailable (expect available 2, total 4); paper B: 0 Available + 1 Unavailable (expect 0/1); paper C: 3 Available (expect 3/3). Assert exact `['available', 'total']` per id.
  - `test_checked_at_is_now`: for the same fixture, assert `now()->diffInSeconds($result[$id]['checked_at']) < 5`.
  - `test_omits_papers_without_inventory_rows`: paper with zero copies → key absent from the map.
  - `test_empty_ids_returns_empty_array_without_query`: call `forPapers([])` inside a DB query-count capture (e.g. `DB::listen` counter) → `[]` and zero queries executed.
  - `test_never_persists_checked_at`: after the call, assert no `inventory` row's columns were modified (reload rows, `assertSame` original attributes) and the `checked_at` value is not recoverable from the DB.
  </action>
  <verify>php artisan test --filter=AvailabilityServiceTest</verify>
  <acceptance_criteria>
  - All 5 tests pass: `php artisan test --filter=AvailabilityServiceTest` exits 0
  - The mixed-status test asserts exact ints (2/4, 0/1, 3/3) — `Reserved` and `Unavailable` never count toward `available`
  - The freshness test asserts `diffInSeconds < 5`; the no-copies test asserts key absence; the empty-ids test asserts zero queries
  - `php -l tests/Unit/AvailabilityServiceTest.php` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 3: #[Computed] availability map in AcademicPaperIndex (paginator + hybrid ids, one call)</name>
  <files>app/Livewire/Pages/Student/AcademicPaperIndex.php</files>
  <read_first>app/Livewire/Pages/Student/AcademicPaperIndex.php (academicPapers computed at 99-172 — the paginator at 162; hybridResults/runHybridSearch at 280-333; #[Computed] conventions), app/Services/AvailabilityService.php (Task 1 state), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.1 call-sites)</read_first>
  <action>
  - Add `use App\Services\AvailabilityService;` to the imports.
  - Add `#[Computed] public function availability(): array` (plain computed — NOT persist/cache):
    - Collect ids: `collect($this->academicPapers->pluck('id'))->merge(collect($this->hybridResults ?? [])->pluck('id'))` → `->unique()->values()->all()`.
    - `return (new AvailabilityService)->forPapers($ids);`
    - Do NOT remove or alter the existing `withCount(['copies as available_copies' => ...])` at lines 150-154 and 310-315 — it stays for `orderBy('status')` (156-157), the status filter (138-148), and the `$paper->status` transform (166, 325).
    - Do NOT modify `runHybridSearch()`, the hooks, or the mode properties in this task (recommendations-mode wiring is 10-04; that plan extends this computed with recommendation ids).
  </action>
  <verify>php -l app/Livewire/Pages/Student/AcademicPaperIndex.php</verify>
  <acceptance_criteria>
  - `AcademicPaperIndex.php` contains `#[Computed] public function availability(): array` that merges `$this->academicPapers` paginator ids + `$this->hybridResults` ids into one `forPapers($ids)` call
  - No `#[Computed(persist: true, cache: true)]` appears above `availability`
  - The existing `withCount(['copies as available_copies' ...])` blocks at lines 150-154 and 310-315 are unchanged
  - `php -l` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 4: Blade — 'X of Y available' + 'Checked just now' in the three card Copies cells</name>
  <files>resources/views/livewire/pages/student/academic-paper-index.blade.php</files>
  <read_first>resources/views/livewire/pages/student/academic-paper-index.blade.php (the three Copies cells at 90-93 (mobile hybrid), 159-162 (mobile SQL), 262-265 (desktop hybrid); existing caption styling `text-xs text-base-content/50`), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.4 X-of-Y markup)</read_first>
  <action>
  - Replace the contents of the Copies cell `<div>` at each of the three spots (lines 90-93, 159-162, 262-265):
    - Line `{{ $paper->available_copies }} available` → `<p class="font-medium">{{ ($availability[$paper->id]['available'] ?? 0) }} of {{ ($availability[$paper->id]['total'] ?? 0) }} available</p>`
    - Append below it: `<p class="text-xs text-base-content/50">Checked just now</p>` (the static muted caption, D-05 — never render the `checked_at` value itself).
    - The `Copies` label `<p class="text-base-content/50 font-medium mb-1">Copies</p>` stays untouched.
  - Do NOT touch the status badges, the `x-mary-table` status cell (343-347), or the detail modal in this task.
  </action>
  <verify>php artisan view:cache</verify>
  <acceptance_criteria>
  - All three Copies cells contain the literal `{{ ($availability[$paper->id]['available'] ?? 0) }} of {{ ($availability[$paper->id]['total'] ?? 0) }} available` and the literal `Checked just now`
  - No occurrence of `{{ $paper->available_copies }} available` remains in the file
  - No `checked_at` value is echoed anywhere in the view (grep the file for `checked_at`)
  - `php artisan view:cache` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 5: D-07 #1 search-half — exact-key-set capture assertion in AiServiceTest</name>
  <files>tests/Feature/AiServiceTest.php</files>
  <read_first>tests/Feature/AiServiceTest.php (the Http::assertSent closure at 33-41 — current partial assertions), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.8)</read_first>
  <action>
  - Extend the existing `/search` `Http::assertSent` closure at lines 33-41 with two more conjuncts: `&& array_keys($request->data()) === ['query', 'filters', 'corpus', 'limit', 'k']` and `&& ! array_key_exists('available', $request->data())`.
  - Add a second conjunct to the SAME closure (or a new sibling test `it_sends_exactly_the_adr_0004_search_keys`) asserting `! array_key_exists('total', $request->data())` and `! array_key_exists('checked_at', $request->data())` — proving no availability field of any name ships.
  - Do NOT weaken the existing query/filters/corpus/limit/k value assertions.
  </action>
  <verify>php artisan test --filter=AiServiceTest</verify>
  <acceptance_criteria>
  - The `/search` capture closure now asserts the exact key set `['query', 'filters', 'corpus', 'limit', 'k']` and the absence of `available`, `total`, `checked_at` keys
  - `php artisan test --filter=AiServiceTest` exits 0 (all existing tests still pass with the strengthened closure)
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 6: D-07 #5 search-page render tests — numbers on screen, none in the payload</name>
  <files>tests/Feature/AcademicPaperIndexHybridTest.php</files>
  <read_first>tests/Feature/AcademicPaperIndexHybridTest.php (seedPapers/forceFill id alignment at 37-71, twoResultSearch at 73-96, Livewire::test + Http::fake conventions, the Inventory seeding pattern at 187-191), tests/fixtures/ai-sidecar/search.json (paper-77 shape), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.9)</read_first>
  <action>
  - Extend `tests/Feature/AcademicPaperIndexHybridTest.php` (same actingAs/`config(['services.ai_sidecar.token' => 'test-token'])`/`Http::preventStrayRequests()` discipline as the existing tests).
  - New test `it_renders_hydrated_availability_on_hybrid_cards`: seed papers 77 + 78 (`forceFill(['id' => ...])`); Inventory rows — paper 77: 2 Available + 1 Unavailable (expect "2 of 3 available"); paper 78: 0 Available + 2 Unavailable (expect "0 of 2 available"); fake `/search` with `twoResultSearch()`; `Livewire::test(AcademicPaperIndex::class)->set('search', 'water pump')->call('runHybridSearch')` → `assertSee('2 of 3 available')`, `assertSee('0 of 2 available')`, `assertSee('Checked just now')` (mobile hybrid spot at a default viewport); `Http::assertSent(fn ($r) => str_contains($r->url(), '/search') && ! array_key_exists('available', $r->data()) && ! array_key_exists('total', $r->data()))`.
  - New test `it_renders_hydrated_availability_on_sql_cards`: no sidecar fake needed; same inventory seeding; plain `Livewire::test(AcademicPaperIndex::class)` (search page default SQL path) → `assertSee('2 of 3 available')` + `assertSee('Checked just now')` + `Http::assertNothingSent()`.
  - New test `it_renders_zero_of_zero_for_papers_without_inventory_rows`: paper 77 with no Inventory rows → `assertSee('0 of 0 available')` (the `??` fallback path).
  </action>
  <verify>php artisan test --filter=AcademicPaperIndexHybridTest</verify>
  <acceptance_criteria>
  - All 3 new tests pass and all pre-existing hybrid tests still pass: `php artisan test --filter=AcademicPaperIndexHybridTest` exits 0
  - The hybrid render test asserts the literal strings `2 of 3 available`, `0 of 2 available`, `Checked just now` AND proves via the capture closure that the `/search` request data contains no `available`/`total` keys (D-07 #5)
  - The SQL render test asserts `Http::assertNothingSent()`
  </acceptance_criteria>
</task>

</tasks>

<verification>
- [ ] `php artisan test --filter=AvailabilityServiceTest` — 5 passing
- [ ] `php artisan test --filter=AiServiceTest` — all passing with the strengthened exact-key closure
- [ ] `php artisan test --filter=AcademicPaperIndexHybridTest` — all passing (existing + 3 new)
- [ ] `php artisan test` — full Laravel suite green (557 passed / 3 skipped baseline)
- [ ] `vendor/bin/pint` scoped to the touched files only: `vendor/bin/pint app/Services/AvailabilityService.php app/Livewire/Pages/Student/AcademicPaperIndex.php resources/views/livewire/pages/student/academic-paper-index.blade.php tests/Unit/AvailabilityServiceTest.php tests/Feature/AiServiceTest.php tests/Feature/AcademicPaperIndexHybridTest.php`
</verification>

<success_criteria>
- All 6 tasks complete
- SEARCH-02 search-page half: result cards show live "X of Y available" + "Checked just now" resolved from Inventory in one grouped query — never in the sidecar payload (capture-proven), never from the model
- D-07 proofs #1 (search side), #4 and #5 (search page) green; #3 is proven by 10-01; #1/#2 chat side by 10-05
</success_criteria>

<output>
After completion, create `.planning/phases/10-live-availability-similar-book-recommendations/10-02-SUMMARY.md`
</output>
