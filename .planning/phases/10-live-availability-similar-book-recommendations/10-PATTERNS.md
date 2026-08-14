# Phase 10 Patterns

**Source of truth:** `10-CONTEXT.md` (D-01..D-18, locked), `10-RESEARCH.md` (verified facts). All file:line refs verified 2026-08-14 against the working tree.

---

## 1. File Map (deliverable → analog → role/data flow)

| # | Deliverable | Closest analog (file:line) | Role / data flow |
|---|---|---|---|
| 1 | **NEW** `app/Services/AvailabilityService.php` — `forPapers(array $ids): array` grouped hydration `{available, total, checked_at}` (D-01/D-02/D-06) | `AcademicPaperIndex.php:150-154` (withCount pattern), `AcademicPaper.php:73-82` (accessor semantics to NOT use), `Inventory.php:56-59` (`isAvailable()` status semantics) | Pure read service: `Inventory` grouped `whereIn` → keyed array. Consumed by: search page (paginator ids + hybrid ids + recommendation ids, one call), chat widget (one `#[Computed]` map across all messages). Never persisted; `checked_at = now()` at fetch. |
| 2 | **NEW** `app/Services/SimilarPapersService.php` — `for(AcademicPaper $paper, int $limit = 10): Collection` title-as-query + self-exclusion + fail-closed (D-08..D-13) | `AcademicPaperIndex.php:296-333` (`runHybridSearch` full pipeline), `AiService.php:27-36` (`search()` signature), `AiService.php:19` (`RRF_CANDIDATES = 60`), `CorpusExporter.php:36-52` (doc shape), `AcademicPaperIndex.php:339-343` (`exitHybridMode` flag idiom) | `AiService::search($paper->title, [], 'catalog', 10)` → map `paper-N` ids → reject seed id (D-11) → `findMany` → re-key to sidecar order → status transform → Collection. Catches `AiServiceUnavailableException|AiServiceAuthException` → empty + `unavailable` flag (D-13). |
| 3 | **MODIFY** `app/Livewire/Pages/Student/AcademicPaperIndex.php` — recommendations mode state, `showSimilar()`, snapshot/restore, yield-on-change in every `updated*` hook (D-14..D-18) | `AcademicPaperIndex.php:58-60` (mode as plain public props), `:280-343` (hybrid state machine + `exitHybridMode`), `:212-274` (the 9 yield points), `ChatWidget.php:15-18` (`view`/`activeConversationId` mode-switch precedent) | Client click → `showSimilar($id)` snapshots prior state → loads recommendations into the same card render path → blade branches on `! is_null($this->recommendedFor)`. Every `updated*` hook + `clearFilters` + `updatingPerPage` exits the mode (D-18). |
| 4 | **MODIFY** `resources/views/livewire/pages/student/academic-paper-index.blade.php` — Similar buttons (4 spots), X-of-Y + caption (3 spots), recommendations header bar + 3 states (D-04/D-05/D-14/D-15/D-16/D-17) | `academic-paper-index.blade.php:96-106/165-175/268-278` (3 card action rows), `:349-371` (table actions scope), `:38-45` (alert-warning banner), `:122-129` (empty state), `:52-59/224-231` (loading overlays), `:64/133/236` (wire:key scheme) | Blade branches on `recommendedFor` before the hybrid/SQL split; recommendation cards reuse existing grid markup verbatim with distinct `wire:key` prefix. |
| 5 | **MODIFY** `resources/views/livewire/chat-widget-citations.blade.php` — "2/3" suffix + color cue on catalog chips only (D-04) | `chat-widget-citations.blade.php:3-7` (catalog chip markup), `:9-11` (policy chip — unchanged) | Render-time enrichment: suffix span keyed off an extra include variable (`$availability` map), never written into `$m['citations']`. |
| 6 | **MODIFY** `app/Livewire/ChatWidget.php` + `resources/views/livewire/chat-widget.blade.php:51-54` — hydrate citations at render time (D-04) | `ChatWidget.php:23-24` (message shape), `:47-63` (`openConversation` history re-render), `ChatWidget.php:234-260` (`companionCitations` 6-key payload), include site `chat-widget.blade.php:51-54` | One `#[Computed] availabilityMap` over all messages' catalog ids (`str_replace('paper-', '', $c['id'])` → int) → `AvailabilityService::forPapers()` once per render → pass map at include site. |
| 7 | **MODIFY** `ceit-ai-sidecar/app/main.py` — strict-key 422 on `/search` + `/chat/stream` (D-06/D-07 #3) | `main.py:52-56` (`_invalid()` 422 helper), `:87-106` (`/search` loose `payload: dict` reads), `:109-140` (`/chat/stream`), `:66-79` (token middleware) | New `_reject_unknown(payload, allowed)` helper beside `_invalid()`; called first in both endpoints; allowed sets: `/search` `{query, filters, corpus, limit, k}`, `/chat/stream` `{query, mode, corpus, top_k}`. No pydantic models exist — validation is hand-rolled dict checks; follow that style (no new FastAPI machinery). |
| 8a | **NEW** `tests/Unit/AvailabilityServiceTest.php` (D-07 #4) | `tests/Unit/AcademicPaperTest.php:1-30` (style: `Tests\Unit`, PHPUnit methods; base `Tests\TestCase` already provides `RefreshDatabase` + in-memory SQLite — see `tests/TestCase.php:10-42`) | 3 papers × mixed copy statuses; assert per-id `{available, total}`, `checked_at` ≈ now (tolerance < 5s), papers with no copies → absent/zero. |
| 8b | **NEW** `tests/Feature/SimilarPapersServiceTest.php` (SEARCH-06) | `tests/Feature/AiServiceTest.php:21-93` (Http::fake capture + typed exception tests), `AcademicPaperIndexHybridTest.php:37-96` (seedPapers/forceFill/twoResultSearch) | Title-as-query verbatim (5 keys exact), self-exclusion (seed id dropped, rank order kept), fail-closed (ConnectionException + 401), determinism (two calls → same order). |
| 8c | **NEW** `tests/Feature/AcademicPaperIndexSimilarTest.php` (D-15/D-18) | `AcademicPaperIndexHybridTest.php` whole file (component test home), `ChatWidgetTest.php:43-48` (Http::fake boilerplate) | Click Similar → header + cards + hydrated numbers; Back restores exactly; edit-search exits mode; status filter still force-exits hybrid; recursion; unavailable banner; empty state. |
| 8d | **NEW** `ceit-ai-sidecar/tests/test_search_contract.py` (D-07 #3) + extend `tests/test_chat_stream.py` | `tests/test_api.py:16-53` (client fixture), `:79-82` (422 test), `tests/test_chat_stream.py:126-183` (422 patterns) | `/search` + `/chat/stream` reject unknown fields (`availability`, `exclude`, etc.) with 422 `invalid_request`. Will FAIL until #7 lands. |
| 8e | **MODIFY** `tests/Feature/AiServiceTest.php:33-41`, `tests/Feature/ChatWidgetTest.php:254-318` — exact-key-set + no-availability assertions (D-07 #1/#2) | existing capture assertions (see §2.8) | Add `array_keys($request->data()) === [...]` to existing `Http::assertSent` closures; keep `it_persists_citation_payload` untouched (it already pins the 6 keys). |

---

## 2. Concrete excerpts per deliverable (with file:line refs — copy actual code)

### 2.1 AvailabilityService — grouped hydrator query (D-02, ADR 0010 verbatim)

```php
// AvailabilityService::forPapers(array $ids): array  →  [id => ['available' => int, 'total' => int, 'checked_at' => Carbon]]
Inventory::whereIn('academic_paper_id', $ids)
    ->selectRaw('academic_paper_id,
        COUNT(*) AS total,
        SUM(CASE WHEN status = "Available" THEN 1 ELSE 0 END) AS available')
    ->groupBy('academic_paper_id');
```

Analog semantics to copy:

```php
// app/Models/Inventory.php:56-59 — the ONLY status that counts as available
public function isAvailable()
{
    return $this->status === 'Available';
}

// app/Livewire/Pages/Student/AcademicPaperIndex.php:150-154 — existing inline shape
->withCount([
    'copies as available_copies' => function ($query) {
        $query->where('status', 'Available');
    },
]);

// app/Models/AcademicPaper.php:73-82 — DO NOT reuse: per-row N+1 accessors
public function getAvailableCopiesCountAttribute()
{
    return $this->copies()->where('status', 'Available')->count();
}
public function getTotalCopiesCountAttribute()
{
    return $this->copies()->count();
}
```

Service call sites (one grouped call per render — never per-row, D-02/D-03):
- Search page: `academicPapers` paginator ids (`$paginated->pluck('id')->all()` at `AcademicPaperIndex.php:162`) + `hybridResults` ids + `recommendations` ids, combined in one `forPapers()` call (e.g. a `#[Computed] availability`).
- Chat widget: ids derived per citation via `str_replace('paper-', '', $c['id'])` (same pattern as `AcademicPaperIndex.php:306`), one pass over all messages (RESEARCH.md landmine #4).

### 2.2 SimilarPapersService — full pipeline analog (`AcademicPaperIndex.php:296-333`)

```php
// 1) Typed-exception fail-closed (D-13) — AcademicPaperIndex.php:296-303
try {
    $results = (new AiService)->search($this->search, $filters, 'catalog', 10);
} catch (AiServiceUnavailableException|AiServiceAuthException) {
    $this->hybridResults = null;
    $this->aiSearchFailed = true;
    return;
}

// 2) paper-N id mapping — AcademicPaperIndex.php:305-308
$ids = collect($results['results'] ?? [])
    ->map(fn ($result) => (int) str_replace('paper-', '', $result['id']))
    ->filter()
    ->all();

// 3) findMany + rank-preserving re-key — AcademicPaperIndex.php:310-330
$papers = AcademicPaper::with(['authors:id,name', 'copies:id,academic_paper_id,status'])
    ->withCount([
        'copies as available_copies' => function ($query) {
            $query->where('status', 'Available');
        },
    ])
    ->findMany($ids);

$byId = $papers->keyBy('id');

// Preserve the sidecar rank order (findMany returns DB order).
$this->hybridResults = collect($ids)
    ->map(fn ($id) => $byId->get($id))
    ->filter()
    ->map(function ($paper) {
        $paper->status = $paper->available_copies > 0 ? 'Available' : 'Unavailable';
        return $paper;
    })
    ->values()
    ->all();
```

`SimilarPapersService::for()` deltas to this skeleton:
- Call signature: `(new AiService)->search($paper->title, [], 'catalog', 10)` — title only, `filters = []`, k stays 60 via `AiService::RRF_CANDIDATES` (D-08..D-10).
- Self-exclusion (D-11) inserted between step 2 and 3: `collect($ids)->reject(fn ($id) => $id === $paper->id)->values()->all()`.
- Fail-closed (D-13): catch → return `collect()` + set an `unavailable` flag property (e.g. `public bool $unavailable = false` set true in catch — mirror the `aiSearchFailed` flag idiom at `AcademicPaperIndex.php:60`); empty/self-exclusion-emptied → `collect()`, flag false.
- Search signature analog (`AiService.php:27-36`): `search(string $query, array $filters = [], ?string $corpus = 'catalog', int $limit = 10): array` POSTs `{query, filters, corpus, limit, k}` with `k = self::RRF_CANDIDATES` (= 60, line 19).
- Doc shape grounding (`CorpusExporter.php:36-52`): ids are `'paper-'.$paper->id`, title-doubled text, metadata carries `catalog_code/department/publication_year/paper_type/authors/url` — the title-as-query signal.

### 2.3 Recommendations mode in AcademicPaperIndex (D-14..D-18)

Mode-as-property precedent (hybrid mode, `AcademicPaperIndex.php:58-60`):

```php
// Hybrid search state (sidecar-ordered results + fallback flag)
public ?array $hybridResults = null;
public bool $aiSearchFailed = false;
```

Recommendations analog: `public ?int $recommendedFor = null;` + `public ?array $recommendations = null;` + `public bool $recommendationsUnavailable = false;` + snapshot props (search text, 6 filters, hybridResults, aiSearchFailed, page, sortBy — RESEARCH.md landmine #9).

Mode exit precedent (`AcademicPaperIndex.php:339-343`):

```php
private function exitHybridMode(): void
{
    $this->hybridResults = null;
    $this->aiSearchFailed = false;
}
```

→ `exitRecommendationsMode()` (clears mode + snapshot), called from `exitHybridMode()` AND every yield point. The 9 yield points to guard (all in `AcademicPaperIndex.php`): `updatedDept` (212-215), `updatedSearch` (217-221), `updatedStatusFilter` (223-230), `updatedYearFilter` (232-236), `updatedDepartmentFilter` (238-242), `updatedPaperTypeFilter` (244-248), `updatedYearFromFilter` (250-254), `updatedYearToFilter` (256-260), `clearFilters` (263-274), plus `updatingPerPage` (70-73).

`runHybridSearch` entry guard to mirror (`AcademicPaperIndex.php:282-286`):

```php
if (strlen(trim($this->search)) < 3 || $this->statusFilter !== '') {
    $this->exitHybridMode();
    return;
}
```

Pagination pageName is mandatory (`AcademicPaperIndex.php:162`):

```php
$paginated = $query->paginate($this->perPage, pageName: 'academic-papers-index');
```

Modal dispatch precedent for `showSimilar` snapshot/restore (`AcademicPaperIndex.php:345-349`):

```php
public function showPaperDetails(int $paperId): void
{
    $this->selectedPaperId = $paperId;
    $this->dispatch('open-paper-modal');
}
```

### 2.4 Blade — card action rows (4 Similar-button spots)

Mobile hybrid card (`academic-paper-index.blade.php:96-106`) — same markup at 165-175 (mobile SQL) and 268-278 (desktop hybrid); the D-14 delta is a second button beside View Details:

```blade
<div class="flex gap-2 mt-4 pt-3 border-t border-base-300">
    @if($this->canBorrow)
        <x-mary-button 
            wire:click="showPaperDetails({{ $paper->id }})"
            class="btn-sm btn-primary gap-2 flex-1"
            icon="o-eye"
            label="View Details"
            spinner
            wire:loading.attr="disabled"
            wire:target="showPaperDetails({{ $paper->id }})"
        />
        {{-- NEW (D-14): secondary Similar — btn-outline, auto-width, no flex-1
        <x-mary-button
            wire:click="showSimilar({{ $paper->id }})"
            class="btn-sm btn-outline gap-2"
            icon="o-sparkles"
            label="Similar"
            spinner
            wire:loading.attr="disabled"
            wire:target="showSimilar({{ $paper->id }})"
        /> --}}
    @else
        {{-- low-credit branch: unchanged, spans the row --}}
    @endif
</div>
```

Desktop SQL table `actions` scope (`blade:349-371`) — the 4th spot:

```blade
@scope('actions', $row)
@if($this->canBorrow)
    <x-mary-button 
        wire:click="showPaperDetails({{ $row->id }})"
        class="btn-sm btn-primary gap-2"
        icon="o-eye"
        label="View"
        spinner
        wire:loading.attr="disabled"
        wire:target="showPaperDetails({{ $row->id }})"
    />
    {{-- NEW: Similar button, same props with $row->id --}}
@else
    ...
@endif
@endscope
```

X-of-Y + caption (D-04/D-05) — replaces `{{ $paper->available_copies }} available` at blade lines 92 (mobile hybrid), 161 (mobile SQL), 264 (desktop hybrid):

```blade
<div>
    <p class="text-base-content/50 font-medium mb-1">Copies</p>
    <p class="font-medium">
        {{ $availability[$paper->id]['available'] }} of {{ $availability[$paper->id]['total'] }} available
    </p>
    <p class="text-xs text-base-content/50">Checked just now</p>
</div>
```

States to reuse (D-16), verbatim:
- Banner (`blade:38-45`): `<div class="alert alert-warning text-sm mb-4">` + inline heroicon SVG + `<span>AI search unavailable — showing basic results</span>` → recommendations variant "Recommendations unavailable right now".
- Empty state (`blade:123-129`): `<x-empty-state icon="o-document-magnifying-glass" title="No Academic Papers Found" message="…" :show-action="false" size="sm" />` → "No similar books found".
- Loading overlay (`blade:52-59`): `wire:loading.flex` + `wire:target="…"` + `absolute inset-0 bg-base-100/80 backdrop-blur-sm z-10` → target `showSimilar(...)`, label "Finding similar books...".
- `wire:key` scheme (blade:64/133/236): `hybrid-mobile-{id}` / `mobile-paper-{id}` / `hybrid-desktop-{id}` → recommendation grids must use a distinct prefix (`rec-…`) to avoid Livewire DOM reuse (RESEARCH.md landmine #10).

### 2.5 Chat chip suffix (D-04) — `chat-widget-citations.blade.php:3-7`

```blade
@if ($c['corpus'] === 'catalog' && $c['url'])
    <a href="{{ $c['url'] }}" class="inline-flex items-center gap-1 rounded-full border border-primary/40 bg-primary/5 text-primary px-2.5 py-0.5 text-[11px] hover:bg-primary/10">
        [{{ $c['n'] }}] {{ $c['title'] }}
        <span class="opacity-60 font-mono">{{ $c['catalog_code'] }}</span>
        {{-- NEW (D-04): suffix beside catalog_code, green ≥1, red 0
        @if (! empty($availability[$c['catalog_code']]))
            <span class="{{ $availability[$c['catalog_code']]['available'] > 0 ? 'text-success' : 'text-error' }}">{{ $availability[$c['catalog_code']]['available'] }}/{{ $availability[$c['catalog_code']]['total'] }}</span>
        @endif --}}
    </a>
@else
    {{-- policy chip (lines 9-11): unchanged — no availability concept (D-04) --}}
@endif
```

Include site with the extra variable (`chat-widget.blade.php:51-54`):

```blade
@if (! empty($m['citations']))
    @include('livewire.chat-widget-citations', ['citations' => $m['citations']])
    @include('livewire.chat-widget-sources', ['citations' => $m['citations']])
@endif
```

→ pass `'availability' => $this->availabilityMap` at line 52 only (chips partial); the sources partial at line 53 stays untouched. The persisted `Message::citations` payload is never enriched (guarded by `ChatWidgetTest::it_persists_citation_payload`, `ChatWidgetTest.php:289-319`).

ChatWidget wiring analog — message shape (`ChatWidget.php:23-24`) and history re-render (`:47-63`):

```php
/** @var array<int, array{role: string, content: string, citations?: array|null, failed?: bool, error?: array|null}> */
public array $messages = [];

public function openConversation(int $id): void
{
    $conversation = $this->ownedConversation($id);
    if (! $conversation) {
        return;
    }
    $this->messages = $conversation->messages
        ->map(fn (Message $message) => $message->role === 'user'
            ? $this->userBubble($message->content)
            : $this->assistantBubble($message->content, $message->citations))
        ->all();
    ...
}
```

Id derivation analog (`ChatWidget.php:254-256` reads `$result['metadata']['catalog_code']`; the persisted payload keeps only `catalog_code` + `paper-N` `id` — map via `str_replace('paper-', '', $c['id'])` → int id → `AvailabilityService::forPapers()`; if `paper-` parsing fails (policy ids like `policy-h2-r1`), skip that citation).

### 2.6 Sidecar strict validation (D-06) — insertion points in `main.py`

Existing helpers (copy style, no pydantic in this codebase):

```python
# main.py:52-56
def _invalid(message: str) -> JSONResponse:
    return JSONResponse(
        status_code=422,
        content={"error": {"code": "invalid_request", "message": message}},
    )
```

New helper beside `_invalid()`:

```python
SEARCH_ALLOWED_KEYS = {"query", "filters", "corpus", "limit", "k"}
CHAT_ALLOWED_KEYS = {"query", "mode", "corpus", "top_k"}

def _reject_unknown(payload: dict, allowed: set[str]) -> JSONResponse | None:
    unknown = set(payload) - allowed
    if unknown:
        return _invalid(f"unknown field(s): {', '.join(sorted(unknown))}")
    return None
```

Call as the FIRST statement of `search()` (main.py:88, after `payload: dict` param) and `chat_stream()` (main.py:110):

```python
@app.post("/search")
def search(payload: dict):
    rejected = _reject_unknown(payload, SEARCH_ALLOWED_KEYS)
    if rejected:
        return rejected
    query = _require_query(payload)
    ...
```

Allowed-key sets are ADR 0004's closed contract (`AiService.php:29-34` posts `query, filters, corpus, limit, k`; `chatStream` at `AiService.php:56-60` posts `query, mode, top_k` and omits `corpus` when null). `/index/rebuild` (main.py:143) takes no fields — out of scope.

### 2.7 Sidecar tests (D-07 #3) — patterns to copy

Client fixture (`tests/test_api.py:16-53`) builds a real `TestClient` with `build_test_index` + deterministic embedders; 422 test pattern (`:79-82`):

```python
def test_search_returns_422_without_query(client):
    app, _, _ = client
    resp = app.post("/search", json={}, headers={"X-Sidecar-Token": "test-token"})
    assert resp.status_code == 422
```

New unknown-field tests (same shape, assert `resp.json()["error"]["code"] == "invalid_request"`):

```python
def test_search_rejects_unknown_fields(client):
    app, _, _ = client
    resp = app.post(
        "/search",
        json={"query": "water pump", "availability": {"77": "1/2"}},
        headers={"X-Sidecar-Token": "test-token"},
    )
    assert resp.status_code == 422
    assert resp.json()["error"]["code"] == "invalid_request"
```

`/chat/stream` 422 pattern (`tests/test_chat_stream.py:164-183`) uses `make_client(tmp_path, corpus_path, SSE_DOCS)` with `FakeEngine` + `FakeCompletions` (lines 23-105) — same fixture works for the unknown-field test (validation happens before retrieval, so engine results are never consulted).

### 2.8 Laravel test deltas (D-07 #1/#2) — extend existing capture closures

Exact-key-set additions:

```php
// tests/Feature/AiServiceTest.php:33-41 — add at the end of the closure:
&& array_keys($request->data()) === ['query', 'filters', 'corpus', 'limit', 'k']
&& ! array_key_exists('available', $request->data())

// tests/Feature/ChatWidgetTest.php:281-286 — chatStream body:
&& array_keys($request->data()) === ['query', 'mode', 'top_k']  // corpus null → omitted
```

`it_persists_citation_payload` (`ChatWidgetTest.php:309-318`) already asserts the exact 6-key shape via `assertSame` — leave untouched; it is the double-edged guard for D-07 #2 (enrichment must never reach the persisted payload).

### 2.9 New-feature-test scaffolding (reuse verbatim)

`AcademicPaperIndexHybridTest.php` fixture-alignment pattern (lines 37-71): seed Dean/ResearchAdviser/TechnicalAdviser first, `forceFill(['id' => 77])` to match `tests/fixtures/ai-sidecar/search.json` paper-77; `twoResultSearch()` (73-96) appends paper-78 for rank/self-exclusion assertions. Boilerplate (`:24-30` + `:102`):

```php
protected function setUp(): void
{
    parent::setUp();
    $user = User::factory()->create();
    $this->actingAs($user);
}

// inside each test:
config(['services.ai_sidecar.token' => 'test-token']);
Http::fake([
    'http://127.0.0.1:8310/search' => Http::response($this->twoResultSearch(), 200),
]);
```

Fail-closed fakes (`AcademicPaperIndexHybridTest.php:149`, `AiServiceTest.php:75`):

```php
Http::fake([
    'http://127.0.0.1:8310/*' => fn () => throw new ConnectionException('Connection refused'),
]);
```

Livewire component assertions (`:108-118`): `Livewire::test(AcademicPaperIndex::class)->set('search', 'water pump')->call('runHybridSearch')->assertSet('aiSearchFailed', false)->assertSet('hybridResults', fn ($r) => $r[0]->id === 77)->assertSeeInOrder([...])`. For render-level availability proof (D-07 #5): seed `Inventory` rows (see `AcademicPaperIndexHybridTest.php:187-191`), assert `assertSee('2 of 3 available')` while the captured `/search` body has no availability keys.

Unit-test base: `tests/TestCase.php:10-42` already provides `RefreshDatabase` + forced in-memory SQLite for the whole suite (Unit and Feature) — new tests only need `use RefreshDatabase;` explicitly or nothing (trait on base class).

---

## 3. Conventions to preserve

**Naming**
- Services: `XxxService` in `app/Services/`, plain `new AiService` instantiation (no DI container), public consts for contract constants (`AiService::RRF_CANDIDATES = 60`).
- Livewire state: mode as plain public props with `?array`/`?int` nullability (`hybridResults`, `aiSearchFailed` idiom); `#[Computed]` for derived query/option results, `#[Computed(persist: true, cache: true)]` only for static filter options (`AcademicPaperIndex.php:174,190,201`).
- Methods: `updatedX`/`updatingX` Livewire hooks; `runHybridSearch` style `run*` actions; private `exit*Mode()` helpers.
- Views: kebab-case partial names (`chat-widget-citations.blade.php`); `wire:key` per loop node.
- Tests: PHPUnit `#[Test]` attributes + snake_case `it_...` methods (Feature), plain `test_...` methods (Unit — `AcademicPaperTest.php`); pytest `test_...` in the sidecar.

**Livewire hook contract (Livewire 4, Laravel 13, PHP 8.4 — repo is v4, not v3 as stale docs claim)**
- `updatedSearch/YearFilter/DepartmentFilter/PaperTypeFilter/YearFromFilter/YearToFilter` → `resetPage('academic-papers-index')` + `runHybridSearch()`.
- `updatedStatusFilter` → `resetPage` + `exitHybridMode()` (never calls the sidecar — status filter is SQL-only, `AcademicPaperIndex.php:223-230`).
- `updatingPerPage` → `resetPage('academic-papers-index')` only (fires before the property updates).
- `resetPage('academic-papers-index')` — the pageName is always `'academic-papers-index'` (line 162).
- `#[Lazy]` + `placeholder()` returns a dedicated blade view (`AcademicPaperIndex.php:22,494-497`); `tests/TestCase.php:31-47` has `disableLivewireLazyLoading` opt-out and `SupportTesting::provide()` for `Livewire::test()`.

**Mary UI / DaisyUI classes**
- Primary action: `x-mary-button` with `class="btn-sm btn-primary gap-2 flex-1"` + `icon="o-eye"` + `label` + `spinner` + `wire:loading.attr="disabled"` + `wire:target="method({{ $id }})"` (blade 98-106, 167-175, 270-278, 351-359).
- Secondary action: DaisyUI `btn-outline` passed through the `class` prop — no special Mary prop (active-users-tab.blade.php:14,207; admin-borrow-transactions.blade.php:59).
- Badges: `badge badge-sm badge-success|badge-error` (status), `badge badge-sm badge-outline` (catalog_code) — blade 68/71, 137/140, 240/243, 343-347.
- Empty state: `x-empty-state` with `icon`, `title`, `message`, `:show-action="false"`, `size="sm|default"` (blade 123-129, 194-209, 296-303, 313-333).
- Warning banner: `<div class="alert alert-warning text-sm mb-4">` + inline heroicon SVG + `<span>` (blade 38-45).
- Overlays: `wire:loading.flex` + `wire:target` comma-list + `absolute inset-0 bg-base-100/80 backdrop-blur-sm z-10 items-center justify-center rounded-lg` (blade 52-59, 224-231).
- Title truncation: `line-clamp-2 break-words` on card titles; D-17 header bar uses `line-clamp-1`.

**Pint**
- Baseline is Pint-clean; scope `vendor/bin/pint` to touched files only (CONTEXT.md line 85).

**Sidecar**
- Hand-rolled dict validation + `_invalid()` 422 envelope `{error: {code, message}}` (no pydantic models anywhere in main.py); token middleware via `X-Sidecar-Token` `secrets.compare_digest` (main.py:66-79).
- Error codes: `invalid_request` (422), `auth_failed` (401).

---

## 4. Landmines from analogs

1. **`findMany` returns DB order, not sidecar rank order** (`AcademicPaperIndex.php:316-330`). Always `keyBy('id')` then re-map over the ordered id list; never iterate the `findMany` result directly. `SimilarPapersService` must preserve this exact ordering (D-12 determinism test asserts id order across two calls).
2. **`Http::toStream()` does not exist** (RESEARCH.md §1.4). Real streaming is `->withOptions(['stream' => true])` + `$response->resource()` (`AiService.php:178-180, 89`). Irrelevant to Phase 10 search calls (plain JSON), but do not copy the stale ADR 0004 wording.
3. **Exact-shape citation test is a double-edged guard** — `ChatWidgetTest::it_persists_citation_payload` (`ChatWidgetTest.php:289-319`) `assertSame`s the full 6-key payload. Any enrichment written into `$m['citations']` or the `Message::citations` column breaks it. Enrichment MUST be a render-time extra include variable (`chat-widget.blade.php:52`), keyed by catalog_code (persisted) or re-derived int id.
4. **One grouped hydrator call per render** — chat renders N messages × ≤5 citations; per-message `forPapers()` is exactly the N+1 D-02 forbids. Batch all catalog ids across `$this->messages` in one `#[Computed]` map. Same on the search page: combine paginator + hybrid + recommendation ids into one call.
5. **`checked_at` must never be persisted or rendered as a value** — `now()` at fetch; UI shows the static caption (D-05); only the unit test asserts freshness (tolerance ~5s).
6. **`Reserved` is never written to `inventory.status` in the app** (`BorrowService::confirmBorrow` sets `'Unavailable'`, `handleReturn` sets `'Available'`; `BorrowTransaction.php:268` updates the paper-level column). Do not special-case `Reserved` — the hydrator's `CASE WHEN status = "Available"` matches the whole app.
7. **`withCount` stays** for `orderBy('status')` (`AcademicPaperIndex.php:156-157`) and the status filter (`:138-148`) — only *display* moves to the hydrator. Keep `$paper->status` transforms (lines 166, 325); badges stay truthful because they derive from the same data.
8. **Every `updated*` hook is a recommendations-mode yield point** — `updatedDept`, `updatedSearch`, `updatedStatusFilter`, `updatedYearFilter`, `updatedDepartmentFilter`, `updatedPaperTypeFilter`, `updatedYearFromFilter`, `updatedYearToFilter`, `clearFilters`, `updatingPerPage` (all `AcademicPaperIndex.php:70-73, 212-274`). Funnel exits through one `exitRecommendationsMode()`; `updatedStatusFilter` must still force-exit hybrid per the existing rule.
9. **Snapshot restore must not fire `updated*` hooks** — hooks fire on client-side property updates, not server-side assignment inside a `call()`. Restore by direct property assignment; verify in tests. Snapshot scope: `search`, six filters, `hybridResults` + `aiSearchFailed`, pagination page (per pageName), `sortBy` (RESEARCH.md landmine #9).
10. **`wire:key` collisions** — recommendation cards rendered in the same grids need a distinct prefix (`rec-…` vs `hybrid-mobile-`/`mobile-paper-`/`hybrid-desktop-`) or Livewire DOM diffing reuses wrong nodes on list swap (RESEARCH.md landmine #10).
11. **`logical_copies_count` ≠ hydrator `total`** — `max('copy_number')` (`AcademicPaper.php:85-88`) is a different number when copies are deleted; `total` = count of `Inventory` rows (RESEARCH.md landmine #17).
12. **`#[Computed(persist: true, cache: true)]` caching** — persists across requests; only use for static filter options. The availability map must be a plain `#[Computed]` (recomputed per render) or it serves stale counts.
13. **Sidecar strict validation must land before the 422 tests pass** — `test_search_contract.py` will fail on today's loose `payload: dict` handlers (RESEARCH.md §4.1); the `_reject_unknown` change is implementation, not contract change. `/search` and `/chat/stream` only; `/index/rebuild` takes no fields.
14. **Fixture id alignment** — DB ids must match fixture `paper-N` ids via `forceFill(['id' => N])->save()` (`AcademicPaperIndexHybridTest.php:67-68`); `AcademicPaperFactory` requires pre-seeded Dean/ResearchAdviser/TechnicalAdviser lookups (factory lines 33-45) — hand-create them as `seedPapers()` does.
15. **`Http::preventStrayRequests()`** — ChatWidgetTest sets it in every test (line 44); stray sidecar calls fail loudly. New Similar/availability tests should do the same.
16. **No `AcademicPaperIndexTest.php` exists** — the hybrid file is the model; SQL-path coverage lives in `AcademicPapersTest.php`/`FiltersTest.php`. Phase 10 component tests go in a new `AcademicPaperIndexSimilarTest.php` or extend the hybrid file.
17. **Sidecar test repo is separate** — Laravel-side capture assertions (`Http::assertSent` exact key sets) are the only 422-proof the Laravel repo can make; the actual 422 tests live in the sidecar's pytest suite.
18. **Livewire 4 semantics** — `$this->stream()` and `#[Computed]` are fine, but repo docs `.cursor/rules`/`.github/instructions` describe Livewire 3/Laravel 12 (stale); verify any v4-specific pagination/lazy behavior against `composer.lock` (Laravel 13.25.0, Livewire 4.4.0).
