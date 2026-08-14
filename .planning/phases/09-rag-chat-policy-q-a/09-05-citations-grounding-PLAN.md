---
phase: 09-rag-chat-policy-q-a
plan: 05
type: execute
wave: 2
depends_on: ["09-01", "09-04"]
files_modified:
  - app/Livewire/ChatWidget.php
  - resources/views/livewire/chat-widget.blade.php
  - resources/views/livewire/chat-widget-citations.blade.php
  - resources/views/livewire/chat-widget-sources.blade.php
  - tests/Feature/ChatWidgetTest.php
autonomous: true
requirements: [SEARCH-03, SEARCH-04, CHAT-04]
must_haves:
  truths:
    - "Companion (new AiService)->search($query, [], null, 5) runs before chatStream with the SAME corpus (null = both) and top_k (5); payload [{n,id,corpus,title,url,catalog_code}] persists into ai_messages.citations (D-20, SEARCH-03)"
    - "Catalog citations render as link chips to url (route /academic-papers/{id}) with catalog_code; policy citations render as non-link chips; the full numbered Sources list renders under the answer (D-21/D-22)"
    - "Empty companion retrieval renders no chips and no Sources; the sidecar's refusal bubble (`I don't have enough information`) renders as a normal bubble — the sidecar stays the single refusal authority (D-23/D-24/D-29, SEARCH-04)"
    - "Policy corpus answers flow end-to-end: corpus stays unset (both corpora) so policy docs surface; policy citations render title-only non-link chips with the (rulebook) suffix (CHAT-04)"
  artifacts:
    - path: resources/views/livewire/chat-widget-citations.blade.php
      provides: "Citation chip partial (link vs non-link per corpus) — prototype markup normalized"
      contains: "catalog_code"
    - path: resources/views/livewire/chat-widget-sources.blade.php
      provides: "Numbered Sources list partial under the answer — prototype markup normalized"
      contains: "Sources"
  key_links:
    - from: app/Livewire/ChatWidget.php
      to: app/Services/AiService.php
      via: "companion search() call with explicit corpus null — search() defaults to 'catalog', the widget MUST pass null for both"
      pattern: "search("
---

<objective>
Wire SEARCH-03/SEARCH-04/CHAT-04 into the widget built in 09-04: run the companion `/search` call before streaming with the same query/corpus/top_k (binding to the sidecar's deterministic RRF ordering), map results to the locked citation payload `[{n, id, corpus, title, url, catalog_code}]`, persist it with the assistant message, render citation chips and the numbered Sources list (ADR 0006 markup), and handle empty retrieval + the sidecar refusal bubble. Ship the binding, payload, render, refusal, and policy-flow tests.

Purpose: This is the client half of grounding — every answer shows the exact numbered set the model worked from (SEARCH-03), refusal is honest and stylistically a normal bubble (SEARCH-04), and policy questions surface rulebook citations (CHAT-04). Runs after 09-04 because it modifies `send()` and the bubble rendering created there.

Output: Widget answers with chips + Sources, persisted citations, green extended `ChatWidgetTest`.

Commit discipline: each task is one focused commit.
</objective>

<execution_context>
@$HOME/.codex/get-shit-done/workflows/execute-plan.md
@$HOME/.codex/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/09-rag-chat-policy-q-a/09-RESEARCH.md
@.planning/phases/09-rag-chat-policy-q-a/09-CONTEXT.md
@.planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md
@.planning/phases/09-rag-chat-policy-q-a/09-VALIDATION.md
@docs/adr/0006-citation-and-grounding-rules.md
@docs/adr/0004-sidecar-chat-endpoint-contract.md
@app/Services/AiService.php
@tests/fixtures/ai-sidecar/search.json

> NOTE: The prototype partials (`resources/views/livewire/pages/prototype/chat-widget-citations.blade.php`, `resources/views/livewire/pages/prototype/chat-widget-sources.blade.php`) are NOT in the main working tree — they live only on branch `prototype/chat-widget` (tip 395244dc). Read them via `git show prototype/chat-widget:&lt;path&gt;` (or checkout that branch) before the branch is deleted.
</context>

<threat_model>
ASVS L1. Block on HIGH severity threats. This plan adds a second outbound sidecar call per turn and renders server-derived citation data.

| Threat | Severity | Mitigation in this plan |
|---|---|---|
| T-01 Citation payload URLs become an injection/redirect surface | MED | The payload is built ONLY from sidecar `/search` metadata (loopback, corpus-exporter-sourced — `/academic-papers/{id}`); no user input reaches the href; all titles/codes render via Blade `{{ }}` escaping. Policy rows never render hrefs (non-link chips). |
| T-02 Citation binding drift — chat and companion search use different corpus/top_k, silently breaking [N] markers | MED | Both calls locked to the same values (corpus null, top_k 5) in `streamQuestion()`; the binding test asserts both requests carry identical values (RESEARCH risk 14). |
| T-03 Ungrounded answers shown without sources | HIGH | Sources list is the full numbered set the model worked from (D-22); empty retrieval renders no sources and the sidecar refusal is the single authority (D-23); refusal render test proves no Sources label appears. |
| T-04 Companion-search failure kills the whole turn | MED | Companion `/search` failures are non-fatal — the turn continues with `citations => null` (no sources); only the chat stream failure marks the turn failed. |

No HIGH-severity threat is left without a mitigation — nothing blocks this plan.
</threat_model>

<tasks>

<task type="auto">
  <name>Task 1: Companion search wiring + citation payload persistence</name>
  <files>app/Livewire/ChatWidget.php</files>
  <read_first>app/Livewire/ChatWidget.php (streamQuestion from 09-04 Task 3), app/Services/AiService.php (search() signature + RRF_CANDIDATES), tests/fixtures/ai-sidecar/search.json (payload shape), .planning/phases/09-rag-chat-policy-q-a/09-RESEARCH.md (section 4.5)</read_first>
  <action>
  - In `streamQuestion()`, BEFORE the `chatStream` call, run the companion search and dereference the envelope: `try { $results = (new AiService)->search($question, [], null, 5)['results']; } catch (AiServiceUnavailableException|AiServiceAuthException) { $results = []; }` — `search()` returns the full envelope `{query, total, took_ms, contract_version, results}`, so the `['results']` dereference is REQUIRED: the payload build below and Task 3's `$results === []` check must iterate actual rows, not the envelope. corpus MUST be the explicit `null` (the method's default is `'catalog'`; the chat call searches both) and `top_k` must equal the chat call's 5 (D-20 binding).
  - Build the payload from results in rank order: `['n' => $i + 1, 'id' => $result['id'], 'corpus' => $result['corpus'], 'title' => $result['title'], 'url' => $result['metadata']['url'] ?? null, 'catalog_code' => $result['metadata']['catalog_code'] ?? null]` — `n` is the 1-based index of the result array (identical RRF order on both calls).
  - Persist the assistant Message with `citations => $payload` when results exist, else `citations => null`; attach the same array to the bubble's `citations` key for rendering.
  - Companion-search failure keeps `citations => null` and the turn continues (non-fatal, T-04).
  </action>
  <verify>php -l app/Livewire/ChatWidget.php</verify>
  <acceptance_criteria>
  - `streamQuestion()` issues `(new AiService)->search($question, [], null, 5)` before the chat stream call; `Http::assertSent` (test in Task 4) proves the `/search` body has `corpus` null, `limit` 5, `k` 60 and the `/chat/stream` body has the same query with `corpus` omitted and `top_k` 5
  - Assistant Messages persist `citations` as the locked payload array (or null on empty/failed companion search)
  - `php -l` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 2: Citation chips + Sources partials, wired into the bubble</name>
  <files>resources/views/livewire/chat-widget-citations.blade.php, resources/views/livewire/chat-widget-sources.blade.php, resources/views/livewire/chat-widget.blade.php</files>
  <read_first>resources/views/livewire/pages/prototype/chat-widget-citations.blade.php (verbatim verdict markup — NOT in working tree; branch prototype/chat-widget @ 395244dc — read via git show prototype/chat-widget:resources/views/livewire/pages/prototype/chat-widget-citations.blade.php), resources/views/livewire/pages/prototype/chat-widget-sources.blade.php (same branch — git show prototype/chat-widget:resources/views/livewire/pages/prototype/chat-widget-sources.blade.php or checkout that branch before it is deleted), .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.6)</read_first>
  <action>
  - Create `resources/views/livewire/chat-widget-citations.blade.php` (normalized from the prototype, no THROWAWAY comments): `@if ($c['corpus'] === 'catalog' && $c['url'])` → `<a href="{{ $c['url'] }}" class="inline-flex items-center gap-1 rounded-full border border-primary/40 bg-primary/5 text-primary px-2.5 py-0.5 text-[11px] hover:bg-primary/10">` with `[{{ $c['n'] }}] {{ $c['title'] }}` + `<span class="opacity-60 font-mono">{{ $c['catalog_code'] }}</span>`; `@else` → non-link `<span class="inline-flex items-center gap-1 rounded-full border border-base-300 bg-base-100 px-2.5 py-0.5 text-[11px] text-base-content/80">` with `[{{ $c['n'] }}] {{ $c['title'] }}` (D-21).
  - Create `resources/views/livewire/chat-widget-sources.blade.php` (normalized): dashed top border box with `Sources` label (`text-[10px] uppercase tracking-wide text-base-content/40`) and an `<ol>` of `[{{ $c['n'] }}]` + link (`link link-primary`, title + `· {{ $c['catalog_code'] }}` mono span) for catalog rows, or `{{ $c['title'] }} <span class="text-base-content/40">(rulebook)</span>` for non-catalog rows (D-22).
  - In `chat-widget.blade.php`, inside the assistant bubble after the content div: `@if (! empty($m['citations']))` → `@include('livewire.chat-widget-citations', ['citations' => $m['citations']])` then `@include('livewire.chat-widget-sources', ['citations' => $m['citations']])` `@endif`.
  </action>
  <verify>php artisan view:cache</verify>
  <acceptance_criteria>
  - Both partials exist at the locked paths; the chips partial contains the `catalog_code` mono span and the non-link `@else` branch; the sources partial contains the literal `Sources` label and the `(rulebook)` suffix span
  - `chat-widget.blade.php` includes both partials behind `@if (! empty($m['citations']))` inside the assistant bubble
  - `php artisan view:cache` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 3: Empty retrieval + refusal rendering</name>
  <files>app/Livewire/ChatWidget.php, resources/views/livewire/chat-widget.blade.php</files>
  <read_first>app/Livewire/ChatWidget.php (Task 1 state), .planning/phases/09-rag-chat-policy-q-a/09-RESEARCH.md (section 4.5), .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.5 failure/refused bubble)</read_first>
  <action>
  - Empty companion retrieval (`$results === []`) → assistant bubble keeps `citations => []`/null; the `@if (! empty($m['citations']))` guard already suppresses chips and Sources — no further view change needed.
  - The sidecar remains the single refusal authority (D-23): the widget still calls `chatStream()` when the companion search is empty; the sidecar's programmatic refusal (09-01 Δ4) streams back the canonical `I don't have enough information` as a normal assistant bubble (no `refused` flag, no special styling, no sources).
  - Verify by test that the refusal string arrives as bubble content and no Sources block renders.
  </action>
  <verify>php -l app/Livewire/ChatWidget.php</verify>
  <acceptance_criteria>
  - The widget never short-circuits the chat call on empty companion results (sidecar refusal authority)
  - A streamed body of exactly `data: I don't have enough information\n\n` + `data: [DONE]\n\n` renders the canonical string as bubble content with no `Sources` label in the output (proven by Task 4 test)
  - `php -l` exits 0
  </acceptance_criteria>
</task>

<task type="auto">
  <name>Task 4: Binding, payload, render, refusal and policy-flow tests</name>
  <files>tests/Feature/ChatWidgetTest.php</files>
  <read_first>tests/Feature/ChatWidgetTest.php (09-04 Task 7 state — reuse fixtures/fake stack), tests/fixtures/ai-sidecar/search.json (paper-77 shape), .planning/phases/09-rag-chat-policy-q-a/09-PATTERNS.md (section 2.7 CHANGE bullets)</read_first>
  <action>
  - Extend `tests/Feature/ChatWidgetTest.php` (same actingAs/Http::fake/preventStrayRequests discipline). Fake BOTH endpoints: `http://127.0.0.1:8310/search` → `search.json` fixture; `http://127.0.0.1:8310/chat/stream` → `chat-stream.txt` SSE fixture.
  - New tests:
    1. `it_binds_companion_search_to_chat_parameters` — after send, `Http::assertSent` twice: once for `/search` with `$request['query'] === $question`, `$request['corpus'] === null`, `$request['limit'] === 5`, `$request['k'] === 60`; once for `/chat/stream` with the same `query`, `top_k === 5`, and no `corpus` key (RESEARCH risk 14 fixture-level binding).
    2. `it_persists_citation_payload` — after send, load the assistant `Message` and assert `$message->citations === [['n' => 1, 'id' => 'paper-77', 'corpus' => 'catalog', 'title' => 'Analysis of Groundwater Depletion Caused By Excessive Use of Water Pumps', 'url' => '/academic-papers/77', 'catalog_code' => 'CEIT-CE-15-014']]` (from the fixture).
    3. `it_renders_catalog_chip_and_sources` — `assertSeeHtml('href="/academic-papers/77"')`, `assertSee('CEIT-CE-15-014')`, `assertSee('Sources')`.
    4. `it_renders_policy_citations_as_non_link_chips` — fake `/search` returning a policy result (id `policy-h2-r1`, corpus `policy`, title `II. Borrowing Rules`, url/catalog_code null): `assertDontSeeHtml('href="/academic-papers/')`, `assertSee('II. Borrowing Rules')`, `assertSee('(rulebook)')` (CHAT-04 client side).
    5. `it_renders_refusal_without_sources` — fake `/search` with empty results `{"query": "...", "total": 0, "results": []}` and `/chat/stream` body `data: I don't have enough information\n\n` + `data: [DONE]\n\n`: `assertSee("I don't have enough information")`, `assertDontSee('Sources')`, and the assistant Message persisted with `citations` null.
  </action>
  <verify>php artisan test --filter=ChatWidgetTest</verify>
  <acceptance_criteria>
  - All 5 new tests pass alongside the 9 from 09-04: `php artisan test --filter=ChatWidgetTest` exits 0 with 14 passing
  - `Http::assertSent` binding assertions compare identical query/corpus/top_k values across the two calls
  - No new test contains a real API key; `Http::preventStrayRequests()` remains in every test
  - `php artisan test` (full suite) still green
  </acceptance_criteria>
</task>

</tasks>

<verification>
- [ ] `php artisan test --filter=ChatWidgetTest` — 14 passing (9 from 09-04 + 5 new)
- [ ] `cd C:\Users\admin\Herd\ceit-ai-sidecar; uv run pytest` — sidecar contract suite (09-01) still green
- [ ] `php artisan test` — full Laravel suite green (523 passed / 3 skipped baseline)
- [ ] Phase gate: run the gated live smoke ONLY after `php artisan ai:export-corpus` and with a rotated key (`SIDECAR_LIVE_CHAT_TEST=1 uv run pytest tests/test_chat_stream_live.py`), then re-run the full suites
</verification>

<success_criteria>
- All 4 tasks complete
- SEARCH-03: every widget answer carries numbered [N] chips + a full Sources list bound to the same retrieval order the model saw; catalog chips link to /academic-papers/{id}
- SEARCH-04: empty retrieval shows the canonical refusal with no sources; the sidecar is the single refusal authority
- CHAT-04: policy questions surface rulebook-grounded answers with non-link policy chips
</success_criteria>

<output>
After completion, create `.planning/phases/09-rag-chat-policy-q-a/09-05-SUMMARY.md`
</output>
