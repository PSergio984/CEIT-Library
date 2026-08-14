# Phase 10 Research: Live Availability & Similar-Book Recommendations

**Researched:** 2026-08-14 | **Source of truth:** `10-CONTEXT.md` (D-01..D-18, locked), ADRs 0010-0012, 0004, 0006

---

## 1. Verified facts (with file:line references)

### 1.1 Availability today (SEARCH-02 baseline)

- The student search page already counts availability per paper via `withCount`:
  - SQL path: `AcademicPaperIndex::academicPapers` — `withCount(['copies as available_copies' => fn ($q) => $q->where('status', 'Available')])` at `app/Livewire/Pages/Student/AcademicPaperIndex.php:150-154`.
  - Hybrid path: the same `withCount` is repeated inline in `runHybridSearch()` at `AcademicPaperIndex.php:310-315`.
- Display is `{{ $paper->available_copies }} available` in exactly **three spots** (all in `resources/views/livewire/pages/student/academic-paper-index.blade.php`):
  - Mobile hybrid card: line 92
  - Mobile SQL card: line 161
  - Desktop hybrid card: line 264
- The desktop SQL table has **no copies column** — only a status badge (`cell_status` scope, lines 343-347) and the `actions` scope (lines 349-371) which today holds a single View button.
- The detail modal (`resources/views/components/academic-paper-detail-modal.blade.php`) renders a per-copy status table (lines 95-300) — it has no "X of Y available" line today.
- Status semantics (verified):
  - `Inventory::isAvailable()` = `status === 'Available'` (`app/Models/Inventory.php:56-59`); `isReserved()`/`isUnavailable()` exist (lines 64-75).
  - `InventoryFactory` statuses: `['Available', 'Reserved', 'Unavailable']` (`database/factories/InventoryFactory.php:16`).
  - **`Reserved` is never written to `inventory.status` anywhere in `app/`** — grep for `'Reserved'` finds only `Inventory::isReserved()` and `BorrowTransaction.php:268`, which updates the **`academic_papers.status` column** (`$this->academicPaper->update(['status' => 'Reserved'])`), not the copy row. The copy lifecycle is: `BorrowService::confirmBorrow` sets `inventory.status = 'Unavailable'` on borrow (`app/Services/BorrowService.php:208`, inside a `lockForUpdate` + transaction), and `BorrowService::handleReturn` sets it back to `'Available'` (line 139). So **BorrowTransaction is the writer; `inventory.status` is the single source of truth for availability** — no extra BorrowTransaction read is needed for the hydrator (D-01's "Reserved/Unavailable never count" matches app semantics).
  - `AcademicPaper::getAvailableCopiesCountAttribute()` / `getTotalCopiesCountAttribute()` exist (`app/Models/AcademicPaper.php:73-82`) but are **per-row N+1 accessors** (each does its own `copies()` query) — the hydrator must not use them per-row (D-02's grouped `whereIn` is the replacement).
  - `getLogicalCopiesCountAttribute()` (`max('copy_number')`, line 85-88) is a **different** concept (used by the modal's "Available Copies / N copy(s)" header, detail-modal lines 99-101) — do not confuse with `total` (= count of copy rows).
- Status filter force-exits hybrid mode: `updatedStatusFilter()` at `AcademicPaperIndex.php:223-230` calls `exitHybridMode()` (lines 339-343); `runHybridSearch()` guards `strlen(trim($this->search)) < 3 || $this->statusFilter !== ''` (line 282). Filter applied at query level: `whereHas`/`whereDoesntHave` on `copies.status = 'Available'` (lines 138-148).
- `aiSearchFailed` fallback flag: property line 60; set in `runHybridSearch()` catch of `AiServiceUnavailableException|AiServiceAuthException` (lines 298-303); cleared on success (line 332); banner rendered at blade lines 38-45 (`alert alert-warning text-sm mb-4` + inline SVG).
- `$paper->status` is a **computed, non-persisted attribute** set by transform: `$paper->status = $paper->available_copies > 0 ? 'Available' : 'Unavailable'` (SQL path line 166, hybrid path line 325). Badges read `$paper->status`.

### 1.2 Citation chip rendering (SEARCH-02 chat side)

- `resources/views/livewire/chat-widget-citations.blade.php` (14 lines): catalog chips are `[{{ $c['n'] }}] {{ $c['title'] }}` + a `font-mono` `catalog_code` span (lines 3-7, `rounded-full border border-primary/40` link chips); policy chips are non-link (lines 9-11).
- Flow: `ChatWidget::streamQuestion()` builds `$citations = $this->companionCitations($question)` (line 152) → `assistantBubble($accumulated, $citations)` (line 175) → the bubble array is **persisted into `ai_messages.citations`** (`Message::create([... 'citations' => $citations])`, line 181). History re-renders via `openConversation()` (lines 47-63) reading `$message->citations`.
- The partial is included from `resources/views/livewire/chat-widget.blade.php:51-54`:
  ```blade
  @if (! empty($m['citations']))
      @include('livewire.chat-widget-citations', ['citations' => $m['citations']])
      @include('livewire.chat-widget-sources', ['citations' => $m['citations']])
  @endif
  ```
  It is included per message — so a render-time enrichment map keyed by `catalog_code` (or paper id) can be passed as an extra variable **without touching the persisted payload** (D-04).
- Payload shape from `companionCitations()` (`ChatWidget.php:234-260`): exactly `{n, id, corpus, title, url, catalog_code}` — `n` = 1-based, `url`/`catalog_code` from `result['metadata']`. Ids are `paper-N` strings; catalog_code can be parsed from `metadata` but the chip renderer gets the persisted payload only — the enrichment must map via `catalog_code` (persisted) or re-derive `paper-N` id → `AvailabilityService::forPapers([int])` needs the int id (`str_replace('paper-', '', $c['id'])`, same pattern as `AcademicPaperIndex.php:306`).
- `chat-widget-sources.blade.php` (the numbered Sources list) is separate — D-04 scopes the suffix to the **chips** partial only; sources list unchanged.

### 1.3 Mary UI / DaisyUI conventions (grounds ADR 0012 D-14)

- Stack (verified from `composer.lock` / `composer.json`): **Laravel v13.25.0, Livewire ^4.0 (v4.4.0), PHP ^8.4, `robsontenorio/mary` ^2.0, Volt ^1.7**. (Some repo docs — `.cursor/rules`, `.github/instructions` — still claim v3/v12; they are stale.)
- `x-mary-button` usage on the search page (blade lines 98-106, 167-175, 270-278): props `wire:click`, `class="btn-sm btn-primary gap-2 flex-1"`, `icon="o-eye"`, `label="View Details"`, `spinner`, `wire:loading.attr="disabled"`, `wire:target="showPaperDetails({{ $paper->id }})"`.
- Secondary/outline pattern is `btn-outline` — used with `x-mary-button` across admin views, e.g. `class="btn-outline btn-sm"` (`resources/views/livewire/pages/Admin/active-users-tab.blade.php:14,207`), `class="btn-outline w-full" icon="o-x-mark"` (`admin-borrow-transactions.blade.php:59`), and plain `<button class="btn btn-outline btn-error ...">` (`components/clear-filters-button.blade.php:3`). `btn-outline` is a DaisyUI class passed through the Mary component's `class` prop — no special Mary prop.
- Badges: `badge badge-sm badge-success` / `badge-error` for status (blade 68, 137, 240, 344), `badge badge-sm badge-outline` for catalog_code (71, 140, 243).
- Empty state: `<x-empty-state icon="o-document-magnifying-glass" title="..." message="..." :show-action="false" size="sm|default">` (blades 123-129, 194-209, 296-303, 313-333).
- Warning banner: `<div class="alert alert-warning text-sm mb-4">` + inline heroicon SVG (lines 38-45).
- Loading overlays: `wire:loading.flex` + `wire:target="perPage, search, statusFilter, ..."` localized overlay (52-59, 224-231); full-page `wire:loading.flex wire:target="academicPapers"` spinner (10-16).
- Filters component `academic-paper-filters.blade.php`: search input `wire:model.live.debounce.300ms="search"` (line 49); selects all `wire:model.live` (93, 100, 108, 116, 124) → every change fires the matching `updated*` hook.

### 1.4 Sidecar `/search` contract (SEARCH-06 mechanism)

- `AiService::search(string $query, array $filters = [], ?string $corpus = 'catalog', int $limit = 10): array` (`app/Services/AiService.php:27-36`) POSTs `{query, filters, corpus, limit, k}` with `k = self::RRF_CANDIDATES` (= 60, line 19), `timeout: 10, retries: 2`, token header `X-Sidecar-Token` (`request()`, lines 170-183).
- Typed exceptions: 401 → `AiServiceAuthException`, connection/HTTP failure → `AiServiceUnavailableException` (`throwUnlessOk`, lines 185-196); stream errors → `AiServiceProviderException` (lines 87-140). 422 falls under Unavailable catch-all (ADR 0004).
- Sidecar `app/main.py:87-106` (`/search`): reads `query` (required), `filters`, `corpus`, `limit`, `k`; response `{query, total, took_ms, results}`. Result shape from `app/search.py:153-172`: `{id, corpus, title, score, bm25_rank, semantic_rank, metadata}` with metadata containing `catalog_code, department, publication_year, paper_type, authors, url`.
- Filters applied post-retrieval in `search.py:109-122`: exactly `paper_type`, `department`, `publication_year`, `year_from`, `year_to`. **Confirmed: no `exclude` parameter exists; no availability field exists anywhere in the response.** A catalog-code-exact query pins the matching doc to rank 1 (`CODE_PIN_RE`, `search.py:19, 143-151`).
- Determinism: `rrf_search` is fully deterministic over a fixed index (BM25 ranks + stored vectors + RRF k=60), same query/corpus/k → same ordered set (this is what ADR 0006's companion binding relies on; ADR 0011's recommendation stability relies on the same).
- **Confirmed gap (critical for D-06/D-07 #3):** neither `/search` nor `/chat/stream` currently rejects unknown fields. Both endpoints take a loose `payload: dict` and read known keys, silently ignoring everything else (`main.py:87-106`, `main.py:109-140`). Sidecar tests today cover only: missing query 422 (`tests/test_api.py:79-82`), non-numeric top_k 422, unknown corpus 422 (`tests/test_chat_stream.py:164-183`). There is **no unknown-field 422 test and no validation code to pass it** — D-06's "stray availability field 422s" requires a **new sidecar code change** (strict key check on both endpoints, reusing `_invalid()` at `main.py:52-56`) plus new sidecar tests. This is an implementation change to enforce the existing contract, not a contract change (no new fields).
- Sidecar infra: `127.0.0.1:8310` (`app/config.py` host/port), token middleware `main.py:66-79` (401 `auth_failed`), `_invalid()` 422 helper (`main.py:52-56`), test fixtures: `tests/conftest.py` (deterministic embedder + `build_test_index`), `tests/test_rrf.py` (deterministic 16-dim vectors for hand-computed rank assertions).
- Laravel sidecar config: `config/services.php` `ai_sidecar` (`base_url` = `SIDECAR_URL` default `http://127.0.0.1:8310`, `token` = `SIDECAR_TOKEN`).
- **`Http::toStream()` does not exist in this codebase** (grep: only `$this->stream()` Livewire calls). The ADR 0004 mention is stale; the real implementation is `->withOptions(['stream' => true])` + `$response->resource()` (`AiService.php:178-180, 89`). Not relevant to Phase 10 (search is a plain JSON POST) but worth noting.

### 1.5 Livewire mode-switching (grounds D-15/D-18)

- Hybrid mode is a plain public property: `public ?array $hybridResults = null;` (`AcademicPaperIndex.php:58`) + `public bool $aiSearchFailed = false;` (line 60). View branches on `! is_null($this->hybridResults)` (blade 61, 232) and renders sidecar-ordered cards.
- `exitHybridMode()` (lines 339-343) clears both flags — the exact pattern D-18's "yield on change" needs a third branch: exit recommendations mode too.
- `#[Computed] academicPapers` (lines 99-172) does the SQL query + `->paginate($this->perPage, pageName: 'academic-papers-index')` (line 162); transforms collection to add `status`. Filter-option computeds use `#[Computed(persist: true, cache: true)]` (174, 190, 201).
- Hooks that fire on client updates: `updatedSearch` (217-221: resetPage + runHybridSearch), `updatedStatusFilter` (223-230: resetPage + exitHybridMode), `updatedYearFilter/DepartmentFilter/PaperTypeFilter/YearFromFilter/YearToFilter` (232-260: resetPage + runHybridSearch), `updatingPerPage` (70-73: resetPage), `clearFilters` (263-274: reset + runHybridSearch), `updatedDept` (212-215). **Every one of these is a yield point for recommendations mode (D-18) and each currently runs the normal query path — a recommendations-mode exit must be injected into all of them** (cleanest: a single guard at the top of each `updated*` hook or a shared `leaveRecommendationsMode()` helper called from `exitHybridMode()` + each hook).
- `runHybridSearch()` (280-333) is the full pattern for: build filters array → `(new AiService)->search(...)` → catch typed exceptions → map `paper-N` ids → `findMany` → re-key to sidecar order → transform status. **This is exactly the skeleton `SimilarPapersService::for()` + the recommendations load will reuse.**
- Page is `#[Lazy]` (line 22) with `placeholder()` (494-497); `showPaperDetails()` dispatches Alpine events for the modal (345-349); `requestQr` (367-412) shows how copy-level actions work.

### 1.6 Test patterns

- Laravel tests (PHPUnit attributes style, `tests/Feature/`):
  - `AiServiceTest.php` — payload capture: `Http::assertSent(fn ($request) => ... $request['query'] === 'water pump' && $request['filters'] === [...] && $request['corpus'] === 'catalog' && $request['limit'] === 10 && $request['k'] === 60)` (lines 33-41); auth/connection/500 exception tests (60-93); sanitized-log assertions (96-166); retry test via `Http::sequence()` (169-180).
  - `ChatWidgetTest.php` — `Http::preventStrayRequests()` + `Http::fake([url => response])` (43-48); fixture files under `tests/fixtures/ai-sidecar/` via `fixture()` helper (19-22); body-key assertion incl. `! array_key_exists('corpus', $request->data())` (281-286); **exact citation payload equality** (309-318) — this test pins the 6-key shape D-07 #2 protects; per-URL fakes for `/search` + `/chat/stream` (46-47); `Http::sequence()` for repeat responses (97-99).
  - `AcademicPaperIndexHybridTest.php` — the Phase 10 test home: `seedPapers()` forces `id` 77/78 to align with the `search.json` fixture (`forceFill(['id' => 77])`, lines 67-68); `twoResultSearch()` extends the fixture (73-96); sidecar-order assertion (112-117); filter forwarding (135-139); down-fallback via `fn () => throw new ConnectionException(...)` (149); `Http::assertNothingSent()` (178); status-filter force-exits-hybrid (182-210); stays-out-of-hybrid (213-229).
  - No `AcademicPaperIndexTest.php` — the SQL-path coverage lives in `AcademicPapersTest.php` / `FiltersTest.php`; the hybrid file is the model for new Phase 10 tests.
  - Factories: `AcademicPaperFactory` (needs seeded Dean/ResearchAdviser/TechnicalAdviser — `inRandomOrder()->first()`, lines 33-45; auto-attaches authors after creating); `InventoryFactory` (random status from the three).
- Sidecar tests (pytest, `C:\Users\admin\Herd\ceit-ai-sidecar\tests\`): `test_api.py` (auth 401s, search shape, 422 missing query, rebuild atomicity), `test_chat_stream.py` (SSE framing, refusal-without-LLM-call, 422s at lines 126-183), `test_filters.py`, `test_rrf.py` (deterministic-embedder rank assertions), `test_ingest.py`, `test_rag.py`. **The D-07 #3 unknown-field 422 tests belong in `test_api.py` (for `/search`) and `test_chat_stream.py` (for `/chat/stream`)** — and they will fail today, requiring the strict-validation addition in `main.py`.

### 1.7 Existing similar-ish functionality

- **None.** `grep -i "similar\|recommend"` across `app/` and the sidecar `app/` returns only cosine-"similarity" comments (`search.py:86`, `ingest.py:83`). No `exclude` param, no `/similar` endpoint, no recommendations UI. (Deferred ideas in CONTEXT.md lines 95-104 confirm: chat interception and `/similar` endpoint are deliberately out of scope.)

---

## 2. Contract deltas (what must change vs what exists) — mapped to D-01..D-18

| # | What exists today | What must change | Files touched |
|---|---|---|---|
| D-01 | `withCount` gives only `available_copies`; `total_copies_count` accessor is N+1 | New `AvailabilityService::forPapers(array $ids): array` returning `{id: {available, total, checked_at}}` (total = all Inventory rows, available = `status='Available'`, checked_at = `now()` at fetch, never persisted) | **new** `app/Services/AvailabilityService.php` |
| D-02 | Two duplicated inline `withCount` blocks | One grouped `whereIn` + `selectRaw('academic_paper_id, COUNT(*) AS total, SUM(CASE WHEN status = "Available" ...)')` + `groupBy` inside the service; callers pass ids | new service + call sites |
| D-03 | `withCount` drives both sort/filter AND display | Keep `withCount` in `academicPapers` for `orderBy('status')` (line 156-157) + status filter (138-148); display moves to hydrator. Call sites: search page (paginator ids + hybrid ids + recommendation ids) and chat chips (≤5 catalog ids per message render) | `AcademicPaperIndex.php`, `ChatWidget.php`/partials |
| D-04 | Cards: `{{ $paper->available_copies }} available` ×3; chips: title + catalog_code only | Cards: "X of Y available" in all 3 spots; chips: compact "2/3" suffix beside catalog_code, color cue (green ≥1, gray/red 0); policy chips unchanged; suffix injected at render time without altering persisted payload (extra include variable is cleanest) | blade ×3, `chat-widget-citations.blade.php`, include call at `chat-widget.blade.php:52` |
| D-05 | no timestamp | Static muted caption "Checked just now" on result cards only (chips omit) — `checked_at` value used only by the hydrator unit test (≈ now) | blades |
| D-06 | **Sidecar accepts any payload keys silently** (`main.py:87-106, 109-140`) | Sidecar must 422 on unknown fields on both endpoints (allowed keys `/search`: query,filters,corpus,limit,k; `/chat/stream`: query,mode,corpus,top_k). Hydration stays post-response in Livewire/views only; request bodies never gain availability | **sidecar** `main.py` (+tests) |
| D-07 | Partial: payload tests assert selected keys only; citation payload exact-shape test exists (`ChatWidgetTest.php:309-318`); sidecar 422 tests cover missing/invalid known fields only; no hydrator; render shows `available_copies` | (1) strengthen capture tests to assert exact key sets + no availability key; (2) keep exact 6-key payload assertion, add no-availability-key assertion; (3) new sidecar 422 unknown-field tests (test_api.py + test_chat_stream.py); (4) new hydrator unit test (mixed statuses, checked_at ≈ now); (5) new render-level test showing numbers while captured payloads contain none | see §5 |
| D-08 | `AiService::search` exists verbatim | New `SimilarPapersService::for(AcademicPaper $paper, int $limit = 10)` → builds query from title only → `(new AiService)->search($title, [], 'catalog', 10)` (k stays at 60 via `RRF_CANDIDATES`) | **new** `app/Services/SimilarPapersService.php` |
| D-09 | — | Title only; never authors/advisers/dept terms | new service |
| D-10 | — | No metadata filters passed (filters = `[]`); one-line upgrade path exists in `search()` | new service |
| D-11 | No exclude anywhere (verified) | Client-side: drop seed `paper-N` id from ranked list before `findMany`; keep sidecar rank order | new service |
| D-12 | Hybrid path already produces ordered `AcademicPaper` collection | Same shape; recommendation cards reuse existing card markup + hydrator (D-12/D-04 combined) | new service + blade |
| D-13 | `runHybridSearch` catches typed exceptions → flag; SQL fallback exists for search | **No** SQL/LLM fallback for similar: catch `AiServiceUnavailableException|AiServiceAuthException` → empty + availability flag → "Recommendations unavailable right now" banner; empty/self-exclusion-emptied → empty collection → `x-empty-state` "No similar books found" | new service + component |
| D-14 | Card action row has one View Details button (`flex-1`); table actions scope has one View | Add secondary Similar button (outline, icon) to all four surfaces: mobile SQL card, mobile hybrid card, desktop hybrid card, desktop table `actions` scope. View Details keeps `flex-1` primary; Similar auto-width secondary. Modal deferred | blade (4 spots), possibly a partial to dedupe |
| D-15 | Hybrid-mode precedent (property swap, no URL change) | Recommendations mode: header bar "Showing similar books to: X" + "Back to results"; cards via existing grid; no URL change; back restores search text, filters, hybrid-vs-SQL, pagination page verbatim | `AcademicPaperIndex.php`, blade |
| D-16 | `aiSearchFailed` alert-warning banner + `x-empty-state` patterns exist | Loading overlay "Finding similar books..."; unavailable banner; empty state; all three keep "Back to results" | blade |
| D-17 | Mobile card row exists | No separate flow; header bar stacks with `line-clamp-1` title truncation; back pinned top | blade |
| D-18 | `updated*` hooks run the normal query on every change; status filter exits hybrid | Entering snapshots prior results state; exiting restores verbatim; editing search/filters while in mode exits immediately + runs normal query (abandon snapshot); status filter force-exits hybrid per existing rule; Similar on a recommended card recurses; status badges via hydrated shape; status filter applies to searches only | component hooks |

**Non-negotiable contract boundaries (no change):** sidecar request schema (query/filters/corpus/limit/k, query/mode/corpus/top_k) — only *enforcement* (422) is added; `ai_messages.citations` persisted shape stays exactly 6 keys; no new sidecar endpoint; no LLM involvement anywhere.

---

## 3. Patterns to reuse (concrete code excerpts)

### 3.1 The grouped hydrator query (D-02, ADR 0010 verbatim)

```php
// AvailabilityService::forPapers(array $ids): array
Inventory::whereIn('academic_paper_id', $ids)
    ->selectRaw('academic_paper_id,
        COUNT(*) AS total,
        SUM(CASE WHEN status = "Available" THEN 1 ELSE 0 END) AS available')
    ->groupBy('academic_paper_id');
```

### 3.2 Sidecar result → models, rank preserved (D-11/D-12) — from `AcademicPaperIndex.php:305-330`

```php
$ids = collect($results['results'] ?? [])
    ->map(fn ($result) => (int) str_replace('paper-', '', $result['id']))
    ->filter()
    ->all();

$papers = AcademicPaper::with(['authors:id,name', 'copies:id,academic_paper_id,status'])
    ->withCount(['copies as available_copies' => fn ($q) => $q->where('status', 'Available')])
    ->findMany($ids);

$byId = $papers->keyBy('id');
// Preserve the sidecar rank order (findMany returns DB order).
$this->hybridResults = collect($ids)
    ->map(fn ($id) => $byId->get($id))->filter()
    ->map(function ($paper) {
        $paper->status = $paper->available_copies > 0 ? 'Available' : 'Unavailable';
        return $paper;
    })->values()->all();
```
`SimilarPapersService::for()` = same shape, minus the seed id (`collect($ids)->reject(fn ($id) => $id === $paper->id)`), same `withCount` so status badges + hydrator both work, and the same transform.

### 3.3 Typed-exception fail-closed (D-13) — from `AcademicPaperIndex.php:296-303`

```php
try {
    $results = (new AiService)->search($this->search, $filters, 'catalog', 10);
} catch (AiServiceUnavailableException|AiServiceAuthException) {
    $this->hybridResults = null;
    $this->aiSearchFailed = true;
    return;
}
```

### 3.4 Http::fake capture with exact body keys (D-07 #1) — from `AiServiceTest.php:33-41` + `ChatWidgetTest.php:273-287`

```php
Http::assertSent(function ($request) {
    return str_contains($request->url(), '/search')
        && $request->hasHeader('X-Sidecar-Token', 'test-token')
        && $request['query'] === 'water pump'
        && $request['filters'] === ['department' => 'Civil Engineering']
        && $request['corpus'] === 'catalog'
        && $request['limit'] === 10
        && $request['k'] === 60;
});
// Chat: assert absence of a key via $request->data():
//   && ! array_key_exists('corpus', $request->data())
```
Delta for D-07 #1: add `array_keys($request->data()) === ['query','filters','corpus','limit','k']` (search) and `=== ['query','mode','top_k']` (chatStream with corpus null — corpus is omitted, `AiService.php:56-60`) — i.e. **exact** ADR 0004 key sets, guaranteeing no availability field.

### 3.5 Secondary button + status badge markup (D-14/D-04) — from the card action row, blade 96-106

```blade
<div class="flex gap-2 mt-4 pt-3 border-t border-base-300">
    <x-mary-button wire:click="showPaperDetails({{ $paper->id }})"
        class="btn-sm btn-primary gap-2 flex-1" icon="o-eye"
        label="View Details" spinner
        wire:loading.attr="disabled"
        wire:target="showPaperDetails({{ $paper->id }})" />
    {{-- NEW: x-mary-button wire:click="showSimilar({{ $paper->id }})"
         class="btn-sm btn-outline gap-2" icon="o-sparkles" label="Similar" spinner ... --}}
</div>
```
Chip suffix target (`chat-widget-citations.blade.php:3-7`):
```blade
<a href="{{ $c['url'] }}" class="...">
    [{{ $c['n'] }}] {{ $c['title'] }}
    <span class="opacity-60 font-mono">{{ $c['catalog_code'] }}</span>
    {{-- NEW: <span class="... {{ $avail['available'] > 0 ? 'text-success' : 'text-error' }}">{{ $avail['available'] }}/{{ $avail['total'] }}</span> --}}
</a>
```

### 3.6 States to reuse (D-16) — blade 38-45 banner, 123-129 empty state, 52-59 overlay

```blade
<div class="alert alert-warning text-sm mb-4">… AI search unavailable — showing basic results</div>
<x-empty-state icon="o-document-magnifying-glass" title="No Academic Papers Found" message="…" :show-action="false" size="sm" />
<div wire:loading.flex wire:target="…" class="absolute inset-0 bg-base-100/80 backdrop-blur-sm z-10 items-center justify-center rounded-lg">…</div>
```

### 3.7 Fixture-ids pattern for hybrid/recommendation tests — `AcademicPaperIndexHybridTest.php:44-71`

```php
$paper77->forceFill(['id' => 77])->save();   // matches tests/fixtures/ai-sidecar/search.json paper-77
```
Recommendation tests can reuse `search.json` (paper-77 title) as the seed paper and extend it with paper-78 (via `twoResultSearch()`) to assert self-exclusion drops 77 and keeps rank order.

---

## 4. Landmines / caveats

1. **D-06 needs real sidecar code, not just tests.** `/search` and `/chat/stream` silently ignore unknown fields today (`main.py` reads known keys from a loose `dict`). D-07 #3's 422 tests will fail until strict key-checking is added (a `_reject_unknown(payload, allowed_set)` helper beside `_invalid()`, applied in both endpoints, 422 `invalid_request`). `/index/rebuild` is out of scope (takes no fields; D-07 #3 names only the two endpoints).
2. **Livewire 4.4 / Laravel 13 / PHP 8.4** — older docs (`.cursor/rules`, `.github/instructions`) claim Livewire 3 / Laravel 12; code comments (`chat-widget.blade.php:67-78`) describe Livewire 4 behaviors. Use v4 semantics: `#[Computed]` fine, `$this->stream()` fine, but check v4 pagination/lazy specifics if anything odd appears.
3. **The exact-shape citation test is a double-edged guard.** `ChatWidgetTest::it_persists_citation_payload` (lines 309-318) asserts `assertSame` on the full payload — it enforces D-07 #2 (6 keys, no availability). Any enrichment added **to the persisted payload** breaks it. Enrichment must be render-time: pass a lookup map into `chat-widget-citations` at the include site (`chat-widget.blade.php:52`), keyed by catalog_code (or `paper-N` id → int). Do NOT write back into `$m['citations']` unless the enrichment is a strictly separate in-memory array (safe, but the persisted `Message::citations` column must never see it).
4. **One grouped hydrator query per render — batch across messages.** The chat widget renders N messages each with ≤5 catalog citations; calling `forPapers` per message is N+1 (what D-02 forbids). Collect all catalog ids across `$this->messages` in one pass (e.g., a `#[Computed]` availability map), then `forPapers($ids)` once. Same on the search page: paginator ids (page 1..N of current page only) + hybrid ids + recommendation ids in a single call.
5. **`checked_at` must never be persisted** — it is `now()` per fetch; UI shows the static caption (D-05). Only the hydrator unit test asserts its freshness (≈ now within tolerance).
6. **`Reserved` inventory rows count as unavailable** — and no code path currently writes `Reserved` to `inventory.status` (only the paper-level `academic_papers.status` gets it, `BorrowTransaction.php:268,280`). The hydrator's `CASE WHEN status = "Available"` is consistent with the whole app; do not be tempted to treat `Reserved` specially.
7. **`withCount` stays** for `orderBy('status')` (line 156-157) and the status filter (138-148) — but display moves to the hydrator. `$paper->available_copies`/`$paper->status` will still exist; the view must switch the "Copies" cell to `$availability[$paper->id]['available'] . ' of ' . ...` — keep the `status` badge as-is (it's derived from the same data, so it stays truthful).
8. **Recommendations mode interacts with every `updated*` hook.** `updatedSearch`, `updatedYearFilter`, `updatedDepartmentFilter`, `updatedPaperTypeFilter`, `updatedYearFromFilter`, `updatedYearToFilter`, `updatedStatusFilter`, `clearFilters`, `updatingPerPage` all mutate results state. D-18's yield-on-change must be injected at the top of each (or funneled through one `exitRecommendationsMode()` called by all), and the snapshot must be abandoned on any change. `updatingPerPage` currently only resets the page — it must also exit the mode (a per-page change is a user query change per D-18's spirit; decide explicitly in planning).
9. **Snapshot semantics for exact restore (D-15).** Snapshot must include: `search`, all six filters, `hybridResults` + `aiSearchFailed` (the arrays hold Eloquent models — copying the array reference is fine since nothing mutates the prior list while in mode), pagination `page` (Livewire's `$page` per pageName), and `sortBy`. Restore must not trigger `updated*` hooks (restoring via direct property assignment inside a `call()` is fine — hooks fire on client-side property updates, not server-side assignment; verify in tests).
10. **`wire:key` collisions.** Recommendation cards rendered in the same grids must use distinct keys (e.g. `rec-mobile-{id}`) or Livewire DOM diffing may reuse wrong nodes when the list swaps.
11. **`Http::toStream()` does not exist** in the codebase (ADR 0004's mention is stale; implementation is `withOptions(['stream' => true])` + `resource()`). Search calls are plain `Http::response(json, 200)` fakes — no stream concerns in Phase 10.
12. **Pint scoping:** baseline is Pint-clean; scope `vendor/bin/pint` to touched files only (CONTEXT.md line 85).
13. **`AcademicPaperFactory` requires seeded lookups** (`Dean::inRandomOrder()->first()`, `ResearchAdviser`, `TechnicalAdviser`). `seedPapers()` in `AcademicPaperIndexHybridTest` hand-creates them — replicate for recommendation tests; `forceFill(['id' => N])` aligns DB ids with sidecar fixture ids.
14. **Sidecar test suite is a separate repo/CI** (pytest, `tests/` next to `app/`) — the D-07 #3 tests and the `main.py` validation change land there; the Laravel repo can't prove 422 enforcement itself (only the capture-side assertions).
15. **No `AcademicPaperIndexTest.php` exists** — the hybrid test file is the model; SQL-path coverage lives in `AcademicPapersTest.php`/`FiltersTest.php`. New Phase 10 feature tests should extend the hybrid file or add `AvailabilityTest.php`/`SimilarPapersTest.php`.
16. **`STATE.md` is stale for milestone v2.0** (Phases 8-10 still marked NOT STARTED while Phase 9 shows complete in `last_activity`) — cosmetic, planning-time only.
17. **`available_copies` vs `total` vs `logical_copies_count`:** hydrator `total` = count of Inventory rows (= `total_copies_count` semantics); `logical_copies_count` (max copy_number) is used by the modal header and is a different number when copies are deleted — never use it as `total`.

---

## 5. Validation architecture

### SEARCH-02 (availability hydration) — D-07 five proofs, mapped to concrete files

| D-07 # | Proof | Where it lives | Current state |
|---|---|---|---|
| 1 | `AiService::search()`/`chatStream()` request bodies carry **exactly** the ADR 0004 keys (no availability) | `tests/Feature/AiServiceTest.php` (search), `tests/Feature/ChatWidgetTest.php` (chatStream, `it_binds_companion_search_to_chat_parameters`), optionally `AcademicPaperIndexHybridTest.php` (page-level capture) | **Delta:** add exact-key-set assertion `array_keys($request->data()) === [...]` + `! array_key_exists('available', $request->data())` |
| 2 | Citation payload = exactly `{n, id, corpus, title, url, catalog_code}` | `tests/Feature/ChatWidgetTest.php::it_persists_citation_payload` (309-318) | Already exact; **add** an explicit no-extra-keys guard (assertSame already covers it — keep untouched, add a companion assertion on the captured body) |
| 3 | Sidecar rejects unknown fields with 422 on `/search` + `/chat/stream` | **sidecar** `tests/test_api.py` (new `test_search_rejects_unknown_fields`) + `tests/test_chat_stream.py` (new `test_chat_stream_rejects_unknown_fields`) | **Does not exist; needs new `main.py` strict-key validation first** (see §4.1) |
| 4 | Hydrator unit test: mixed copy statuses (Available/Reserved/Unavailable), total = all rows, `checked_at` ≈ now | **new** `tests/Unit/AvailabilityServiceTest.php` (RefreshDatabase; 3 papers with mixed copy sets; assert per-id `{available, total}` and `now()->diffInSeconds($checked_at) < 5`) | New |
| 5 | Render-level: numbers on screen while captured sidecar payloads contain none | **new** feature tests: search page shows "2 of 3 available" + "Checked just now" (Http::fake capture asserting payload has no availability keys); chat chip shows "2/3" suffix with color class; policy chips unchanged | New |

### SEARCH-06 (similar-books mechanism) — additional validation

| Concern | Test | Location |
|---|---|---|
| Title-as-query verbatim | `Http::assertSent` on `/search`: `query === $paper->title`, `filters === []`, `corpus === 'catalog'`, `limit === 10`, `k === 60`; **no** metadata filters | new `tests/Feature/SimilarPapersServiceTest.php` (or Unit — service is HTTP-bound, Feature-style with Http::fake) |
| Self-exclusion | Seed paper id dropped from returned collection; remaining results keep sidecar rank order (extend `twoResultSearch()` fixture) | same |
| Fail-closed: sidecar down / auth | `ConnectionException` fake and 401 fake → empty collection + unavailable flag (`SimilarPapersService` must expose e.g. `unavailable`), no exception escapes | same |
| Fail-closed: empty / self-exclusion-emptied | empty results fixture; single-result fixture equal to seed → empty collection, flag false | same |
| Determinism of the mechanism | Same paper, two calls → identical ordered id lists (sidecar determinism is a given; the Laravel mapping must not scramble it — assert id order equality) | same |
| Component behavior (D-15/D-18) | `AcademicPaperIndex` tests: click Similar → recommendations mode header + cards + hydrated "X of Y"; Back restores search text/filters/hybrid-vs-SQL/page exactly; editing search/filters while in mode exits immediately and runs the normal query; status filter while in mode still force-exits hybrid; Similar on a recommended card recurses; unavailable → banner + Back; empty → `x-empty-state` + Back | new/extended `tests/Feature/AcademicPaperIndexHybridTest.php` (or `AcademicPaperIndexSimilarTest.php`) |
| Never-LLM (cross-cutting) | Sidecar capture tests (D-07 #1) already prove no availability in requests; recommendation requests assert exactly the 5 keys (query/filters/corpus/limit/k) | as above |

### Suggested new/changed files inventory

- **Laravel:** `app/Services/AvailabilityService.php` (new), `app/Services/SimilarPapersService.php` (new), `app/Livewire/Pages/Student/AcademicPaperIndex.php` (hydrator wiring, recommendations-mode state + hooks, `showSimilar()`, snapshot/restore), `resources/views/livewire/pages/student/academic-paper-index.blade.php` (4 button spots, X-of-Y + caption, header bar, states), `resources/views/livewire/chat-widget.blade.php` + `chat-widget-citations.blade.php` (suffix + availability map variable), `app/Livewire/ChatWidget.php` (render-time availability map computed), tests: `tests/Unit/AvailabilityServiceTest.php`, `tests/Feature/SimilarPapersServiceTest.php`, `tests/Feature/AcademicPaperIndexSimilarTest.php` (or extend hybrid test), deltas in `AiServiceTest.php`/`ChatWidgetTest.php`.
- **Sidecar:** `app/main.py` (strict unknown-field validation on `/search` + `/chat/stream`), `tests/test_api.py` + `tests/test_chat_stream.py` (two 422 tests).

---

*Phase: 10-live-availability-similar-book-recommendations | Research compiled 2026-08-14*
