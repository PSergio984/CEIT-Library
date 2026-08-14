---
phase: 10-live-availability-similar-book-recommendations
plan: 03
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Services/SimilarPapersService.php
  - tests/Feature/SimilarPapersServiceTest.php
autonomous: true
requirements: [SEARCH-06]
must_haves:
  truths:
    - "SimilarPapersService::for(AcademicPaper $paper, int $limit = 10) calls AiService::search($paper->title, [], 'catalog', $limit) verbatim — query built from the title ONLY, filters = [] (no metadata filters), k stays at 60 via AiService::RRF_CANDIDATES (D-08/D-09/D-10, ADR 0011)"
    - "The seed paper's paper-N id is dropped client-side before findMany (reject on the mapped int id); the returned Collection preserves sidecar rank order via keyBy + re-map (D-11/D-12)"
    - "Fail-closed with no fallback: AiServiceUnavailableException|AiServiceAuthException → empty Collection + unavailable flag true; empty retrieval or self-exclusion-emptied → empty Collection + flag false; no SQL fallback, no LLM fallback (D-13, SEARCH-06 deterministic list)"
  artifacts:
    - path: app/Services/SimilarPapersService.php
      provides: "New service: for(AcademicPaper $paper, int $limit = 10): Collection — title-as-query mechanism with self-exclusion + fail-closed flag"
      contains: "unavailable"
    - path: tests/Feature/SimilarPapersServiceTest.php
      provides: "SEARCH-06 validation — verbatim title query, self-exclusion + rank order, fail-closed (down/auth/empty/self-emptied), determinism"
      contains: "it_excludes_the_seed_paper"
  key_links:
    - from: app/Services/SimilarPapersService.php
      to: app/Services/AiService.php
      via: "for() consumes search() with title-only query — the same deterministic /search the results page uses (RRF k=60)"
      pattern: "search("
---

<objective>
Build the ADR 0011 similar-books mechanism as a Laravel service (D-08..D-13): `SimilarPapersService::for(AcademicPaper $paper, int $limit = 10): Collection<AcademicPaper>` — builds the query from the paper's title only, calls `AiService::search($title, [], 'catalog', $limit)` verbatim (k at the 60-candidate RRF pool), drops the seed paper's id client-side, loads models in sidecar rank order with the same `withCount`/status transform the search page uses, and fails closed (empty Collection + `unavailable` flag on sidecar down/auth; empty Collection + flag false on empty/self-exclusion-emptied — no SQL fallback, no LLM fallback). Ship the full `SimilarPapersServiceTest` suite: verbatim-query capture, self-exclusion + rank order, fail-closed for all four empty paths, and cross-call determinism.

Purpose: SEARCH-06's "books similar to X" is a deterministic ranked list reusing the existing sidecar `/search` — no new endpoint, no LLM, no metadata filters. The UI (10-04) and any future chat interception consume this service; its output shape (rank-ordered `AcademicPaper` Collection) feeds the shared card path and the ADR 0010 hydrator.

Output: Service + green SimilarPapersServiceTest. The component wiring (`showSimilar`, recommendations mode, snapshot/restore) lands in 10-04 on top of this.

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
@docs/adr/0011-similar-books-mechanism.md
@docs/adr/0004-sidecar-chat-endpoint-contract.md
@app/Services/AiService.php
@app/Livewire/Pages/Student/AcademicPaperIndex.php
@tests/Feature/AiServiceTest.php
@tests/Feature/AcademicPaperIndexHybridTest.php
@tests/fixtures/ai-sidecar/search.json
</context>

<threat_model>
ASVS L1. Block on HIGH severity threats. This plan adds an outbound sidecar call whose input is derived from catalog data.

| Threat | Severity | Mitigation in this plan |
|---|---|---|
| T-01 Fail-open on sidecar failure — misleading "no similar books" vs an error state | MED | Typed exceptions (`AiServiceUnavailableException|AiServiceAuthException`) are caught and converted to empty Collection + `unavailable === true`; the caller (10-04) renders the "Recommendations unavailable right now" banner from the flag. No exception escapes `for()`. Proven by the down/auth fail-closed tests. |
| T-02 Query injection / malformed input into the sidecar call | LOW | `$paper->title` is corpus-exporter-sourced DB text, not user input; it travels as a JSON `query` string through `Http` (no URL concatenation). The verbatim-capture test pins the exact body. |
| T-03 Self-exclusion bypass — the seed paper appears in its own recommendations | MED | The seed id is rejected from the mapped int-id list BEFORE `findMany`; the self-exclusion test asserts the seed id is absent and the remaining order matches the sidecar rank. |
| T-04 Rank scramble — `findMany` DB order returned instead of sidecar order (determinism break) | MED | `findMany` result is keyed by id then re-mapped over the ordered id list (the `AcademicPaperIndex.php:318-330` pattern); the determinism test asserts identical ordered id lists across two calls. |
| T-05 Unbounded limit from the caller | LOW | `for()` accepts `int $limit = 10`; the sidecar caps `limit` via the `search()` envelope — the 10-04 caller always passes the default 10. No upper-bound check added (matches `runHybridSearch`'s fixed 10). |

No HIGH-severity threat is left without a mitigation — nothing blocks this plan.
</threat_model>

<tasks>

<task type="auto">
  <name>Task 1: SimilarPapersService::for() — title query, verbatim search, self-exclusion, rank-preserving load, fail-closed</name>
  <files>app/Services/SimilarPapersService.php</files>
  <read_first>app/Services/AiService.php (search() signature at 27-36, RRF_CANDIDATES = 60 at line 19, typed exceptions), app/Livewire/Pages/Student/AcademicPaperIndex.php (the full runHybridSearch pipeline at 280-333 — id mapping at 305-308, findMany + keyBy re-key at 310-330, status transform at 324-328; the aiSearchFailed flag idiom at line 60), app/Models/AcademicPaper.php, .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.2)</read_first>
  <action>
  - Create `app/Services/SimilarPapersService.php` (namespace `App\Services`), plain class with `use App\Models\AcademicPaper; use App\Services\AiService;` — no DI container, mirroring `AiService` conventions.
  - Public property `public bool $unavailable = false;` (the fail-closed flag, mirroring the `aiSearchFailed` idiom).
  - Method `public function for(AcademicPaper $paper, int $limit = 10): \Illuminate\Support\Collection`:
    1. Reset the flag at entry: `$this->unavailable = false;`.
    2. `try { $results = (new AiService)->search($paper->title, [], 'catalog', $limit)['results']; } catch (AiServiceUnavailableException|AiServiceAuthException) { $this->unavailable = true; return collect(); }` — title ONLY (D-09: no authors/advisers/department terms), `filters = []` (D-10), corpus `'catalog'`, k stays 60 via `AiService::RRF_CANDIDATES` (D-08).
    3. Map `paper-N` ids: `collect($results)->map(fn ($r) => (int) str_replace('paper-', '', $r['id']))->filter()`.
    4. Self-exclusion: `->reject(fn ($id) => $id === $paper->id)->values()` (D-11 — drop the seed BEFORE model loading; the RRF pool of 60 absorbs the wasted rank slot; zero sidecar change).
    5. When the id list is empty → `return collect();` with flag still false (D-13 empty/self-exclusion-emptied).
    6. Load models: `AcademicPaper::with(['authors:id,name', 'copies:id,academic_paper_id,status'])->withCount(['copies as available_copies' => fn ($q) => $q->where('status', 'Available')])->findMany($ids)`.
    7. Re-key and re-map over the ordered id list (D-12): `$byId = $papers->keyBy('id'); return collect($ids)->map(fn ($id) => $byId->get($id))->filter()->map(fn ($p) => tap($p, fn ($p) => $p->status = $p->available_copies > 0 ? 'Available' : 'Unavailable'))->values();` — sidecar rank order preserved, same `AcademicPaper` shape the search page renders, status transform applied for badges.
    - No SQL fallback, no LLM fallback anywhere in the class (D-13).
  </action>
  <verify>php -l app/Services/SimilarPapersService.php</verify>
  <acceptance_criteria>
  - `for(AcademicPaper $paper, int $limit = 10)` exists and calls `(new AiService)->search($paper->title, [], 'catalog', $limit)` with `$paper->title` as the ONLY query term and `[]` filters
  - The id pipeline maps `paper-N` → int, rejects `$paper->id` before loading, and re-keys `findMany` results over the ordered id list (rank preserved)
  - `$this->unavailable` is set true ONLY inside the typed-exception catch; empty/self-exclusion-emptied paths return `collect()` with the flag false
  - The file contains no `where(`-based SQL fallback query and no `chatStream`/LLM call
  - `php -l` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 2: SimilarPapersServiceTest — verbatim title query + exact key set</name>
  <files>tests/Feature/SimilarPapersServiceTest.php</files>
  <read_first>tests/Feature/AcademicPaperIndexHybridTest.php (seedPapers/forceFill id alignment at 37-71, twoResultSearch at 73-96), tests/Feature/AiServiceTest.php (Http::assertSent capture style at 33-41), tests/fixtures/ai-sidecar/search.json (paper-77 title), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.9)</read_first>
  <action>
  - Create `tests/Feature/SimilarPapersServiceTest.php` (PHPUnit `#[Test]` attributes + snake_case methods, `RefreshDatabase`, `Http::preventStrayRequests()` in every test). Seed the three advisers then `AcademicPaper::factory()` with `forceFill(['id' => 77])` to align with the `search.json` fixture (replicate `seedPapers()` at lines 37-71).
  - `it_queries_with_title_only_verbatim`: `config(['services.ai_sidecar.token' => 'test-token'])`; `Http::fake(['http://127.0.0.1:8310/search' => Http::response($this->fixture, 200)])` with the `search.json` fixture; `(new SimilarPapersService)->for($paper77)` → `Http::assertSent(fn ($r) => str_contains($r->url(), '/search') && $r->hasHeader('X-Sidecar-Token', 'test-token') && $r['query'] === $paper77->title && $r['filters'] === [] && $r['corpus'] === 'catalog' && $r['limit'] === 10 && $r['k'] === 60 && array_keys($r->data()) === ['query', 'filters', 'corpus', 'limit', 'k'])` — proving title-only, no metadata filters, exact ADR 0004 key set (D-07 cross-cutting never-LLM).
  </action>
  <verify>php artisan test --filter=SimilarPapersServiceTest</verify>
  <acceptance_criteria>
  - `it_queries_with_title_only_verbatim` passes with the capture asserting `$r['query'] === $paper77->title`, `$r['filters'] === []`, `$r['corpus'] === 'catalog'`, `$r['limit'] === 10`, `$r['k'] === 60`, and the exact key set
  - `Http::preventStrayRequests()` is present in the test; no real API key value anywhere
  - `php artisan test --filter=SimilarPapersServiceTest` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 3: SimilarPapersServiceTest — self-exclusion, rank order, determinism</name>
  <files>tests/Feature/SimilarPapersServiceTest.php</files>
  <read_first>tests/Feature/AcademicPaperIndexHybridTest.php (twoResultSearch extension pattern at 73-96 — appends paper-78 for rank assertions), app/Services/SimilarPapersService.php (Task 1 state), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 3.7 fixture-ids pattern)</read_first>
  <action>
  - Extend the fixture: seed papers 77 AND 78 (`forceFill` ids), and build a `twoResultSearch()` analog whose results array lists `paper-77` first then `paper-78` (mirror the hybrid test's extension at 73-96).
  - `it_excludes_the_seed_paper_and_keeps_rank_order`: `for($paper77)` → `$result->pluck('id')->all() === [78]` and `$service->unavailable === false`; assert the seed id 77 is absent.
  - `it_is_deterministic_across_calls`: call `for($paper77)` twice (fresh `Http::fake` response each time, same fixture) → `assertSame($first->pluck('id')->all(), $second->pluck('id')->all())` — identical ordered id lists (ADR 0006/0011 determinism through the Laravel mapping).
  - `it_returns_empty_when_only_the_seed_matches`: fixture whose results contain only `paper-77` → `for($paper77)` returns an empty Collection with `unavailable === false` (self-exclusion-emptied, D-13).
  - `it_returns_empty_collection_for_empty_retrieval`: fixture `{"query": "...", "total": 0, "results": []}` → empty Collection, flag false (D-13).
  </action>
  <verify>php artisan test --filter=SimilarPapersServiceTest</verify>
  <acceptance_criteria>
  - `it_excludes_the_seed_paper_and_keeps_rank_order` asserts the exact ordered id list with 77 dropped
  - `it_is_deterministic_across_calls` uses `assertSame` on the two ordered id lists
  - The two empty paths assert `unavailable === false` and an empty Collection
  - `php artisan test --filter=SimilarPapersServiceTest` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 4: SimilarPapersServiceTest — fail-closed on sidecar down and auth</name>
  <files>tests/Feature/SimilarPapersServiceTest.php</files>
  <read_first>tests/Feature/AcademicPaperIndexHybridTest.php (the ConnectionException fake at line 149), tests/Feature/AiServiceTest.php (401/connection exception assertions at 60-93), app/Services/SimilarPapersService.php (Task 1 state)</read_first>
  <action>
  - `it_fails_closed_when_sidecar_is_down`: `Http::fake(['http://127.0.0.1:8310/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection refused')])` → `$service = new SimilarPapersService; $result = $service->for($paper77);` assert `$result->isEmpty() === true` and `$service->unavailable === true` — no exception escapes `for()` (D-13).
  - `it_fails_closed_on_auth_failure`: fake 401 `Http::response(['error' => ['code' => 'auth_failed', 'message' => 'missing or invalid X-Sidecar-Token']], 401)` → empty Collection + `unavailable === true`.
  </action>
  <verify>php artisan test --filter=SimilarPapersServiceTest</verify>
  <acceptance_criteria>
  - Both fail-closed tests assert `isEmpty()` and `unavailable === true` without the exception propagating to the test
  - `php artisan test --filter=SimilarPapersServiceTest` exits 0
  </acceptance_criteria>
</task>

</tasks>

<verification>
- [ ] `php artisan test --filter=SimilarPapersServiceTest` — all 7 tests passing (1 verbatim + 4 self-exclusion/rank/empty + 2 fail-closed = 7)
- [ ] `php artisan test` — full Laravel suite green (557 passed / 3 skipped baseline)
- [ ] `vendor/bin/pint app/Services/SimilarPapersService.php tests/Feature/SimilarPapersServiceTest.php` — exits 0, no other files touched
</verification>

<success_criteria>
- All 4 tasks complete
- SEARCH-06 mechanism proven: title-as-query verbatim (exact 5-key payload, no metadata filters), seed self-excluded with sidecar rank order intact, deterministic across calls, and fail-closed on every empty/down path with the `unavailable` flag
- The service returns the same `AcademicPaper` Collection shape the search page renders — ready for 10-04's shared card path and ADR 0010 hydration
</success_criteria>

<output>
After completion, create `.planning/phases/10-live-availability-similar-book-recommendations/10-03-SUMMARY.md`
</output>
