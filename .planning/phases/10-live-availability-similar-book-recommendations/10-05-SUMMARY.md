---
phase: 10-live-availability-similar-book-recommendations
plan: 05
subsystem: ui
tags: [livewire, blade, availability, citations, chat-widget, hydration]

requires:
  - phase: 10-live-availability-similar-book-recommendations
    provides: "AvailabilityService::forPapers grouped hydrator (10-02) — the single grouped query behind the chat chip suffix"
  - phase: 10-live-availability-similar-book-recommendations
    provides: "Sidecar strict-key 422 validation (10-01) — the closed ADR 0004 contract the exact-key capture tests pin"
provides:
  - "SEARCH-02 chat half: live copy availability hydrated onto catalog citation chips as a compact 'X/Y' suffix with green (>=1 available) / red (0) color cue, resolved from Inventory at render time (D-03 call site #1, D-04 chip suffix, ADR 0010)"
  - "One #[Computed] availabilityMap across ALL messages' catalog citations — a single grouped AvailabilityService::forPapers() call per render (no N+1, D-02), passed as an extra include variable to the chips partial only"
  - "D-07 chat-side proof: exact key sets pinned on /chat/stream {query, mode, top_k} and companion /search {query, filters, corpus, limit, k} with no availability key; persisted 6-key citation payload provably untouched (it_persists_citation_payload byte-identical)"
affects: [chat widget future phases, phase 11 agentic search]

tech-stack:
  added: []
  patterns:
    - "Render-time enrichment via extra include variable: availability passed at the chips include site (chat-widget.blade.php:52), never written into the persisted ai_messages.citations payload (D-03/D-04)"
    - "Plain #[Computed] availabilityMap — recomputed per render (stale-count landmine #12 avoided), keyed by persisted catalog_code so the partial looks up by the persisted key"

key-files:
  created: []
  modified:
    - app/Livewire/ChatWidget.php
    - resources/views/livewire/chat-widget.blade.php
    - resources/views/livewire/chat-widget-citations.blade.php
    - tests/Feature/ChatWidgetTest.php

key-decisions:
  - "availabilityMap is keyed by catalog_code (the persisted key) rather than paper id — the chips partial already renders catalog_code, so the lookup stays in the persisted domain and the map needs no id re-derivation at render time"
  - "Single-query proof uses a DB::listen counter on the inventories table with a baseline snapshot immediately before the second send: the render carrying TWO citations adds exactly ONE query, proving grouped batching across messages (assertSet on the computed would trigger an extra recompute and skew the count — Livewire's computed cache is invalidated per render)"

patterns-established:
  - "Pattern: chat citation hydration — collect paper-N ids across all messages -> strip prefix -> int-filter (policy ids drop out) -> one forPapers() -> re-key by catalog_code -> render suffix only when the map has an entry (absent = no suffix via ?? null guard)"
  - "Pattern: D-07 chat capture — exact array_keys() equality on both sidecar request bodies, proving no availability key can ever reach the never-LLM chain"

requirements-completed: [SEARCH-02]

duration: 25min
completed: 2026-08-14
---

# Phase 10 Plan 05: Citation Availability Summary

**Catalog citation chips in the chat widget now show a live "X/Y" copy-availability suffix with a green/red color cue, hydrated from Inventory at render time via one grouped `#[Computed] availabilityMap` call across all messages — while the persisted 6-key citation payload and the exact ADR 0004 sidecar request keys stay provably untouched.**

## Performance

- **Duration:** 25 min
- **Started:** 2026-08-14T15:16:00Z
- **Completed:** 2026-08-14T15:41:16Z
- **Tasks:** 4/4
- **Files modified:** 4 (0 created, 4 modified)

## Accomplishments

- `#[Computed] availabilityMap` on `ChatWidget` collects every catalog citation's `paper-N` id across ALL messages, makes exactly ONE `AvailabilityService::forPapers()` call per render (D-02 grouped, no N+1), and re-keys by the persisted `catalog_code`; `$this->messages` is only read, never written (T-01 mitigation)
- Compact suffix span beside `catalog_code` on catalog chips only — `text-success` when >=1 available, `text-error` at 0, e.g. `2/3`; policy chips and the sources partial byte-identical (D-04); papers with no Inventory rows render no suffix (absent map + `?? null` guard)
- The chips include at `chat-widget.blade.php:52` passes `'availability' => $this->availabilityMap`; the sources partial at line 53 and the persisted `ai_messages.citations` payload are untouched (T-01/T-04)
- D-07 chat-side capture tests: `/chat/stream` bodies pinned to exactly `{query, mode, top_k}` and companion `/search` bodies to exactly `{query, filters, corpus, limit, k}`, both with no `available` key (never-LLM chain, ADR 0004)
- Four new render tests (D-07 #5 chat side): green suffix `2/3`, red suffix `0/3`, no suffix with zero Inventory rows, policy chips carry no availability markup; single-query proof via a `DB::listen` counter (two citations across two messages -> exactly one grouped query on the second send's render)

## Task Commits

Each task was committed atomically:

1. **Task 1: #[Computed] availabilityMap in ChatWidget** - `59a4cdf8` (feat)
2. **Task 2: Chips suffix markup + include-site variable** - `6bbc518a` (feat)
3. **Task 3: Exact-key capture deltas (D-07 #1 chat side)** - `66ebeba9` (test)
4. **Task 4: Chip suffix render tests (D-07 #5 chat side)** - `f1ac3841` (test)

**Plan metadata:** `(docs: complete plan)` — this commit

## Files Created/Modified

- `app/Livewire/ChatWidget.php` - `use AvailabilityService;` + `#[Computed] availabilityMap()` walking all messages' catalog citations, one grouped `forPapers()` call, map keyed by `catalog_code`
- `resources/views/livewire/chat-widget.blade.php` - chips include (line 52) passes `'availability' => $this->availabilityMap`; sources partial untouched
- `resources/views/livewire/chat-widget-citations.blade.php` - `text-success`/`text-error` "X/Y" suffix span inside the catalog `<a>` chip only, behind the `?? null` absent-entry guard
- `tests/Feature/ChatWidgetTest.php` - exact-key-set assertions on both sidecar capture closures (Task 3), `seedCatalogPaper77()` helper + 4 chip-suffix render tests with the grouped-query counter (Task 4)

## Decisions Made

- Keyed the map by persisted `catalog_code` instead of paper id — the chip partial already renders `catalog_code`, keeping the render-time lookup entirely in the persisted domain (per plan Task 1 step 5)
- Used a `DB::listen` inventory-query counter with a pre-second-send baseline instead of `assertSet` on the computed — Livewire invalidates the computed cache per render, so an `assertSet` access recomputes and would inflate the count (observed empirically: 3 queries instead of 2)
- Policy-chip bareness asserted via the absence of the two availability cue classes (`text-success`/`text-error`) rather than a broad `<span class="text-` pattern — the sources partial legitimately renders `<span class="text-base-content/40">(rulebook)</span>`

## Deviations from Plan

None - plan executed exactly as written. (The Task 4 single-query proof was implemented with the plan's permitted DB-counter option; the policy-chip assertion targets the two exact cue classes because the sources partial's `text-base-content/40` span made the broader pattern unusable — an assertion refinement, not a scope change.)

## Issues Encountered

- First pass of the grouped-query counter failed at "3 identical to 2": two `assertSet('availabilityMap.CEIT-CE-15-014.available', 2)` calls after the renders each triggered a computed recompute (Livewire caches computeds only between renders) — fixed by dropping `assertSet` and snapshotting the counter immediately before the second `call('send')`, asserting a delta of exactly 1 for the two-citation render
- `assertDontSeeHtml('<span class="text-')` in the policy test matched the sources partial's `(rulebook)` span — narrowed to the two availability cue classes

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- SEARCH-02 chat half complete: chips show live "X/Y" with green/red cues resolved from Inventory at render time; the persisted 6-key payload and sources list are provably unchanged; captured request bodies carry exactly the ADR 0004 keys (D-07 #1 both sides, #2 untouched guard, #5 both surfaces)
- D-07 chain now complete across the phase: #1 (10-02 + 10-05), #2 (existing guard untouched), #3 (10-01), #4 (10-02), #5 (10-02 + 10-05)
- Phase complete, ready for next step

---
*Phase: 10-live-availability-similar-book-recommendations*
*Completed: 2026-08-14*
