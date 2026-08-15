# Phase 11: Academic Papers & Agentic Search - Pattern Map

**Mapped:** 2026-08-15
**Files analyzed:** 30 source files (6 planning/test-framework + 13 Laravel + 4 sidecar app + 6 sidecar tests + 1 fixture dir)
**Analogs found:** 20/21 target files (1 partial — new `agent.py` composes `rag.py` + RESEARCH examples; no in-repo tool-loop analog exists)

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `app/Services/CorpusExporter.php` (MODIFY) | Service — corpus writer | DB → `catalog.json` docs | itself — `exportCatalog()` current shape (lines 12-56) | 10/10 (self-evolution) |
| `app/Services/AiService.php` (MODIFY) | Service — sidecar HTTP gateway | Laravel ↔ sidecar HTTP | itself — `chatStreamEvents()` read loop (87-140) | 10/10 |
| `app/Livewire/Pages/Student/AcademicPaperIndex.php` (MODIFY) | Livewire page — paper tab mode | props → sidecar `/search` → DB hydrate | itself — `runHybridSearch()` (303-356), `showSimilar`/`backToResults` (376-434), computeds (188-224) | 10/10 |
| `resources/views/livewire/pages/student/academic-paper-index.blade.php` (MODIFY) | Blade surface | Livewire state → HTML | itself — recommendations mode guard (60-235); tab strip from `admin-advisers-deans.blade.php:32-53` | 10/10 |
| `resources/views/components/academic-paper-filters.blade.php` (MODIFY) | Blade component (shared student/admin) | props + `$wire` → HTML | itself — select pattern (93-129), `hasActiveFilters` (21-23), badges (133-155) | 10/10 |
| `app/Livewire/ChatWidget.php` (MODIFY) | Livewire component | SSE frames → stream slots + `ai_messages` | itself — `streamQuestion()` (124-198), `companionCitations()` (237-263) | 10/10 |
| `resources/views/livewire/chat-widget.blade.php` (MODIFY) | Blade surface | Livewire streams → DOM | itself — W-3 persistent slot (67-83), error bubble (56-61) | 10/10 |
| `tests/Feature/ExportAiCorpusTest.php` (MODIFY) | Feature test | seed DB → artisan export → JSON asserts | itself — `it_exports_catalog_documents_with_locked_schema` (60-91) | 10/10 |
| `tests/Feature/AiServiceTest.php` (MODIFY) | Feature test | `Http::fake` payload capture | itself — `it_posts_search_with_token_and_locked_payload` (21-46) | 10/10 |
| `tests/Feature/AiServiceChatTest.php` (MODIFY) | Feature test | `Http::fake` SSE fixtures | itself — chunk ordering (58-72), error event (74-94) | 10/10 |
| `tests/Feature/ChatWidgetTest.php` (MODIFY) | Feature test | Livewire + faked streams + DB asserts | itself — send/persist pattern (86-110), companion binding (279-319) | 10/10 |
| `tests/Feature/AcademicPaperIndexHybridTest.php` (MODIFY) | Feature test | Livewire + `Http::fake` | itself — filter forwarding (121-140), force-exit (182-210) | 10/10 |
| `tests/fixtures/ai-sidecar/chat-stream-agentic.txt` (NEW) | Test fixture | — | `chat-stream.txt` / `chat-stream-newlines.txt` | 10/10 |
| `ceit-ai-sidecar/app/main.py` (MODIFY) | API endpoints | HTTP → engine/rag | itself — `chat_stream()` (123-157), `_reject_unknown` (63-67) | 10/10 |
| `ceit-ai-sidecar/app/search.py` (MODIFY) | Search engine | docs → filtered RRF | itself — `passes()` (109-125) | 10/10 |
| `ceit-ai-sidecar/app/agent.py` (NEW) | Sidecar module — agentic loop | LLM tool calls → `rrf_search` | **partial** — `rag.py` `RagService` client mgmt (97-117) + `stream_events` framing (156-181); loop itself from RESEARCH §Code Examples 3-4 | 7/10 (novel control flow) |
| `ceit-ai-sidecar/tests/conftest.py` (MODIFY) | Test fixtures | corpus JSON | itself — `make_corpus` (43-132), `build_test_index` (165-172) | 10/10 |
| `ceit-ai-sidecar/tests/test_filters.py` (MODIFY) | Unit test | engine-level | itself — filter tests (39-75) | 10/10 |
| `ceit-ai-sidecar/tests/test_api.py` (MODIFY) | API test | TestClient | itself — 422 posture (85-105) | 10/10 |
| `ceit-ai-sidecar/tests/test_chat_stream.py` (MODIFY) | API test | TestClient + Fake LLM | itself — `FakeCompletionsHolder` (57-59), `make_client` (72-105) | 10/10 |
| `ceit-ai-sidecar/tests/test_agentic_loop.py` (NEW) | Unit test | Fake LLM → loop assertions | `test_chat_stream.py` fakes + `test_filters.py` `_build_index` | 9/10 |

---

## Pattern Assignments

### 1. `app/Services/CorpusExporter.php` — rich paper doc (D-01..D-03)

**Analog:** itself (current shape is already one-doc-per-paper with all fields; D-02 = remove title-doubling at line 26 + normalize segments).

**Imports** (lines 5-8): `use App\Models\AcademicPaper;` `use Illuminate\Support\Facades\File;` — no new imports needed.

**Core pattern** (lines 12-24): eager-load all names in one query —
```php
return AcademicPaper::with(['authors', 'researchAdviser', 'technicalAdviser', 'dean'])
    ->get()
    ->map(function (AcademicPaper $paper) {
        $title = $this->sanitize($paper->title, 500);
        $authors = $paper->authors->pluck('name')->map(fn ($name) => $this->sanitize($name, 200))->all();
        $researchAdviser = $paper->researchAdviser ? $this->sanitize(...) : null;  // lines 22-24 same for technicalAdviser/dean
```

**The ONLY change** (line 26): `$segments = [$title.'.', $title.'.'];` → `$segments = [$title.'.'];`. Segments 27-34 (`authors:`/`research_adviser:`/`technical_adviser:`/`dean:`/`department:`/`publication_year:`/`paper_type:`/`catalog_code:`) stay verbatim — they already compose the rich text.

**Untouched contract** (lines 41-51): `metadata` block keys are the filter + citation contract (`catalog_code`, `department`, `publication_year` int, `paper_type`, `authors` array, `research_adviser`/`technical_adviser`/`dean`, `url` `/academic-papers/{id}`). Do NOT reorder/rename.

**Sanitization** (lines 141-154): `sanitize()` strips control chars, collapses whitespace, caps at `$maxLen`. All dynamic strings pass through it.

**Envelope** (lines 127-139): `writeEnvelope()` — `schema_version` stays `1` (re-export rides the versioned atomic rebuild).

### 2. `app/Services/AiService.php` — `chatStreamFrames()` + filter passthrough

**Analog:** itself. `search()` already forwards `filters` verbatim (lines 27-36) — **zero change for author/adviser passthrough**; add only `chatStreamFrames()`.

**Core pattern to copy — the SSE read loop** (lines 87-140): `chatStreamEvents()` is the exact skeleton for `chatStreamFrames()`:
```php
$stream = $response->resource();
while (! feof($stream)) {
    $line = fgets($stream);
    if ($line === false) break;
    $line = rtrim($line, "\r\n");
    if (str_starts_with($line, 'data: ')) { ... '[DONE]' return ... json_decode chunk envelope ... yield }
    if ($line === 'event: error') { $dataLine = fgets($stream); ... throw AiServiceProviderException ... }
}
```

**Additive branch** (insert inside the loop, RESEARCH §Code Examples 6, after the `data:` block):
```php
if ($line === 'event: activity' || $line === 'event: citations') {
    $dataLine = fgets($stream);
    if ($dataLine !== false && str_starts_with($dataLine, 'data: ')) {
        $payload = json_decode(trim(substr($dataLine, 6)), true);
        yield ['type' => $line === 'event: activity' ? 'activity' : 'citations', 'payload' => $payload];
    }
    continue;
}
```
**Constraint:** keep `chatStreamEvents()` untouched — existing callers/tests depend on raw-chunk yields (AiServiceChatTest.php:59-72). `chatStreamFrames()` is a NEW method; `chatStream()` (54-73) unchanged (`retries: 0` — pitfall #8, no HTTP retries on the agentic path).

**Constants** (lines 19-25): `SSE_CHUNK_KEY = 'c'` — keep; the frame parser decodes the same envelope.

**Error taxonomy** (lines 185-196): `throwUnlessOk()` — 401 → `AiServiceAuthException`, failed → `AiServiceUnavailableException`; `event: error` (118-127) → `AiServiceProviderException`. All three extend `AiServiceException` with `errorCode()` (`auth_failed`/`unavailable`/`provider_error`, asserted at AiServiceChatTest.php:204-209). Frame types `activity`/`citations` must NOT bypass this taxonomy (RESEARCH Security).

### 3. `app/Livewire/Pages/Student/AcademicPaperIndex.php` — paper tab

**Analog:** itself (mode-composition is this file's established pattern).

**New props** — copy the validated-filter block (lines 41-57):
```php
#[Validate('string|max:100|nullable')]
public string $authorFilter = '';
#[Validate('string|max:100|nullable')]
public string $adviserFilter = '';
public bool $paperTabActive = false;   // UI-SPEC §Paper Search Tab
```

**Filters passthrough** — extend the array in `runHybridSearch()` (lines 311-317):
```php
$filters = [
    'paper_type' => $this->paperTypeFilter ?: null,   // ...
    'author' => $this->authorFilter ?: null,          // NEW
    'adviser' => $this->adviserFilter ?: null,        // NEW
];
```
`AiService::search($this->search, $filters, 'catalog', 10)` (line 320) unchanged — RESEARCH verified filters forward verbatim.

**Mode-composition rules (copy exactly):**
- `updatedStatusFilter()` (239-247) → `exitHybridMode()` — keep; author/adviser get the **updatedYearFilter-style** handlers (249-254: `exitRecommendationsMode(); resetPage(); runHybridSearch();`) — NOT force-exit (pitfall #3).
- `clearFilters()` (285-297) — add `'authorFilter', 'adviserFilter'` to the `reset([...])` list.
- `runHybridSearch()` gate (305-309): `strlen(trim($this->search)) < 3 || $this->statusFilter !== ''` → exitHybridMode. Author/adviser refine; they don't replace the topic query.
- Rank-order preservation (328-353): `findMany($ids)` + `keyBy` + re-map in sidecar order — reuse verbatim (hybrid results render through the SAME `hybridResults` path, so blade changes are only the tab strip + idle empty state).

**Snapshot pattern** — mirror `showSimilar`/`backToResults` (376-434) for tab state if planner adds an enter/leave snapshot: capture `search`, all filter props, `hybridResults`, `aiSearchFailed`, page, `sortBy` + new `authorFilter`/`adviserFilter`/`paperTabActive`; restore without re-querying (SimilarTest asserts `Http::recorded()` unchanged, 140-152).

**New computeds** — copy `availableDepartments()` (204-213) shape:
```php
#[Computed(persist: true, cache: true)]
public function availableAuthors() { return Author::distinct()->orderBy('name')->pluck('name')->filter()->values(); }
// availableAdvisers(): union of ResearchAdviser::orderBy('name')->pluck('name') + TechnicalAdviser::... , distinct, values
```
`ResearchAdviser`/`TechnicalAdviser`/`Dean` models are `HasFactory` name-only tables (ResearchAdviser.php:8-22).

**Availability** (442-453): `availability()` computed already merges `academicPapers` + `hybridResults` + `recommendations` — paper tab results ride `hybridResults` → zero change (shared-card path, D-04).

### 4. `academic-paper-index.blade.php` — tab strip + idle empty state

**Analog:** itself + `admin-advisers-deans.blade.php`.

**Tab strip** — insert between header block (25-28) and `<x-academic-paper-filters>` (31-35); copy the repo's only tab pattern (`admin-advisers-deans.blade.php:32-53`):
```blade
<div role="tablist" class="tabs tabs-boxed mb-6 bg-base-200 w-max">
    <a role="tab" class="tab {{ ! $paperTabActive ? 'tab-active' : '' }}" wire:click="$set('paperTabActive', false)">Browse</a>
    <a role="tab" class="tab {{ $paperTabActive ? 'tab-active' : '' }}" wire:click="$set('paperTabActive', true)">Paper Search</a>
</div>
```
(UI-SPEC §Paper Search Tab; add `w-max` — the admin version has no `w-max` but the spec's copy does.)

**Overlay targets** — append `paperTabActive, authorFilter, adviserFilter` to both `wire:target` lists (lines 240, 432).

**Idle empty state** — inside the mobile hybrid `@empty` and desktop equivalents, when `$paperTabActive && ! $search && ! $authorFilter && ! $adviserFilter` render the idle copy instead of "No Academic Papers Found" (UI-SPEC Empty States): `<x-empty-state icon="o-magnifying-glass" title="Search the paper collection" message="Type a topic, or choose an author or adviser to find papers." :show-action="false" size="sm" />` — reuse the exact `x-empty-state` markup shape at 320-326.

**Result rendering:** zero changes — mobile `block xl:hidden` (237-426), desktop hybrid grid (439-522) untouched (D-06 no sort control; add the `Results ranked by relevance` caption per UI-SPEC).

### 5. `components/academic-paper-filters.blade.php` — author/adviser selects

**Analog:** itself.

**Select markup** — copy the department select (108-113) for the two new controls (UI-SPEC §Filter Controls), appended after Department inside grid (line 91 `grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3`):
```blade
<select wire:model.live="authorFilter" class="select select-bordered select-sm sm:select-md w-full">
    <option value="">All Authors</option>
    <template x-for="author in availableAuthors" :key="author">
        <option :value="author" x-text="author"></option>
    </template>
</select>
```
(adviserFilter identical — `availableAdvisers`, `All Advisers`.)

**Blade data wiring:** `availableAuthors`/`availableAdvisers` must be passed into the component like the existing three at academic-paper-index.blade.php:31-35 (`:availableAuthors="$this->availableAuthors"`) and assigned in `init()` (filters.blade.php:14-19) + new Alpine `availableAuthors: null` props (9-12).

**Active badges** — `hasActiveFilters` getter (21-23): add `|| $wire.authorFilter || $wire.adviserFilter`; badge spans (147-154 pattern): `Author: {name}` / `Adviser: {name}` with `badge badge-sm` styling; `clearFilters()` handles reset on the PHP side (Assignment 3).

**Gate on paper tab:** component is shared with admin — guard the new controls on `$wire.paperTabActive` (UI-SPEC Reusable Assets) since admin page has no author/adviser props.

### 6. `app/Livewire/ChatWidget.php` — agentic frame handling

**Analog:** itself.

**`streamQuestion()` core loop** (124-198) — the skeleton stays; the change is inside the `foreach` (170-173):
```php
$accumulated = '';
$citations = $this->companionCitations($question);   // 155 — KEEP as fallback
try {
    $svc = new AiService;
    $response = $svc->chatStream($question, 'citations', null, 5);   // 159 — unchanged
    $this->stream('<span class="inline-flex gap-1 py-1">…dots…</span>', false, 'ans');  // 164-168
    foreach ($svc->chatStreamEvents($response) as $chunk) {           // 170 — swap to chatStreamFrames()
        $accumulated .= $chunk;
        $this->stream($chunk, false, 'ans');
    }
```
Replace with `chatStreamFrames()` iteration: `activity` frames → `$this->stream($line, false, 'activity')` on the NEW slot; `chunk` frames → `$this->stream($chunk, false, 'ans')`; `citations` frame → use as the payload for `assistantBubble()` + `Message::create` (178-185) — **only if present, else existing `companionCitations()` fallback** (RESEARCH §Recommended structure 6, A-7). UI-SPEC: also stream bounce-dots into `activity` slot first (masks first-call latency).

**Back-compat:** non-agentic streams (no activity frames) must keep today's exact behavior — `chatStreamEvents()` still yields raw chunks (Assignment 2).

**Error handling** (188-194): `catch (AiServiceException $e)` → failed bubble with `errorCode()`; the fail-closed refusal text arrives as a NORMAL chunk stream ("I don't have enough information to answer that." — render as normal assistant bubble, NOT error bubble; UI-SPEC Copywriting).

**Citation shape** — `companionCitations()` (237-263) builds ADR 0006 payload `{n, id, corpus, title, url, catalog_code}` from `/search` results; agentic frame payload arrives in the same shape (sidecar `citation_payload`, RESEARCH §Code Examples 7) → reuse `assistantBubble()` + chips partial untouched.

### 7. `chat-widget.blade.php` — activity slot (W-3 hazard)

**Analog:** itself (lines 67-83 is THE pattern; UI-SPEC §Agentic Loop Activity Lines provides the exact markup).

**Second persistent slot** — mount immediately ABOVE the `ans` slot (insert before line 79):
```blade
<div class="flex justify-start">
    <div class="bg-base-200 text-base-content rounded-2xl rounded-bl-sm px-4 max-w-[85%]">
        <div wire:stream="activity" class="text-xs py-2 empty:py-0 empty:invisible space-y-1"></div>
    </div>
</div>
```
**HAZARD (verbatim comment 67-78):** `:empty` matches only truly empty elements — keep the `wire:stream` element on a SINGLE line; any wrapped formatting with whitespace resurfaces the idle-bubble bug (pitfall #7).

**Error bubble** (56-61) untouched — loop failure renders through it; activity slot re-renders away with the bubble.

### 8-12. Laravel test files — copy patterns

**`ExportAiCorpusTest.php`** — update `it_exports_catalog_documents_with_locked_schema` (60-91): `seedPaper()` (28-49) already creates all names; flip line 87 `assertStringStartsWith($paper->title.'. '.$paper->title.'. ', ...)` → single-title prefix, and add `assertStringContainsString('technical_adviser: …')`/`publication_year: 2025`/`catalog_code: …` text asserts. Fixture unlink in `setUp()` (20-26), `readExport()` (51-58) reused.

**`AiServiceTest.php`** — new `it_passes_author_and_adviser_filter_keys`: copy 21-46 exactly (config token, `Http::preventStrayRequests()`, fake `search.json`, `Http::assertSent` closure) asserting `$request['filters'] === ['author' => ..., 'adviser' => ...]` and top-level keys exactly `['query','filters','corpus','limit','k']` (line 41).

**`AiServiceChatTest.php`** — new `it_parses_activity_and_citations_frames`: inline SSE body like 81-86 (event-lines + `[DONE]`), assert `chatStreamFrames()` yields typed frames; **back-compat assert**: `chatStreamEvents()` (58-72) still yields raw chunks on the same fixture. Fixture helper (16-19), `Http::preventStrayRequests` + Content-Type `text/event-stream` headers (28) reused.

**`ChatWidgetTest.php`** — new agentic cases: copy the send pattern (86-110): `config token`, `Http::fake` with BOTH `/search` (emptySearchResponse, 30-38) and `/chat/stream` (new fixture `chat-stream-agentic.txt`), `Livewire::test(...)->set('draft', ...)->call('send')`, then assert stream slot content via `assertSeeHtml`, persisted `citations` via `Message::where('role','assistant')->first()->citations` (339-350 pattern), and refusal-as-normal-bubble (417-438 pattern). **Wave-0 gap #2:** create `tests/fixtures/ai-sidecar/chat-stream-agentic.txt` mirroring `chat-stream.txt` + `event: activity`/`event: citations` lines per RESEARCH §Code Examples 5.

**`AcademicPaperIndexHybridTest.php`** — new cases copy `it_forwards_filter_values_to_the_sidecar` (121-140) for author/adviser keys; force-exit case copy 182-210 (`set('statusFilter', ...)` → `hybridResults` null while `paperTabActive` true); snapshot case copy SimilarTest 122-153 (`Http::recorded()` count unchanged).

### 13. `ceit-ai-sidecar/app/search.py` — additive filter clauses

**Analog:** itself — `passes()` (109-125) is the exact insertion point; additive clauses only (RESEARCH §Code Examples 2):

```python
AUTHOR_KEYS = ("research_adviser", "technical_adviser")

def passes(doc: dict) -> bool:
    meta = doc.get("metadata") or {}
    f = filters or {}
    # ... existing paper_type / department / publication_year / year_from / year_to (112-121) ...
    if f.get("author"):
        needle = str(f["author"]).lower()
        if not any(needle in str(name).lower() for name in (meta.get("authors") or [])):
            return False
    if f.get("adviser"):
        needle = str(f["adviser"]).lower()
        if not any(needle in str(meta.get(k) or "").lower() for k in AUTHOR_KEYS):
            return False
    return not (corpus and doc.get("corpus") != corpus)
```
Semantics locked by RESEARCH: case-insensitive **substring** (A-3, matches Laravel `scopeSearch` LIKE behavior, AcademicPaper.php:97-115); adviser matches research OR technical (A-2); `str()`-guard every value (pitfall #5 — agent junk args); `authors` is an array (pitfall #6 — CorpusExporter.php:46). Placement inside `passes()` means filters apply pre-fusion (verified test_filters.py:65-75 — no signature changes to `rrf_search` needed).

### 14. `ceit-ai-sidecar/app/main.py` — agentic routing

**Analog:** itself. **No top-level contract change** — `SEARCH_ALLOWED_KEYS` (59) and `CHAT_ALLOWED_KEYS` (60) untouched (A-5; new filters ride inside the permissive `filters` dict, main.py:106).

**Change:** `chat_stream()` (123-157) body: after validation (131-148 verbatim — `_reject_unknown` 63-67, `_require_query` 70-74, mode/corpus/top_k checks), replace the one-shot `rrf_search` + `stream_events` (150-151) with the agentic entry: first non-streamed tool-eligible LLM call → loop → final `stream_events`-style answer. Keep: `StreamingResponse` + headers (153-157), `event: error` taxonomy, 401 middleware (77-90), 422 `_invalid` envelope (52-56).

### 15. `ceit-ai-sidecar/app/agent.py` (NEW) — the agentic loop

**No direct analog — compose from `rag.py` + RESEARCH §Code Examples 3-4 + `search.py` seam.**

**Imports/clients — copy `RagService`** (rag.py:97-117): constructor injects `client/base_url/api_key/model/max_tokens` with `settings` fallbacks; `_ensure_client()` lazy `OpenAI(base_url=..., api_key=...)`. Agent module takes the same injectable-client shape so tests can pass `FakeCompletionsHolder`.

**Tool spec** — RESEARCH §Code Examples 3 `SEARCH_TOOL` verbatim (single `search` function; `additionalProperties: false`); server-side validation via pydantic model with `extra="forbid"` (D-09/security: model output → search input).

**Loop shape** — RESEARCH §Code Examples 4:
```python
MAX_TOOL_ROUNDS = 3                      # D-11 — counts EXECUTED searches
while True:
    resp = client.chat.completions.create(model=..., messages=messages, tools=[SEARCH_TOOL], tool_choice="auto", max_tokens=...)
    msg = resp.choices[0].message
    if not msg.tool_calls:               # D-07 auto-detect — no classifier
        → stream final answer / refusal
    if rounds >= MAX_TOOL_ROUNDS:        # cap → fail-closed
        → stream answer from docs or refusal
    args = ToolArgs.model_validate_json(call.function.arguments)   # extra="forbid"
    yield activity frame; results = rrf_search(query, k=60, limit=top_k, filters=..., corpus=..., include_text=True)
    docs = merge_dedup(docs, results); messages.append(tool call + result); rounds += 1
```

**SSE framing — copy `RagService.stream_events()`** (156-181): empty-docs refusal = `data: I don't have enough information\n\n` + `data: [DONE]\n\n` (zero LLM calls — 166-169); chunk envelope `json.dumps({CHUNK_KEY: delta})` (172-173 — `CHUNK_KEY = "c"` rag.py:69); provider exception → `event: error` + `data: {code: provider_error...}` (174-180); `data: [DONE]\n\n` terminator (181). **New additive frames:** `event: activity\ndata: {"text": "…"}` and `event: citations\ndata: [{n,id,corpus,title,url,catalog_code}]` emitted BEFORE the final `[DONE]` (RESEARCH §Code Examples 5+7; citations = merged deduped doc set renumbered 1..N in merge order — pitfall #2).

**Final answer prompt:** reuse `PROMPTS["citations"]` + `build_context()` (rag.py:39-51, 77-85) over the merged docs (ADR 0003).

### 16-20. Sidecar test files

**`conftest.py`** — update `make_corpus` (43-132) doc `text` shapes (lines 58, 71, 84, 97) to the NEW rich single-title shape (drop the doubled title, add `research_adviser:`/`technical_adviser:`/`dean:`/`catalog_code:` segments) + add `research_adviser`/`technical_adviser`/`dean` metadata keys so filter tests exercise real fields. **Wave-0 gap #1:** must land in the same commit as the Laravel exporter change. `DeterministicEmbedder` (22-40), `embed_from` (150-162), `build_test_index` (165-172) untouched.

**`test_filters.py`** — new `test_filter_author_any_of_multiple` / `test_filter_adviser_research_or_technical`: copy `_build_index` (12-36) + `test_filter_paper_type_and_year_range` (49-63) shape — `filters={"author": "juan"}` → all results contain case-insensitive match in `metadata.authors`; `filters={"adviser": "engr. jose"}` matches docs keyed by either adviser role.

**`test_api.py`** — new `test_search_endpoint_accepts_author_adviser_filters`: copy client fixture (16-53) + 200-shape assert (68-77) with author/adviser filters; and a posture assert: unknown **top-level** field still 422 (copy 85-94 verbatim).

**`test_chat_stream.py`** — extend with activity/citations frame asserts using the EXISTING fakes: `FakeCompletions` (23-54) + `FakeCompletionsHolder` (57-59) + `FakeEngine` (62-69) + `make_client` (72-105). Agentic tests in `test_agentic_loop.py` reuse this exact fake stack: `FakeCompletions.create()` records kwargs (29-31) → assert `tools=[SEARCH_TOOL]`, `tool_choice="auto"`; add a `tool_calls` variant to the fake (extend `_response`, 49-54) returning `message.tool_calls` — the only new fake surface needed.

**`test_agentic_loop.py`** (NEW) — direct `AgenticLoop`/module-level unit tests with injected fake client + `FakeEngine`-style rrf_search double; cases from RESEARCH Validation Architecture: direct-answer-no-search (zero `rrf_search` calls), tool-call→search→answer, 3-round cap, malformed-args-correct-once-then-refuse, frame ordering (activity before chunks before citations before `[DONE]`).

---

## Shared Patterns

1. **Closed-schema validation both sides** — sidecar `_reject_unknown` (main.py:63-67) + Laravel payload-key assertion `array_keys($request->data()) === [...]` (AiServiceTest.php:41, ChatWidgetTest.php:303). New filter keys live INSIDE `filters` (permissive); no top-level key changes (A-5).
2. **`Http::fake` capture tests** — `Http::preventStrayRequests()` + `Http::assertSent` closure over `$request->data()` (AiServiceTest.php:26-45; AcademicPaperIndexHybridTest.php:126-140; ChatWidgetTest.php:301-318).
3. **SSE framing contract** — JSON chunk envelope `{"c": "<delta>"}` (`SSE_CHUNK_KEY` AiService.php:25 ↔ `CHUNK_KEY` rag.py:69); `[DONE]` terminator; `event: error` taxonomy (rag.py:174-180 ↔ AiService.php:118-127); NEW additive `event: activity` / `event: citations` never wrapped in data envelopes (pitfall #1 — parser fall-through renders raw JSON).
4. **Mode composition** — snapshot-and-restore (AcademicPaperIndex.php:376-434), force-exit on status filter (239-247), per-filter `updated*()` handlers (249-282); paper tab follows all three.
5. **Availability hydration reuse** — `availability()` merges all result sets (AcademicPaperIndex.php:442-453); `availabilityMap()` grouped per-render (ChatWidget.php:284-322) feeds citation chips; never sent to the sidecar (asserted, ChatWidgetTest.php:303-305).
6. **Fail-closed refusal** — zero-token path `data: I don't have enough information` (rag.py:166-169); ADR 0006 grounding; renders as normal bubble, not error.
7. **W-3 persistent stream slot** — single-line `wire:stream` element + `empty:py-0 empty:invisible` (chat-widget.blade.php:67-83); new `activity` slot duplicates it exactly.
8. **Deterministic LLM/embedder fakes** — `FakeCompletionsHolder` (test_chat_stream.py:57-59), `DeterministicEmbedder` (conftest.py:22-40), `embed_from` (conftest.py:150-162) — no network in unit tests.

---

## PATTERN MAPPING COMPLETE
**Phase:** 11 - Academic Papers & Agentic Search
**Files analyzed:** 30
**Analogs found:** 20/21 (1 partial: `app/agent.py` — composed from `rag.py` client/framing patterns + RESEARCH §Code Examples 3-4)
**File created:** .planning/phases/11-academic-papers-agentic-search/11-PATTERNS.md
