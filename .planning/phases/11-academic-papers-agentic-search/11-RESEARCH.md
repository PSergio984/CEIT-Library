# Phase 11: Academic Papers & Agentic Search — Research

**Researched:** 2026-08-15
**Status:** Ready for planning

<user_constraints>

## Locked Decisions (verbatim from 11-CONTEXT.md)

### Paper corpus content
- **D-01:** Bibliographic-only corpus — no abstract/summary column or table added. The index composes what exists in `academic_papers` today (title, year, paper_type, department, advisers, dean, authors).
- **D-02:** Paper-specific chunking = one rich index document per paper, composing title + author/adviser/dean names + department + paper_type + year into the searchable text. Metadata stays filterable via the existing `/search` filters mechanism. This upgrades the current title-doubled catalog doc shape (Phase 8) without adding body text.
- **D-03:** "Topic" = free-text keyword search over the composed document. No topics/keywords taxonomy column or tags table added.

### Paper search UX
- **D-04:** Paper search lives as a tab/mode on the existing student `AcademicPaperIndex` page — same pattern as the Phase 10 similar-books mode. One destination; reuses the existing card grid, detail modal, availability hydration, and hybrid search path.
- **D-05:** Expression = explicit filter controls for author, year range, and adviser, PLUS the free-text box for topic. Follows the Phase 8 filter pattern (`paper_type`, `department`, `publication_year`, `year_from`, `year_to` already exist in `/search`; author/adviser filters are new).
- **D-06:** Results ordered by hybrid relevance (sidecar RRF rank order preserved). No separate sort control.

### Agentic trigger
- **D-07:** The model auto-detects when one-shot retrieval is insufficient and enters the loop. No user toggle, no special phrase required.
- **D-08:** On loop cap or error, fail-closed to the grounding answer "I don't have enough information" (SEARCH-04 rule). Never guess, never fall back to a weaker ungrounded answer.
- **D-09:** Any question type may enter the loop, but the tool surface is search-only: no web access, no mutations, no multi-agent orchestration (REQUIREMENTS.md Out of Scope honored).

### Agentic loop shape
- **D-10:** One `search` tool exposing the existing closed `/search` contract — query + corpus (`catalog`|`policy`) + filters (department/paper_type/year range/author/adviser) + top_k. No per-corpus tool split, no query-rewrite helper.
- **D-11:** Maximum 3 tool-call rounds per user message (initial retrieval + 2 refinements). Bounded worst-case cost.
- **D-12:** The chat widget streams a compact activity line per step (e.g., "Searching papers by author…") and the final answer streams normally with numbered citations. No raw tool JSON, no collapsible trace.

### the agent's Discretion
- Sidecar contract mechanics for the new author/adviser filters (key names, filter semantics) — planner/researcher to fit the existing closed-schema pattern (ADR 0004 + sidecar `SEARCH_ALLOWED_KEYS`).
- Where the loop runtime lives (sidecar vs Laravel) and the exact SSE framing for step activity lines — planner decides within existing ADR 0002/0004 framing.
- Paper-tab composition with the existing status filter force-exit and recommendations mode (Phase 10 D-18 pattern) — planner applies the established mode-composition approach.

### Deferred Ideas (OUT OF SCOPE — ignore)
- Abstract/summary data source for papers (schema + admin entry) — rejected (D-01).
- Explicit topics/keywords taxonomy (tags table) — rejected (D-03).
- Open web search / multi-agent orchestration — locked out of scope.
- Query-rewrite/refine helper tool — rejected (D-10); single search tool only.

</user_constraints>

<phase_requirements>

## Phase Requirements

| Req | Text (REQUIREMENTS.md) | Research support |
|-----|------------------------|------------------|
| SEARCH-05 | User can search academic papers by topic, author, year, or adviser | D-02's rich doc text makes topic free-text BM25+semantic searchable; author/adviser as new sidecar filters (D-05) extend the existing filter mechanism `paper_type`/`department`/`publication_year`/`year_from`/`year_to` already implemented in `rrf_search` post-retrieval `passes()` [VERIFIED: ceit-ai-sidecar/app/search.py:109-125]; year range already exists. D-04 paper tab reuses the existing hybrid-search page path [VERIFIED: app/Livewire/Pages/Student/AcademicPaperIndex.php:303-356]. |
| CHAT-05 | Assistant runs agentic multi-step search (function-calling loop) when one-shot retrieval is insufficient | D-07..D-12 lock the loop: model auto-detect via tool-use (no classifier), single `search` tool over the closed `/search` contract, max 3 tool-call rounds, fail-closed to SEARCH-04's "I don't have enough information". The sidecar's `openai` SDK (>=2.45) + OpenRouter support function calling [CITED: openrouter.ai/docs/guides/features/tool-calling.md]; default model meta-llama/llama-3.3-70b-instruct supports tool calling per its model-page FAQ [CITED: openrouter.ai/meta-llama/llama-3.3-70b-instruct]. SSE framing (ADR 0002) extends additively with activity/citations frames. |

</phase_requirements>

## Summary

Phase 11 has two halves with very different risk profiles. The paper corpus + filters half is a contained evolution of existing, tested machinery: `CorpusExporter::exportCatalog()` already ships a one-doc-per-paper shape with authors/advisers/dean/department/paper_type/year both in `text` and `metadata` [VERIFIED: app/Services/CorpusExporter.php:12-56] — D-02's "rich doc" is mostly **removing the title-doubling** and **normalizing the text composition** (single title, author names, adviser names, dean, department, year, paper_type, catalog_code). The sidecar already has post-retrieval filters for paper_type/department/year range [VERIFIED: ceit-ai-sidecar/app/search.py:109-125]; author/adviser filters are two additive `passes()` clauses keyed off existing metadata. The paper tab on `AcademicPaperIndex` reuses `runHybridSearch()`'s exact pattern — filters array built from public properties, `AiService::search($query, $filters, 'catalog', 10)`, id-preserving rank order, AvailabilityService hydration [VERIFIED: app/Livewire/Pages/Student/AcademicPaperIndex.php:303-356, 442-453].

The agentic half is the genuinely new machinery. **Primary recommendation: the loop runs in the sidecar (Python)**, not Laravel: the `openai` SDK client + API key already live there (`RagService`, `settings.llm_api_key`) [VERIFIED: ceit-ai-sidecar/app/rag.py:112-117, app/config.py:13-16]; the SDK supports `tools=`/`tool_choice` natively [CITED: openrouter.ai/docs/guides/features/tool-calling.md]; each tool round is a direct `rrf_search` call with `include_text=True` (same internal seam `/chat/stream` already uses [VERIFIED: ceit-ai-sidecar/app/main.py:150]) — no HTTP round-trip per tool call, no PHP OpenAI SDK (none exists in composer.json), no LLM key duplicated into Laravel, and ADR 0001's "model config-only server-side" posture stays intact. Laravel only parses two new additive SSE frames. Trigger = "tool-use in the first LLM call": the first chat call becomes a single non-streamed tool-eligible call; if the model emits a `tool_call` it enters the loop, otherwise its text streams as today. No classifier — that is an extra LLM call with its own failure mode and violates the "simplest robust" bar. Cap = a loop guard counting executed search tool calls (D-11's "initial retrieval + 2 refinements"); at cap or on error, answer grounded in whatever docs accumulated, or fail-closed refusal if none. Citations = the merged, deduplicated doc set across all rounds, numbered and emitted as a dedicated `event: citations` frame so ADR 0006's "numbered set the model worked from" binding is exact.

Keep the `catalog` corpus tag. The catalog corpus IS academic papers (`exportCatalog()` is the only writer; conftest/docs confirm `corpus: 'catalog'` on every `paper-{id}` doc [VERIFIED: app/Services/CorpusExporter.php:38, ceit-ai-sidecar/tests/conftest.py:57-106]). Introducing a `papers` tag would require `CHAT_ALLOWED_KEYS` corpus validation changes, break ADR 0006 chip rendering (catalog → link chips) and `availabilityMap` hydration (filters `corpus === 'catalog'`) [VERIFIED: resources/views/livewire/chat-widget-citations.blade.php:3, app/Livewire/ChatWidget.php:287-291], and fork the similar-books mechanism (ADR 0011 title-as-query targets catalog). The rich shape is an evolution of `catalog.json` docs; a rebuild re-embeds transparently.

## Architectural Responsibility Map

| Concern | Owner | Details |
|---------|-------|---------|
| Rich paper doc text composition | Laravel `CorpusExporter::exportCatalog()` | Single title + `authors:` names + `research_adviser:`/`technical_adviser:`/`dean:` + `department:` + `publication_year:` + `paper_type:` + `catalog_code:`; drop the title-doubling [VERIFIED: app/Services/CorpusExporter.php:26-40] |
| Paper metadata for filters | Laravel exporter `metadata` | Unchanged keys: `catalog_code`, `department`, `publication_year` (int), `paper_type`, `authors` (array), `research_adviser`/`technical_adviser`/`dean` (strings), `url` |
| Author/adviser filter semantics | Sidecar `HybridSearch.rrf_search` `passes()` | Additive clauses over `metadata.authors` (array) and `metadata.research_adviser`/`technical_adviser` |
| Filter key contract | Sidecar `main.py` | Filter keys live inside `filters` dict (already open/permissive — no top-level key change, `SEARCH_ALLOWED_KEYS` untouched) [VERIFIED: ceit-ai-sidecar/app/main.py:59,106] |
| Loop runtime | Sidecar (new `app/agent.py`-style module) | LLM calls with tools, round guard, arg validation, SSE frame emission |
| SSE framing for activity + citations | Sidecar `main.py`/agent module + `RagService` | New additive frames within ADR 0002 framing; `[DONE]`/`event: error` unchanged |
| Frame parsing | Laravel `AiService::chatStreamEvents()` (+ new frames method) | Learn `event: activity`/`event: citations` without breaking existing chunk/error handling |
| Activity line render | Laravel `ChatWidget` + blade | Second persistent Livewire stream slot (W-3 pattern) above the answer slot |
| Agentic citations payload | Sidecar loop → `event: citations` frame → `ChatWidget` | ADR 0006 shape `{n, id, corpus, title, url, catalog_code}`; fallback to `companionCitations()` when absent |
| Paper tab mode state | Laravel `AcademicPaperIndex` | Mode flag + snapshot pattern (Phase 10 `showSimilar`/`backToResults`); status filter force-exit honored |
| Author/adviser filter data sources | Laravel `AcademicPaperIndex` computeds | Distinct `authors` names (pivot); union of `research_advisers` + `technical_advisers` names |
| Fail-closed | Sidecar loop + existing `/chat/stream` empty-retrieval refusal | Canonical "I don't have enough information" (ADR 0006), zero-token path when no docs |
| Index freshness | Existing export → `/index/rebuild` → nightly reconcile | Shape change rides the existing versioned rebuild (atomic swap) [VERIFIED: ceit-ai-sidecar/app/rebuild.py:40-102] |

## Standard Stack (versions verified)

No new libraries required for this phase. [VERIFIED: composer.json, ceit-ai-sidecar/pyproject.toml]

| Layer | Technology | Version (verified) | Used for |
|-------|-----------|--------------------|----------|
| PHP app | Laravel | framework ^13.0 (composer.json) | CorpusExporter, AiService, Livewire pages |
| Livewire | livewire/livewire | ^4.0 (4.4 per ADR 0002) | Page modes, `$this->stream()` slots |
| Python sidecar | FastAPI + uvicorn | fastapi>=0.141,<0.142; uvicorn>=0.52,<0.53 | /search, /chat/stream, loop host |
| LLM client | openai SDK | >=2.45,<3 | `chat.completions.create(tools=...)` — tool calling supported |
| LLM provider | OpenRouter | base_url `https://openrouter.ai/api/v1` | ADR 0001; default `meta-llama/llama-3.3-70b-instruct` (supports tool calling [CITED: model FAQ]); `tool_choice: "auto"` default [CITED: openrouter.ai/docs/guides/features/tool-calling.md] |
| Search | sqlitesearch | ^0.3 | FTS5 BM25; keyword_fields corpus/department/paper_type (filters are metadata post-retrieval, not keyword fields) [VERIFIED: ceit-ai-sidecar/app/search.py:57-64] |
| Embeddings | sentence-transformers | >=5.7,<5.8 | Whole-doc embeddings of `doc["text"]` [VERIFIED: ceit-ai-sidecar/app/ingest.py:75-79] |
| Tests | PHPUnit ^13 (Laravel) / pytest ^8 + httpx (sidecar) | verified | Both suites exist with deterministic embedder fixtures |
| Runtime | PHP ^8.4 / Python >=3.13 (uv) | verified | — |

## Architecture Patterns

### System diagram (recommended)

```
Laravel                                    Sidecar (127.0.0.1:8310)              OpenRouter
────────                                    ──────────────────────               ─────────
AcademicPaperIndex (paper tab)                                                    
  filters[] {author, adviser, ...}  ──POST /search────► main.py: closed keys,    
  AiService::search()                                    rrf_search(query, k=60,  
                                                         filters, corpus)         
                                                                                  
ChatWidget ──POST /chat/stream──► main.py: agentic path                          
  Http stream read                  │                                           
  └ chatStreamFrames() ◄──SSE───┐   │ first LLM call (stream=False, tools=[search])
      event: activity           │   │   └─ tool_call? ──► loop (≤3 rounds):       
      data: {"c":"…"} (chunks)  │   │        parse args → validate (closed schema)
      event: citations          │   │        rrf_search(..., include_text=True)   
      data: [DONE] / event: err │   │        append tool result → next LLM call   
                                 │   └─ final: streamed answer from merged docs   
                                 │      + citations frame (dedup, renumber)       
```

### Recommended structure

**Sidecar (new module `app/agent.py` + thin `main.py` wiring):**
1. `SEARCH_TOOL` spec: OpenAI function-calling JSON schema with `parameters` mirroring the `/search` request (`query: str`, `corpus: "catalog"|"policy"|null`, `filters: object` with known keys incl. new `author`/`adviser`, `top_k: int 1..50`) [VERIFIED basis: ceit-ai-sidecar/app/main.py:59,123-151].
2. `AgenticLoop` (or method on `RagService`): `stream_agentic_events(query)` — one non-streamed tool-eligible call; while `tool_calls` and `rounds < 3`: validate args (pydantic, `extra="forbid"`), run `rrf_search(query, k=60, limit=top_k, filters=..., corpus=..., include_text=True)`, append tool message, emit `event: activity` frame; on final round, stream the answer (existing citations-mode prompt, ADR 0003) from the merged doc set and emit `event: citations`.
3. Loop guard `MAX_TOOL_ROUNDS = 3` (counts executed searches; D-11). Malformed args → one corrective feedback turn, then fail-closed. Empty merged docs → canonical refusal, no LLM call (existing zero-token path, ADR 0006).
4. `main.py /chat/stream`: replace the one-shot `stream_events` call with the agentic path (same SSE media type, headers, error taxonomy — 401/422 unchanged).

**SSE framing for activity lines (recommendation within ADR 0002 discretion):** new **event types**, not data-line envelopes — `event: activity` + `data: {"text": "Searching papers by author…"}` and `event: citations` + `data: [{n, id, corpus, title, url, catalog_code}]`. Rationale: existing `data:` lines carry either `{"c": "<delta>"}` chunks or raw text; a `{"t": "activity"}` data envelope would fall through `chatStreamEvents()`'s chunk decoding and be yielded as raw JSON into the answer [VERIFIED: app/Services/AiService.php:101-115]. Event-type frames are additive: `[DONE]` and `event: error` semantics unchanged (ADR 0004).

**Laravel:**
5. `AiService::chatStreamFrames(Response): Generator` — same `resource()` read loop, yields typed frames `['type' => 'chunk'|'activity'|'citations', ...]`; keep `chatStreamEvents()` untouched for back-compat (or have it delegate filtering chunks).
6. `ChatWidget::streamQuestion()` — when frames arrive: activity frames → `$this->stream('<line>', false, 'activity')` on a new persistent slot (same single-line W-3 slot pattern as `ans` [VERIFIED: resources/views/livewire/chat-widget.blade.php:151-162]); chunk frames → existing `'ans'` slot; citations frame → citation payload for the final bubble + persistence; no frame → fall back to `companionCitations()` [VERIFIED: app/Livewire/ChatWidget.php:154-178, 237-263].

**Paper tab (Laravel, planner's discretion on visual framing):**
7. `AcademicPaperIndex`: new `authorFilter`/`adviserFilter` public props (validated, nullable strings); extend the `runHybridSearch()` filters array [VERIFIED: app/Livewire/Pages/Student/AcademicPaperIndex.php:311-317] with `'author' => $this->authorFilter ?: null, 'adviser' => $this->adviserFilter ?: null`; new `#[Computed] availableAuthors()` (`Author::distinct()->orderBy('name')->pluck('name')`) and `availableAdvisers()` (union of `ResearchAdviser::orderBy('name')->pluck('name')` + `TechnicalAdviser::...`, distinct). Tab/mode state mirrors `recommendedFor` + `recommendationsSnapshot` [VERIFIED: app/Livewire/Pages/Student/AcademicPaperIndex.php:65-73, 368-434]; status filter continues to force-exit hybrid mode (`updatedStatusFilter()` → `exitHybridMode()`) [VERIFIED: lines 239-247].
8. No route/URL change — same page, component state only (D-04 "one destination").

### Composition matrix (paper tab vs existing modes)

| Control | SQL list | Hybrid search | Recommendations mode | Paper tab (new) |
|---------|----------|---------------|---------------------|-----------------|
| Free-text search box | SQL LIKE | sidecar (≥3 chars) | hidden (seed paper) | sidecar |
| Status filter | SQL | force-exit to SQL | snapshot-restored | force-exit to SQL (unchanged) |
| Year / dept / paper_type | SQL | sidecar filters | snapshot-restored | sidecar filters |
| year_from / year_to | SQL | sidecar filters | snapshot-restored | sidecar filters |
| **author (new)** | — | sidecar filter | snapshot-restored | sidecar filter |
| **adviser (new)** | — | sidecar filter | snapshot-restored | sidecar filter |
| Availability | AvailabilityService | AvailabilityService | AvailabilityService | AvailabilityService (shared `availability()` computed already merges all result sets [VERIFIED: lines 442-453]) |

## Don't Hand-Roll

- **Tool calling** — the openai SDK + OpenRouter already implement `tools`/`tool_choice`; pass the JSON spec, read `response.choices[0].message.tool_calls`. Do not parse the model's natural-language "I will search for..." — that is what `tool_choice: "auto"` exists for [CITED: openrouter.ai/docs/guides/features/tool-calling.md].
- **A classifier for the trigger** — tool-use-in-first-response IS the auto-detect (D-07). A separate classifier = second LLM call, second failure mode, more latency.
- **A new `papers` corpus tag** — forks citations rendering, availability hydration, chat corpus validation, similar-books; `catalog` already equals papers (see Summary).
- **A sidecar `exclude` param** — ADR 0011 already rejected it; the RRF 60 pool absorbs wasted rank slots.
- **Hand-written SSE parsing in the widget** — extend the existing `chatStreamEvents()` generator; keep the JSON `{"c": ...}` chunk envelope (W-1 correction).
- **History replay in the loop** — single-turn contract (ADR 0004) stands; the loop threads its own tool messages internally, never a `history` field over the wire.
- **Filter key rejection (422) for unknown filter keys** — the filters dict is intentionally permissive today [VERIFIED: ceit-ai-sidecar/app/main.py:106]; the closed-schema posture is top-level keys. Keep it.

## Common Pitfalls

1. **Frames breaking the chunk parser** — any new SSE event must be tested against `chatStreamEvents()`; a data-envelope activity line will render raw JSON into the answer (verified parser fall-through, AiService.php:108-113).
2. **Citation mismatch with the model's source set** — ADR 0006 binds `[N]` to the numbered docs the model worked from; the agentic citation set MUST be the merged round docs (dedupe by id, renumber 1..N in merge order), not a fresh one-shot companion search. Use the `event: citations` frame from the loop itself.
3. **Status filter force-exit** — the paper tab must keep `updatedStatusFilter()` → `exitHybridMode()`; author/adviser filters must NOT exit hybrid mode (they are sidecar filters, unlike status).
4. **Embedding drift on doc-shape change** — the whole-doc embedding is `doc["text"]` [VERIFIED: ingest.py:75-79]; changing the text changes every catalog vector → RRF results shift. Re-run `python -m app.eval` against the golden set after export+rebuild; expect catalog cases to move (author/adviser queries should improve).
5. **`passes()` type errors** — filter values from the agent arrive as strings; `int()` conversions already exist for year filters [VERIFIED: search.py:112-121]; validate `author`/`adviser` as `str` before containment checks to avoid TypeErrors on junk args.
6. **`authors` is an array** — `meta.get("authors")` is a list [VERIFIED: CorpusExporter.php:46]; `f["author"] in meta["authors"]` (exact) vs any-substring semantics must be decided and tested; recommend case-insensitive substring (UI dropdown values are exact anyway; agent names are fuzzy).
7. **Widget slot hazard (W-3)** — the second activity stream slot must be a single-line persistent element; wrapped markup with whitespace resurfaces the idle-bubble bug [VERIFIED: chat-widget.blade.php comment, lines 151-162].
8. **Retry duplication** — `chatStream()` deliberately has `retries: 0` [VERIFIED: AiService.php:54-63]; the agentic path adds *internal* tool rounds — do not add HTTP-level retries on top or a failed tool round may double-search.
9. **Rebuild ordering** — export new `catalog.json` → `POST /index/rebuild` → nightly reconcile confirms; the versioned atomic swap keeps the old index serving during build [VERIFIED: rebuild.py:40-102].
10. **Tool-call reliability** — OpenRouter surfaces a per-model Tool Call Error Rate and offers Exacto routing for tool-call accuracy; if the default Balanced routing produces malformed calls in practice, switch routing mode (config-level, ADR 0001 posture) rather than adding retry loops.

## Code Examples (verified against the codebase)

### 1. Rich paper doc (CorpusExporter evolution) — replace `$segments` (CorpusExporter.php:26-34)

```php
$segments = [$title.'.'];
$segments[] = 'authors: '.implode('; ', $authors);
$segments[] = 'research_adviser: '.($researchAdviser ?? '');
$segments[] = 'technical_adviser: '.($technicalAdviser ?? '');
$segments[] = 'dean: '.($dean ?? '');
$segments[] = 'department: '.$this->sanitize($paper->department, 200);
$segments[] = 'publication_year: '.$paper->publication_year;
$segments[] = 'paper_type: '.$this->sanitize($paper->paper_type, 200);
$segments[] = 'catalog_code: '.$this->sanitize($paper->catalog_code, 200);
```
(All source fields already eager-loaded at lines 14-24; metadata block at 41-51 unchanged — that block is the filter + citation contract.)

### 2. Author/adviser filters in `rrf_search().passes()` (search.py:109-121, additive)

```python
AUTHOR_KEYS = ("research_adviser", "technical_adviser")  # adviser filter targets

def passes(doc: dict) -> bool:
    meta = doc.get("metadata") or {}
    f = filters or {}
    # ... existing paper_type / department / publication_year / year_from / year_to ...
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
Filters apply post-retrieval BEFORE RRF fusion — a filtered doc can never outrank a relevant one (verified behavior, test_filters.py:65-76).

### 3. `search` tool spec (sidecar agent module; mirrors /search contract)

```python
SEARCH_TOOL = {
    "type": "function",
    "function": {
        "name": "search",
        "description": "Search the CEIT Library catalog (academic papers) or policy rulebook. "
                       "Use when the user's question needs retrieved documents.",
        "parameters": {
            "type": "object",
            "properties": {
                "query": {"type": "string"},
                "corpus": {"type": "string", "enum": ["catalog", "policy"]},
                "filters": {
                    "type": "object",
                    "properties": {
                        "paper_type": {"type": "string"},
                        "department": {"type": "string"},
                        "publication_year": {"type": "integer"},
                        "year_from": {"type": "integer"},
                        "year_to": {"type": "integer"},
                        "author": {"type": "string"},
                        "adviser": {"type": "string"},
                    },
                },
                "top_k": {"type": "integer", "minimum": 1, "maximum": 50},
            },
            "required": ["query"],
            "additionalProperties": False,
        },
    },
}
```
Tool call → `rrf_search(query, k=60, limit=args.get("top_k", 5), filters=args.get("filters") or {}, corpus=args.get("corpus"), include_text=True)` — the same internal seam `/chat/stream` uses (main.py:150). Enforce `additionalProperties: False` server-side with a pydantic model (`extra="forbid"`) so malformed args are caught before execution.

### 4. Loop shape (sidecar; cap = executed search rounds, D-11)

```python
MAX_TOOL_ROUNDS = 3

def stream_agentic_events(query, mode="citations"):
    messages = [{"role": "system", "content": SYSTEM_PROMPT}, {"role": "user", "content": query}]
    docs = []          # merged, deduped by doc id across rounds
    rounds = 0
    while True:
        resp = client.chat.completions.create(model=..., messages=messages, tools=[SEARCH_TOOL], tool_choice="auto", max_tokens=...)
        msg = resp.choices[0].message
        if not msg.tool_calls:                       # auto-detect: no tool -> direct answer
            return stream final answer from merged docs (or refuse if empty)
        if rounds >= MAX_TOOL_ROUNDS:                # cap: fail-closed
            return stream answer from docs or refusal
        for call in msg.tool_calls:                  # validate before executing
            args = ToolArgs.model_validate_json(call.function.arguments)  # extra="forbid"
        yield activity frame("Searching ...")
        results = rrf_search(...include_text=True)
        docs = merge_dedup(docs, results)
        messages.append(assistant tool-call message + tool result)
        rounds += 1
    # final: prompt on merged docs (citations mode), stream chunks, then event: citations frame, then [DONE]
```

### 5. SSE frames (sidecar; ADR 0002 additive)

```
event: activity
data: {"text": "Searching papers by author\u2026"}

data: {"c": "The papers by "}
data: {"c": "Juan Dela Cruz are \u2026"}
event: citations
data: [{"n":1,"id":"paper-77","corpus":"catalog","title":"\u2026","url":"/academic-papers/77","catalog_code":"CEIT-CE-15-014"}]

data: [DONE]
```

### 6. Laravel frame parser (extend AiService::chatStreamEvents pattern, AiService.php:87-140)

```php
// inside the data:/event loop, add:
if ($line === 'event: activity' || $line === 'event: citations') {
    $dataLine = fgets($stream);
    if ($dataLine !== false && str_starts_with($dataLine, 'data: ')) {
        $payload = json_decode(trim(substr($dataLine, 6)), true);
        yield ['type' => $line === 'event: activity' ? 'activity' : 'citations', 'payload' => $payload];
    }
    continue;
}
```
Keep `chatStreamEvents()` yielding raw chunk strings for existing callers/tests; add `chatStreamFrames()` (typed) used by the widget.

### 7. Citation payload from loop docs (ADR 0006 shape)

```python
def citation_payload(docs):  # docs already deduped, merge-ordered
    return [
        {"n": i + 1, "id": d["id"], "corpus": d["corpus"], "title": d["title"],
         "url": (d.get("metadata") or {}).get("url"),
         "catalog_code": (d.get("metadata") or {}).get("catalog_code")}
        for i, d in enumerate(docs)
    ]
```
Widget: use frame when present, else existing `companionCitations()` fallback (ChatWidget.php:237-263); persist into `ai_messages.citations` (ADR 0005) as today (ChatWidget.php:180-185).

### 8. Paper tab filters passthrough (AcademicPaperIndex::runHybridSearch, lines 311-317)

```php
$filters = [
    'paper_type' => $this->paperTypeFilter ?: null,
    'department' => $this->departmentFilter ?: null,
    'publication_year' => $this->yearFilter ?: null,
    'year_from' => $this->yearFromFilter ?: null,
    'year_to' => $this->yearToFilter ?: null,
    'author' => $this->authorFilter ?: null,   // new
    'adviser' => $this->adviserFilter ?: null,  // new
];
```
`AiService::search()` already forwards `filters` verbatim — zero service changes (AiService.php:27-36; payload assertion test AiServiceTest.php:33-45).

## Assumptions Log

| # | Claim | Tag |
|---|-------|-----|
| A-1 | `meta-llama/llama-3.3-70b-instruct` supports OpenAI function calling on OpenRouter (model-page FAQ confirms tool-calling + structured outputs; routed to multiple providers) | [CITED: openrouter.ai/meta-llama/llama-3.3-70b-instruct] — verify tool-call reliability in the first spike; Exacto routing is the fallback |
| A-2 | The `adviser` filter matches research_adviser OR technical_adviser (D-05 says "adviser", the corpus has both roles) | [ASSUMED] — planner should confirm with the product side; alternative is two separate keys |
| A-3 | Case-insensitive substring semantics for author/adviser (UI dropdown sends exact names; agent sends fuzzy names) | [ASSUMED] — matches existing LIKE-based SQL search behavior (AcademicPaper::scopeSearch uses `LIKE %..%`) [VERIFIED: app/Models/AcademicPaper.php:96-115] |
| A-4 | First LLM call becomes non-streamed (decision call) — adds ~1-3s latency before the answer streams; activity lines mask it | [ASSUMED] — planner's UX call; alternative (streamed first call with tool detection) is fragile and rejected |
| A-5 | Top-level `SEARCH_ALLOWED_KEYS` stays unchanged; new filters ride inside the already-permissive `filters` dict | [VERIFIED: main.py:59,106 — no top-level change needed] |
| A-6 | Papers corpus golden-set cases are added to `data/golden_dataset.json` now (small), full eval stack lands in Phase 13 | [ASSUMED] — eval.py already supports `filters` + negative cases and `--corpus catalog` [VERIFIED: ceit-ai-sidecar/app/eval.py:27-45,87] |
| A-7 | The agentic path serves ALL `/chat/stream` messages (D-07: no toggle). Existing `mode` values stay valid; agentic behavior is the implementation of the existing endpoint, not a new endpoint | [ASSUMED] — D-07 wording; ADR 0014 during planning should lock this |
| A-8 | Paper tab = new filter props + mode state on the existing page; no URL/route change (D-04 "one destination") | [VERIFIED basis: AcademicPaperIndex.php mode patterns] |

## Open Questions

1. **Adviser filter scope** — research+technical (OR) or research-only? D-05 says "adviser" singular. Recommendation: OR across both (the doc text carries both; a reader asking "papers advised by X" means any adviser role). Planner to lock in ADR 0013.
2. **First-call latency** — non-streamed tool-eligible first call adds a decision round-trip before streaming begins. Acceptable? Recommendation: yes — activity lines render during the loop; verify against the demo path.
3. **Activity line content** — "Searching papers by author…" is D-12's example; exact strings (and whether corpus/department are mentioned) are planner/UX scope. Keep them short, no raw JSON.
4. **Golden set update now or Phase 13?** — Recommendation: add ~10 papers cases (author/adviser/year/topic + 2 negatives) to `golden_dataset.json` this phase as the quality gate; formal EVAL-01..04 is Phase 13.
5. **Balanced vs Exacto routing for tool calls** — recommendation: keep default Balanced; flip via config if tool-call error rate is visible. Lock in ADR 0014.

## Environment Availability

- Sidecar runs on `127.0.0.1:8310`; `SIDECAR_TOKEN=smoke-test-token` placeholder in both `.env` files (operator sets a real token pre-production) [VERIFIED: 11-CONTEXT.md specifics]. Note: a non-placeholder token breaks `SidecarLiveTest` if it hardcodes the placeholder — check during implementation.
- LLM: OpenRouter key + model configured in sidecar `.env` (`llm_api_key`, `llm_model`); live smoke tests are gated (1 skipped in sidecar suite) [VERIFIED: config.py:13-16, tests/test_chat_stream_live.py exists].
- Embedding model `paraphrase-multilingual-MiniLM-L12-v2` downloads at first embed (~470 MB) — dev machines have it cached; CI/tests use deterministic embedders only [VERIFIED: conftest.py:22-40].
- Baselines: Laravel `php artisan test` ~585 passed / 3 skipped (~53s); sidecar `pytest` ~49 passed / 1 skipped [VERIFIED: 11-CONTEXT.md specifics].
- DB: tests run on sqlite; prod driver-agnostic code paths already exist (AcademicPaper sequence logic).

## Validation Architecture

Framework: PHPUnit 13 (Laravel, `php artisan test`) + pytest 8 (sidecar, `uv` env). Patterns to reuse: `Http::fake` + `Http::preventStrayRequests` payload-capture assertions (AiServiceTest.php:26-45), `Livewire::test()` component assertions with faked sidecar streams (ChatWidgetTest.php:71-83), deterministic embedder fixtures (conftest.py), `FakeCompletionsHolder` LLM mock (test_chat_stream.py:23-59).

| Requirement | Test (new) | File | Asserts |
|-------------|-----------|------|---------|
| D-02 doc shape | `it_exports_rich_paper_docs_with_locked_schema` | tests/Feature/ExportAiCorpusTest.php (update existing `it_exports_catalog_documents_with_locked_schema`) | Single title prefix (title-doubling removed); text contains authors/advisers/dean/department/paper_type/year/catalog_code; metadata keys unchanged incl. `authors` array; url intact; sanitization caps hold |
| D-02 shape | conftest `make_corpus` update + fixtures | ceit-ai-sidecar/tests/conftest.py | Sidecar test corpus docs match the new rich text shape so filter tests exercise real fields |
| D-05 author filter | `test_filter_author_any_of_multiple` | ceit-ai-sidecar/tests/test_filters.py | `filters={"author": "juan"}` returns docs whose `metadata.authors` contains a case-insensitive match; excludes others; combined with year range |
| D-05 adviser filter | `test_filter_adviser_research_or_technical` | test_filters.py | `filters={"adviser": "engr. jose"}` matches research_adviser and technical_adviser docs |
| D-05 422 posture | `test_search_endpoint_accepts_author_adviser_filters` | ceit-ai-sidecar/tests/test_api.py | POST /search with author/adviser filters → 200 and filtered results; unknown TOP-LEVEL field still 422 (posture unchanged) |
| D-05 passthrough | `it_passes_author_and_adviser_filter_keys` | tests/Feature/AiServiceTest.php | `Http::fake` capture: `$request['filters'] === ['author' => ..., 'adviser' => ...]`; top-level keys exactly query/filters/corpus/limit/k |
| D-04 paper tab | `it_searches_with_author_and_adviser_filters_in_tab_mode` | tests/Feature/AcademicPaperIndexHybridTest.php (new cases) | Livewire: set search + authorFilter + adviserFilter → sidecar payload captured with both keys; results render in rank order; availability computed present |
| D-04 force-exit | `it_exits_paper_tab_on_status_filter` | AcademicPaperIndexHybridTest.php | statusFilter set → hybridResults null, SQL path renders (existing force-exit honored in tab mode) |
| D-04 composition | `it_snapshots_and_restores_paper_tab_state` | AcademicPaperIndexSimilarTest.php (pattern) | author/adviser values snapshot + restore across mode transitions |
| D-07 trigger | `test_direct_answer_streams_without_search` | ceit-ai-sidecar/tests/test_agentic_loop.py (new) | Fake LLM returns content, no tool_calls → chunks stream, zero `rrf_search` calls |
| D-07 loop | `test_tool_call_triggers_search_then_answer` | test_agentic_loop.py | Fake LLM tool_call with args → `rrf_search` called with parsed query/filters/corpus/top_k; final answer streams; citations frame carries merged docs |
| D-11 cap | `test_loop_caps_at_three_rounds_and_fails_closed` | test_agentic_loop.py | 4th tool call suppressed; final answer grounded in accumulated docs; with zero docs → "I don't have enough information" |
| D-08 fail-closed | `test_malformed_tool_args_correct_once_then_fail_closed` | test_agentic_loop.py | Unknown arg key → one corrective turn → second malformed → refusal, no ungrounded answer |
| D-12 frames | `test_activity_and_citations_frames` | ceit-ai-sidecar/tests/test_chat_stream.py (extend) | SSE body contains `event: activity` lines and `event: citations` before `[DONE]`; chunk envelope `{"c": ...}` unchanged |
| D-12 parse | `it_parses_activity_and_citations_frames` | tests/Feature/AiServiceChatTest.php (extend) | `chatStreamFrames()` yields typed frames; `chatStreamEvents()` still yields raw chunks (back-compat) |
| D-12 render | `it_renders_activity_lines_and_agentic_citations` | tests/Feature/ChatWidgetTest.php (new cases) | Livewire: activity stream slot receives lines; final bubble has citations payload from frame; persisted to `ai_messages.citations`; error/refusal bubble renders |
| Quality gate | papers cases + negatives in golden set; `python -m app.eval --corpus catalog` | ceit-ai-sidecar/data/golden_dataset.json + eval.py (existing runner) | Author/adviser/topic/year queries hit expected `paper-N` ids; negatives retrieve zero relevant ids |
| Reconcile | no change | tests/Feature/ReconcileAiIndexTest.php | Existing nightly-reconcile test still passes with new doc shape |

**Wave 0 gaps to watch:** (1) sidecar fixtures (`conftest.py` corpus docs) must be updated in the same commit as the Laravel exporter shape or tests disagree; (2) `ChatWidgetTest` fixtures (`chat-stream.txt`) need an agentic variant file; (3) the golden-set `catalog_snapshot` timestamp will drift on re-export — update it; (4) `SidecarLiveTest` token coupling if `SIDECAR_TOKEN` is real.

## Security Domain

| ASVS-ish category | Threat / control relevant this phase |
|-------------------|--------------------------------------|
| Prompt injection (V1-ish) | The tool-call pipeline is the new injection surface: model output becomes search input. Controls: closed-schema arg validation (`additionalProperties: false`, pydantic `extra="forbid"`), string-only filter values, no file/URL/executable tools (search-only, D-09), and the existing grounding prompt (ADR 0003) already instructs source-faithfulness. Tool results are library-owned docs, not web content — no SSRF class surface exists (web search is locked out of scope). |
| Input validation (V5) | Tool args validated identically to `/search`'s contract (query required non-empty str; corpus enum; top_k int 1..50; filter keys known + str values). Malformed → corrective turn → fail-closed, never raw execution. |
| Business logic / resource abuse (V10-ish) | Token/cost guard: MAX_TOOL_ROUNDS = 3 (D-11) + per-call `max_tokens` (512 today) bounds worst case; fail-closed refusal is the zero-token path (ADR 0006) — the refusal path must never call the LLM. No HTTP retries on the stream (existing posture, AiService:54-63). |
| Logging / PII (V7-ish) | `AiService` failure logging is sanitized (endpoint + reason only, never queries/tokens) [VERIFIED: AiService.php:198-201]. Activity lines are static strings, never raw tool JSON (D-12). Agent queries flow through the same token-gated sidecar middleware (401 posture unchanged). |
| Error handling (V7) | New frames must not bypass `event: error` semantics: provider failure mid-loop → single `event: error` + `[DONE]` (existing taxonomy, ADR 0004), mapped to `AiServiceProviderException` on the Laravel side. |

## Sources

### Repo files (verified)
- `.planning/phases/11-academic-papers-agentic-search/11-CONTEXT.md` — locked decisions (D-01..D-12, discretion, deferred)
- `.planning/REQUIREMENTS.md` — SEARCH-05, CHAT-05, Out of Scope table
- `.planning/ROADMAP.md` — Phase 11 goal/success criteria
- `.planning/STATE.md` — phase status
- `app/Services/CorpusExporter.php` — catalog doc shape (paper-{id}, title-doubled, metadata, url)
- `app/Services/AiService.php` — search/chatStream/chatStreamEvents, SSE_CHUNK_KEY, typed exceptions
- `app/Services/SimilarPapersService.php` — title-as-query, fail-closed flag
- `app/Livewire/Pages/Student/AcademicPaperIndex.php` — hybrid search, filters, modes, availability
- `app/Livewire/ChatWidget.php` — message render array, companionCitations, availabilityMap
- `resources/views/livewire/chat-widget.blade.php` — persistent stream slots (W-3), error/retry
- `resources/views/livewire/chat-widget-citations.blade.php` — corpus-dependent chips
- `app/Models/AcademicPaper.php`, `app/Models/Author.php` — relations, scopeSearch
- `database/migrations/2025_09_09_072225_create_advisers_and_deans_tables.php`, `2025_09_16_000002_create_academic_paper_authors_table.php` — normalized names
- `docs/adr/0001..0006, 0008, 0010, 0011` — provider, streaming, modes, contract, history, citations, widget, availability, similar-books
- `tests/Feature/ExportAiCorpusTest.php`, `AiServiceTest.php`, `AiServiceChatTest.php`, `ChatWidgetTest.php`, `AcademicPaperIndexHybridTest.php`, `AcademicPaperIndexSimilarTest.php`
- `ceit-ai-sidecar/app/main.py` — SEARCH_ALLOWED_KEYS, /search, /chat/stream, 422 posture
- `ceit-ai-sidecar/app/search.py` — rrf_search, passes(), code pin
- `ceit-ai-sidecar/app/rag.py` — prompts, stream_events, refusal
- `ceit-ai-sidecar/app/ingest.py` — whole-doc embedding
- `ceit-ai-sidecar/app/rebuild.py` — versioned atomic rebuild
- `ceit-ai-sidecar/app/eval.py` — golden-set runner
- `ceit-ai-sidecar/app/config.py`, `pyproject.toml` — versions, OpenRouter config
- `ceit-ai-sidecar/tests/conftest.py`, `test_api.py`, `test_filters.py`, `test_chat_stream.py`
- `ceit-ai-sidecar/data/golden_dataset.json`

### Web (cited)
- https://openrouter.ai/docs/guides/features/tool-calling.md — tool/function calling via OpenAI-compatible `tools` parameter; `tool_choice: "auto"` default; model filtering by `supported_parameters=tools`; Tool Call Error Rate + Auto Exacto routing
- https://openrouter.ai/meta-llama/llama-3.3-70b-instruct — model page; FAQ confirms tool calling and structured outputs support
- https://openrouter.ai/docs/llms.txt — docs index used to locate the tool-calling guide

---

*Research produced 2026-08-15 for Phase 11 planning (gsd-phase-researcher).*
