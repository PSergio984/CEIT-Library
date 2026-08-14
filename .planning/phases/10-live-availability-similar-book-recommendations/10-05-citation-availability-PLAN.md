---
phase: 10-live-availability-similar-book-recommendations
plan: 05
type: execute
wave: 2
depends_on: ["10-01", "10-02"]
files_modified:
  - app/Livewire/ChatWidget.php
  - resources/views/livewire/chat-widget.blade.php
  - resources/views/livewire/chat-widget-citations.blade.php
  - tests/Feature/ChatWidgetTest.php
autonomous: true
requirements: [SEARCH-02]
must_haves:
  truths:
    - "Catalog citation chips render a compact 'available/total' suffix beside catalog_code with a color cue — text-success when ≥1 available, text-error at 0; policy chips unchanged (no availability concept); the sources partial is untouched (D-04, ADR 0010)"
    - "The availability map is computed ONCE per render across ALL messages' catalog citations (one AvailabilityService::forPapers call — no N+1) and passed to the chips partial as an extra include variable at chat-widget.blade.php:52; the persisted 6-key citations payload in ai_messages.citations is NEVER modified (D-03/D-04 — guarded by the untouched it_persists_citation_payload assertSame)"
    - "chatStream request bodies carry exactly {query, mode, top_k} (corpus omitted when null) and /search companion bodies carry exactly {query, filters, corpus, limit, k} — no availability keys anywhere (D-06/D-07 #1 chat side)"
  artifacts:
    - path: app/Livewire/ChatWidget.php
      provides: "#[Computed] availabilityMap — collects paper-N ids across all messages, one grouped forPapers call, re-keyed by catalog_code"
      contains: "availabilityMap"
    - path: resources/views/livewire/chat-widget-citations.blade.php
      provides: "Render-time suffix span (text-success/text-error) on catalog chips only — reads the extra $availability variable"
      contains: "text-success"
    - path: resources/views/livewire/chat-widget.blade.php
      provides: "Include site passes 'availability' => $this->availabilityMap to the chips partial only (line 52); sources partial unchanged"
      contains: "availabilityMap"
  key_links:
    - from: app/Livewire/ChatWidget.php
      to: app/Services/AvailabilityService.php
      via: "availabilityMap() derives int ids with str_replace('paper-', '', $c['id']) and calls forPapers() once (10-02 service)"
      pattern: "forPapers"
    - from: resources/views/livewire/chat-widget-citations.blade.php
      to: app/Livewire/ChatWidget.php
      via: "the suffix span looks up $availability[$c['catalog_code']] — keyed by the persisted catalog_code"
      pattern: "catalog_code"
---

<objective>
Complete SEARCH-02's chat surface (D-03 call site #1, D-04 chip suffix, D-07 #1/#2/#5 chat side): hydrate citation chips with live availability at render time. Add a `#[Computed] availabilityMap` to `ChatWidget` that collects every catalog citation's `paper-N` id across ALL messages in one grouped `AvailabilityService::forPapers()` call (10-02) and re-keys by the persisted `catalog_code`; pass it as an extra include variable at `chat-widget.blade.php:52` (chips partial ONLY — the sources partial at line 53 and the persisted 6-key `citations` payload stay untouched, enforced by the exact-shape test); render the compact "2/3"-style suffix with green/red color cue on catalog chips only in `chat-widget-citations.blade.php`; and ship the capture/render tests: chatStream + companion `/search` bodies carry exactly the ADR 0004 key sets with no availability keys, chips render the suffix with correct colors, policy chips stay bare, and `it_persists_citation_payload` remains untouched and green.

Purpose: SEARCH-02's availability on cited books — resolved from Inventory post-response, never persisted, never in sidecar payloads (10-01 + capture tests complete the never-LLM chain).

Output: Green extended ChatWidgetTest (14 existing + new), chips showing live "X/Y", persisted payload shape provably unchanged.

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
@docs/adr/0006-citation-and-grounding-rules.md
@docs/adr/0004-sidecar-chat-endpoint-contract.md
@app/Livewire/ChatWidget.php
@app/Services/AvailabilityService.php
@resources/views/livewire/chat-widget.blade.php
@resources/views/livewire/chat-widget-citations.blade.php
@tests/Feature/ChatWidgetTest.php
@tests/fixtures/ai-sidecar/search.json
</context>

<threat_model>
ASVS L1. Block on HIGH severity threats. This plan adds a render-time hydration path to a persisted message payload.

| Threat | Severity | Mitigation in this plan |
|---|---|---|
| T-01 Enrichment written into the persisted `ai_messages.citations` payload — breaks the 6-key contract (D-03/D-04) and the exact-shape guard | HIGH | The map is computed from `$this->messages` and passed ONLY as an extra include variable at the include site; no write-back into `$m['citations']` and no column update. `it_persists_citation_payload` (assertSame on the 6 keys) is left untouched and must stay green — it is the guard. |
| T-02 Availability computed per message (N+1 — what D-02 forbids) | MED | One `#[Computed] availabilityMap` iterates ALL messages once, dedupes ids, and makes a single `forPapers()` call. The capture test proves exactly one `/search`-independent DB call via the map shape. |
| T-03 Stale counts (cached map) | MED | Plain `#[Computed]` (recomputed per render) — NOT `persist/cache` variants. |
| T-04 Suffix leaks onto policy chips or the Sources list | MED | The suffix span renders only inside the catalog-link `@if ($c['corpus'] === 'catalog' && $c['url'])` branch; the sources partial at line 53 is untouched; the policy render test asserts the suffix is absent from policy chips. |
| T-05 Malformed citation ids (policy ids like `policy-h2-r1`) crash the map | MED | `str_replace('paper-', '', $c['id'])` results are int-cast and filtered; non-numeric ids are skipped by `forPapers`'s own int-filter (10-02 Task 1) — the map simply has no entry for them. |

No HIGH-severity threat is left without a mitigation — nothing blocks this plan.
</threat_model>

<tasks>

<task type="auto">
  <name>Task 1: #[Computed] availabilityMap in ChatWidget — one grouped call across all messages</name>
  <files>app/Livewire/ChatWidget.php</files>
  <read_first>app/Livewire/ChatWidget.php (message shape at 23-24, openConversation history render at 47-63, companionCitations payload at 234-260 — ids are `paper-N` strings, catalog_code in metadata), app/Services/AvailabilityService.php (forPapers from 10-02), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.5), 10-RESEARCH.md (landmine #4 — one call per render)</read_first>
  <action>
  - Add `use App\Services\AvailabilityService;`.
  - Add `#[Computed] public function availabilityMap(): array` (plain computed — recomputed per render, never persisted):
    1. Walk `$this->messages`; for each assistant message's `citations` array, keep rows where `$c['corpus'] === 'catalog'`.
    2. Derive the int id: `(int) str_replace('paper-', '', $c['id'])` — filter out ids that are 0 or non-numeric after the strip (policy ids like `policy-h2-r1` become non-int and drop out); collect unique ids.
    3. Empty ids → `return [];` (no query).
    4. `$hydrated = (new AvailabilityService)->forPapers($ids);`
    5. Re-key BY `catalog_code` (the persisted key the chips partial can look up): iterate the catalog citations again, build `[catalog_code => $hydrated[intId]]` for entries where `catalog_code` is non-empty AND the id is present in `$hydrated` (papers without inventory rows stay absent — chips render no suffix for them).
    6. Return the map; do NOT write back into `$this->messages` or any persisted payload (D-03/D-04).
  </action>
  <verify>php -l app/Livewire/ChatWidget.php</verify>
  <acceptance_criteria>
  - `availabilityMap()` exists as a plain `#[Computed]`, derives ids via `str_replace('paper-', '', $c['id'])`, makes exactly ONE `forPapers($ids)` call per render, and returns a map keyed by `catalog_code`
  - `$this->messages` is only read, never written, inside the computed; no `Message` update/citation write anywhere in the task
  - `php -l` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 2: Chips suffix markup + include-site variable</name>
  <files>resources/views/livewire/chat-widget-citations.blade.php, resources/views/livewire/chat-widget.blade.php</files>
  <read_first>resources/views/livewire/chat-widget-citations.blade.php (catalog chip markup at lines 3-7 — catalog_code mono span; policy chip at 9-11), resources/views/livewire/chat-widget.blade.php (include site at 51-54), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.5)</read_first>
  <action>
  - In `chat-widget.blade.php` line 52 ONLY (the chips include): change `@include('livewire.chat-widget-citations', ['citations' => $m['citations']])` to pass the map: `@include('livewire.chat-widget-citations', ['citations' => $m['citations'], 'availability' => $this->availabilityMap])`. Leave line 53 (sources partial) and the persisted payload untouched.
  - In `chat-widget-citations.blade.php`, inside the catalog `<a>` chip AFTER the existing `<span class="opacity-60 font-mono">{{ $c['catalog_code'] }}</span>`: add `@if (! empty($availability[$c['catalog_code'] ?? null] ?? null))` → `<span class="{{ $availability[$c['catalog_code']]['available'] > 0 ? 'text-success' : 'text-error' }} font-medium">{{ $availability[$c['catalog_code']]['available'] }}/{{ $availability[$c['catalog_code']]['total'] }}</span>` — compact suffix, green ≥1 / red 0 (D-04).
  - The policy `@else` span branch (lines 9-11) stays byte-identical — no availability concept (D-04).
  - Guard: the `@if` uses `?? null` so citations whose paper has no Inventory rows (absent from the map) render without a suffix.
  </action>
  <verify>php artisan view:cache</verify>
  <acceptance_criteria>
  - `chat-widget.blade.php` line 52 passes `'availability' => $this->availabilityMap`; line 53 is unchanged
  - The chips partial contains the suffix span with the literal `'text-success' : 'text-error'` ternary inside a `{{ }}` and the `available`/`total` interpolation `{{ $availability[$c['catalog_code']]['available'] }}/{{ $availability[$c['catalog_code']]['total'] }}`
  - The policy branch in the partial is byte-identical to before (no suffix span inside `@else`)
  - `php artisan view:cache` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 3: ChatWidgetTest — exact-key capture deltas (D-07 #1 chat side)</name>
  <files>tests/Feature/ChatWidgetTest.php</files>
  <read_first>tests/Feature/ChatWidgetTest.php (the binding test it_binds_companion_search_to_chat_parameters at 254-287 and it_persists_citation_payload at 309-318 — do NOT touch the latter), .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.8)</read_first>
  <action>
  - Extend the existing `it_binds_companion_search_to_chat_parameters` test's `Http::assertSent` closures (or add sibling assertions in the same test):
    - `/chat/stream` body: add `array_keys($request->data()) === ['query', 'mode', 'top_k']` (corpus null → omitted per `AiService.php:56-60`) and `! array_key_exists('available', $request->data())`.
    - companion `/search` body: add `array_keys($request->data()) === ['query', 'filters', 'corpus', 'limit', 'k']` and `! array_key_exists('available', $request->data())`.
  - Add a focused test `it_never_sends_availability_keys_to_the_sidecar` if the closures can't be extended cleanly — same assertions, explicit about the never-LLM intent.
  - Do NOT modify `it_persists_citation_payload` (lines 309-318) — it already pins the exact 6-key shape via assertSame and is the D-07 #2 guard; leaving it untouched is itself an acceptance criterion.
  </action>
  <verify>php artisan test --filter=ChatWidgetTest</verify>
  <acceptance_criteria>
  - The chatStream capture asserts the exact key set `['query', 'mode', 'top_k']` and no `available` key; the companion `/search` capture asserts the exact key set `['query', 'filters', 'corpus', 'limit', 'k']` and no `available` key
  - `it_persists_citation_payload` body is byte-identical to before this task (diff the file)
  - `php artisan test --filter=ChatWidgetTest` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 4: ChatWidgetTest — chip suffix render tests (D-07 #5 chat side)</name>
  <files>tests/Feature/ChatWidgetTest.php</files>
  <read_first>tests/Feature/ChatWidgetTest.php (existing fake stack — actingAs, Http::preventStrayRequests, Http::fake for /search + /chat/stream with fixtures at 43-48; the seeding + Inventory pattern from AcademicPaperIndexHybridTest.php:187-191), database/factories/InventoryFactory.php, .planning/phases/10-live-availability-similar-book-recommendations/10-PATTERNS.md (section 2.9)</read_first>
  <action>
  - Extend `tests/Feature/ChatWidgetTest.php` with four tests (same discipline: actingAs user, config token, preventStrayRequests, both endpoints faked — `/search` → `search.json` fixture, `/chat/stream` → `chat-stream.txt` SSE). Seed paper 77 (`forceFill(['id' => 77])`) with explicit Inventory rows.
  - `it_renders_availability_suffix_on_catalog_chips`: paper 77 with 2 Available + 1 Unavailable rows; send a message → `assertSee('2/3')` and the rendered HTML contains a span with class `text-success` (assert via `assertSeeHtml` on the suffix span).
  - `it_renders_red_suffix_when_no_copies_available`: paper 77 with 0 Available + 3 Unavailable → `assertSee('0/3')` and `assertSeeHtml` a span with `text-error`.
  - `it_omits_suffix_when_paper_has_no_inventory_rows`: paper 77 with zero Inventory rows → `assertDontSee('0/0')` (absent map → no suffix, the `?? null` guard).
  - `it_keeps_policy_chips_without_availability`: fake `/search` returning a policy result (id `policy-h2-r1`, corpus `policy`, no catalog_code) → policy chip renders bare: `assertDontSeeHtml('<span class="text-')` inside the policy chip (or assert the specific catalog_code suffix pattern `'/` does not appear in the policy chip's markup), and `assertSee('(rulebook)')` behavior unchanged.
  - Add a single-query proof in `it_renders_availability_suffix_on_catalog_chips`: assert only ONE availability hydration happens for two messages citing the same paper — e.g. reuse `Http::sequence()` for two assistant turns and assert the DB query count via a counter around `forPapers` (or assert the computed produces the same map for both messages — implementation-level `assertSet('availabilityMap.77.available', 2)` is acceptable if the computed is public).
  </action>
  <verify>php artisan test --filter=ChatWidgetTest</verify>
  <acceptance_criteria>
  - All 4 new tests pass: `php artisan test --filter=ChatWidgetTest` exits 0 with 14 existing + new tests green
  - `it_renders_availability_suffix_on_catalog_chips` asserts the literal `2/3` and a `text-success` span; the red test asserts `0/3` and a `text-error` span; the no-rows test asserts no suffix; the policy test asserts the policy chip carries no availability markup
  - `it_persists_citation_payload` still passes untouched (the persisted payload never sees the suffix)
  - No real API key value; `Http::preventStrayRequests()` in every test
  </acceptance_criteria>
</task>

</tasks>

<verification>
- [ ] `php artisan test --filter=ChatWidgetTest` — 14 existing + 4 new (or 15) passing; `it_persists_citation_payload` untouched and green
- [ ] `php artisan test --filter=AvailabilityServiceTest` — wave-1 service seam still green
- [ ] `php artisan test` — full Laravel suite green (557 passed / 3 skipped baseline)
- [ ] `vendor/bin/pint app/Livewire/ChatWidget.php resources/views/livewire/chat-widget.blade.php resources/views/livewire/chat-widget-citations.blade.php tests/Feature/ChatWidgetTest.php` — exits 0
- [ ] Sidecar suite still green (`cd C:\Users\admin\Herd\ceit-ai-sidecar; uv run pytest`) — chatStream bodies from the widget carry exactly query/mode/top_k so the 10-01 closed schema never sees an unknown field
</verification>

<success_criteria>
- All 4 tasks complete
- SEARCH-02 chat half: catalog citation chips show live "X/Y" with green/red color cues, resolved from Inventory at render time — the persisted 6-key payload and the sources list are provably unchanged, and captured request bodies carry exactly the ADR 0004 keys
- D-07 chain complete across the phase: #1 both sides (10-02 + 10-05), #2 (existing guard untouched), #3 (10-01), #4 (10-02), #5 both surfaces (10-02 + 10-05)
</success_criteria>

<output>
After completion, create `.planning/phases/10-live-availability-similar-book-recommendations/10-05-SUMMARY.md`
</output>
