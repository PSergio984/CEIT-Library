# Feature Research

**Domain:** AI library assistant (RAG agent) for an academic library management system
**Researched:** 2026-08-13
**Confidence:** HIGH

*Mined from two reference codebases: `rag-search-engine` (WSL) — hybrid BM25+semantic RRF search, RAG/summarize/citations/question CLI, golden-dataset evaluation — and `llm-zc` (D:\ai-eng\llm-zc) — agentic function-calling loop, grounding instructions, ground-truth generation, LLM-as-judge, feedback + metrics monitoring. Plus the CEIT-Library codebase itself (models, migrations, Livewire components).*

---

## Feature Landscape

### Table Stakes (Users Expect These)

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| Hybrid book search (BM25 + semantic, RRF fusion) | "Find me books about X" is the #1 query; keyword-only misses paraphrase queries ("civil engineering", "konstruksyon ng tulay"); semantic-only misses exact titles/ISBNs/call numbers. The `rag-search-engine` reference proves the pattern: BM25 + `all-MiniLM-L6-v2` embeddings fused with RRF (k=60), retrieving 500× limit per system | HIGH | Reuse `HybridSearch.rrf_search` design verbatim. CEIT-Library's indexable fields come from `Inventory` (+ catalog title/author/description). Exact-match fields (ISBN, call number, title) must still win — RRF ranks handle that naturally since BM25 scores exact tokens highest |
| Result cards with availability status | Users asking "is it available?" expect the assistant to KNOW, not guess. A book recommendation without availability = dead end | MEDIUM | Requires joining `Inventory` + `BorrowTransaction` (active loans) server-side. Availability must come from the DB, never from the LLM — anti-hallucination rule. Card fields: title, author, call number, copies available/total, status badge |
| Chat with inline citations | Any answer claiming facts about the catalog must show sources; the reference `citations` subcommand uses `[1]`, `[2]` numeric markers mapped to a source list below the answer | LOW | Citation UX conventions from reference: numbered markers in text, clickable source list underneath, per-citation title/author. Each citation must map to a real retrieved document id |
| Grounding rules + "I don't know" | Users lose trust permanently after one hallucinated availability/policy claim. Both reference codebases hard-code this: "If the answer isn't in the provided documents, say 'I don't have enough information'"; llm-zc's INSTRUCTIONS: "If you can't answer using FAQ, don't do it yourself. Only use the facts from the FAQ database"; off-topic questions refused | LOW | Encode as system prompt (llm-zc INSTRUCTIONS pattern) + verify in evaluation (golden set must include negative cases where the correct answer IS "I don't know") |
| Library policy Q&A over the rulebook | "Ilang araw ba ang pwede mag-borrow?", "Ano ang penalty ng overdue?" — `RuleHeader` + `RuleRegulation` tables already hold this corpus; the FAQ pattern (question/answer/section) is exactly llm-zc's `ingest.py` FAQ shape | MEDIUM | Corpus = RuleHeader+RuleRegulation rows converted to Q/A records at index time. Reference insight: boost question-field matches (`boost_dict={"question": 3.0}`) and filter by course-equivalent (rule category) |
| Academic paper search | Distinct corpus (`AcademicPaper` + `Author` pivot + advisers). Users search by title keyword, author, year, topic — NOT by availability | MEDIUM | Differences from book search: availability is irrelevant (papers are in-house references, "borrowing" is logged not stocked); year/author/adviser are first-class filter fields, not free text; display = citation-style card (authors, year, advisers), not availability card |
| Summarization | "Summarize this paper/rule" and multi-result synthesis. `summarize` subcommand synthesizes top-N retrieved docs into 3-4 information-dense sentences; Kestra flow 4 shows length/language controls | MEDIUM | What gets summarized: (1) a single academic paper's abstract/description, (2) a policy topic spanning multiple rules, (3) a set of search results ("what are my options?"). Never summarize something not retrieved |
| Chat widget: streaming + typing indicator + history | Users expect a chat to feel like chat, not a form submit. llm-zc's app.py shows the minimum (ask button, spinner); modern expectation is streamed tokens | MEDIUM | Livewire 4 polling/streaming or SSE; persist `chat_messages` table (user_id, role, content, citations, created_at) for history + evaluation sampling. Typing indicator = the llm-zc spinner equivalent |
| Role-aware widget access | Students and librarians must not see the same capabilities (e.g., policy answers are public, but "who borrowed this copy" is staff-only). CEIT-Library already has Gates (`AppServiceProvider`, `AdminOnly`, `LibrarianOrAdmin` middleware) | LOW | Gate at two layers: (1) widget visibility per role, (2) tool/endpoint authorization in the Python sidecar (never trust client role claim — pass role from Laravel auth context) |
| Rate limiting + cost guard | LLM calls cost money per token; students + a shared service account = runaway costs. llm-zc tracks tokens/cost per call (`RAGWithMetrics`, `calc_total_price`); evaluation lessons repeatedly emphasize budget awareness | MEDIUM | Per-user daily message cap (Laravel rate limiter on the Livewire component), per-request token budget, max conversation turns. Log usage to the metrics table |
| Clear error/degradation states | LLM down, sidecar unreachable, index empty → widget must degrade gracefully ("Assistant is temporarily unavailable"), never hang or 500 | MEDIUM | Timeout on sidecar HTTP call, circuit-breaker-ish backoff, friendly fallback message. Existing 200+ PHPUnit suite pattern extends to widget failure cases |

### Differentiators (Competitive Advantage)

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Agentic multi-search instead of one-shot retrieval | One-shot RAG retrieves once with the user's raw phrasing; the agentic loop (llm-zc `rag_agent`) lets the model search, read results, re-search with expanded keywords/corrected spellings, then answer. llm-zc's INSTRUCTIONS literally say: "Make multiple searches... Try to expand your search by using new keywords or corrected spellings based on the results." Library queries are famously loose ("that book about the bridge thing") — multi-search materially raises hit rate | HIGH | Function-calling loop via Responses API with a `search` tool (catalog, papers, policies as separate tools). Cap iterations (max 3-5) for latency/cost. Evaluate with trajectory_score (below) to prove it's better than one-shot before shipping |
| Availability-aware recommendations ("similar to Y") | Not just "find books about X" but "I liked this book — what's similar?" Semantic embeddings over title+description make item-similarity cheap (cosine nearest neighbors in the existing embedding cache). A generic library catalog can't do this | MEDIUM | Item-to-item similarity = reuse of semantic index; needs `description` coverage in catalog records. Combine with availability so recommendations aren't all checked-out books |
| Policy corpus that is the actual database (not a static FAQ) | `RuleHeader`/`RuleRegulation` are living tables; answers always reflect current rules. Static FAQ files rot (llm-zc itself hit this: ground-truth doc IDs "no longer exist in the current FAQ data" — dataset drift broke evaluation) | MEDIUM | Index build = DB snapshot at deploy/refresh cadence; add a re-index trigger when rules change. Drift between DB and index becomes a monitored metric |
| Full evaluation stack with library-specific golden sets | Reference pattern: LLM generates 5 questions per source doc (pydantic structured output, retries, cost-tracked, parallel), then precision/recall/F1@k against retrieved docs, then LLM-as-judge scores answers. For a library: separate golden sets per corpus (books, papers, policies) + a negative set ("I don't know" cases) + citation-accuracy rubric | HIGH | Direct reuse of `evaluation_cli.py` metric design and llm-zc ground-truth generation + judge prompts. This is a milestone target feature, not an add-on |
| User feedback + automatic judge in the widget | llm-zc app.py: +1/-1 buttons per answer + an automatic relevance judge (RELEVANT / PARTLY_RELEVANT / NON_RELEVANT with explanation) saved to the same feedback table. Turns every production answer into evaluation data | MEDIUM | Thumbs up/down on each assistant message; background judge job (queued, not blocking the response); feedback table feeds Grafana and the next golden-set refresh |
| Grafana monitoring of the assistant | llm-zc: response time, prompt/completion tokens, cost per call, judge scores, user feedback — all persisted then dashboarded (dashboard.py). A shared institutional service needs this to justify LLM spend | MEDIUM | Prometheus exporter on the sidecar (requests, latency, tokens, cost, RRF stats) + feedback/judge tables in Postgres; Grafana dashboards per milestone scope |
| Role-aware answer depth (librarian superpowers) | Same query, different capability: students get availability + general policy; librarians additionally get copy-level info (which copy, who holds it, due date) — the assistant becomes a librarian productivity tool, not just a student toy | MEDIUM | Tool-level ACL in sidecar; extra `lookup_borrower`/`copy_status` tools only registered for staff roles. Sensitive: must never leak borrower PII to students |
| Follow-up suggestion chips | llm-zc INSTRUCTIONS end with "ask if there are other areas that the user wants to explore" — institutionalized as clickable follow-ups ("Similar books", "Summarize this paper", "Borrowing rules") | LOW | Cheap engagement win; chip set can be static per answer type or LLM-suggested |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Open web search / "just ask ChatGPT about the topic" | "Assistant should know everything" | The reference flows (1_chat_without_rag vs 2_chat_with_rag) demonstrate exactly why: no retrieval → plausible-sounding wrong answers. Off-campus sources contradict institutional policy; citations become URLs nobody can verify against the catalog | Keep retrieval corpus = catalog + papers + policy tables. Off-topic → "I don't know" (llm-zc INSTRUCTIONS: "offtopic questions shouldn't be answered") |
| Letting the assistant perform borrow/return transactions | "Why can't it just check the book out for me?" | LLM + irreversible state mutation = hallucinated borrows, no audit trail consistency with `QrScanner`'s `DB::transaction` pattern, and OWASP-style prompt-injection risk against real records. The reference codebases deliberately never mutate anything | Assistant links to the existing borrow UI (deep link to the book's action page); actions stay human/QR-scan initiated |
| Fully autonomous multi-agent research (flow 6 pattern) | "Analyst agent + research agent + synthesis" sounds advanced | Multi-agent orchestration multiplies LLM calls, latency, and failure modes for a question that a single RAG agent with a few tools answers better. llm-zc's own agent evaluation found 1 search is usually enough, 2-3 okay, >3 needs justification | Single agent loop with a small, sharp toolset (catalog search, paper search, policy search). Revisit only if evaluation shows trajectory failures |
| Real-time streaming embeddings / re-index on every DB change | "Keep the index always fresh" | Embedding the catalog on every inventory change = cost + complexity with no user-visible gain; the catalog changes slowly. llm-zc's drift lesson cuts the other way too: churn breaks ground truth | Scheduled (nightly) or deploy-time re-index; policy-table changes trigger a targeted re-index of policy docs only |
| LLM-generated availability/policy claims without DB grounding | "Let the model answer from context, it's fine" | The single worst failure for a library: "3 copies available" when there are 0. Context snippets go stale between index and answer | Availability = SQL lookup at answer-assembly time (structured tool result injected as context); policy = retrieved rule text verbatim-quotable |
| Voice input / multilingual everything / persona games | "Cool factor", "Filipino + English + Taglish" | Scope explosion with no retrieval-quality value; voice adds ASR failure modes on top of RAG. Multilingual answers are a model capability, not a feature to build | Ship text chat; allow the model to answer in the language the user asked (free capability, zero build). Persona: one librarian tone, tested by the judge |
| Storing full chat history in the vector index (memory-RAG) | "Assistant should remember my last question" | Pollutes retrieval (conversation text masquerades as catalog content), degrades search quality, complicates evaluation | Session history lives in the `chat_messages` table and is appended to the prompt as conversation context only — never embedded into the search index |

---

## Feature Dependencies

```
[Hybrid Search (BM25+semantic RRF)]
    └──requires──> [Catalog Corpus Export (Inventory + titles/authors/descriptions)]
                        └──requires──> [DB Snapshot / Re-index Job]

[Availability-Aware Results] ──enhances──> [Hybrid Search]
    └──requires──> [Inventory + BorrowTransaction Live Lookup (SQL, not LLM)]

[Chat with Citations] ──enhances──> [Hybrid Search]  (citations map to retrieved doc ids)

[Academic Paper Search] ──requires──> [Paper Corpus Export (AcademicPaper + Author pivot)]

[Policy Q&A] ──requires──> [Policy Corpus (RuleHeader + RuleRegulation)]
    └──requires──> [Re-index on Rule Change]

[Summarization] ──enhances──> [Search Results / Single Paper / Policy Topic]

[Agentic Multi-Search] ──enhances──> [Chat with Citations]  (replaces one-shot retrieval)

[Role-Aware Access] ──governs──> [Chat Widget]
    └──requires──> [Existing Gates: AppServiceProvider + AdminOnly / LibrarianOrAdmin]

[Chat Widget] ──requires──> [Sidecar HTTP API + Auth Context Handoff]
    └──requires──> [Rate Limiting / Cost Guard]

[Evaluation Stack] ──validates──> [Hybrid Search, Agentic Multi-Search, Grounding Rules]
    └──requires──> [Feedback + Judge Tables]
                       └──feeds──> [Grafana Monitoring]

[Grafana Monitoring] ──requires──> [Sidecar Metrics Exporter + Feedback DB]
```

### Dependency Notes

- **Hybrid Search requires Catalog Corpus Export:** The Python sidecar has no Laravel ORM access; CEIT-Library must expose `Inventory`/title/author/description (and `AcademicPaper` + authors, and `RuleHeader`/`RuleRegulation`) as an indexed snapshot. Without this contract, nothing else in the milestone works. This is the P0 dependency.
- **Availability-Aware Results requires live SQL, not the index:** availability changes by the hour (borrows, returns); the retrieval index is days-old. Availability must be a structured post-retrieval join from `BorrowTransaction`/`Inventory`.
- **Agentic Multi-Search enhances Chat with Citations:** it replaces the retrieve-once step; citation tracking must survive multiple tool calls (citation ids come from the final context assembly, not the first search).
- **Role-Aware Access requires the existing Gate system:** `AdminOnly` / `LibrarianOrAdmin` middleware and `User` role methods already exist; the widget only needs to inherit them, and the sidecar needs the role claim passed through the request.
- **Evaluation Stack requires Feedback + Judge tables:** thumbs and judge verdicts are the production-quality signal the milestone requires; the golden sets are the offline signal. Both must exist for the "full evaluation stack" target.
- **Policy Q&A conflicts with static FAQ files:** if policy answers come from a hand-written FAQ file instead of `RuleHeader`/`RuleRegulation`, drift begins immediately (llm-zc experienced this: ground-truth records pointed at deleted docs). The corpus must be DB-derived.
- **Summarization conflicts with open-ended text input:** summarizing arbitrary pasted text (user uploads) is a different feature than summarizing retrieved library content; scoping it to retrieved content only keeps evaluation possible.

---

## MVP Definition

### Launch With (v1)

- [ ] **Hybrid search over the catalog** — BM25 + semantic RRF (reference implementation proven); the core "find me books about X" promise
- [ ] **Result display with availability from live SQL** — book cards with copies available/total; availability never LLM-generated
- [ ] **Chat with citations + grounding rules** — numbered `[N]` citations mapped to sources; "I don't have enough information" behavior; off-topic refusal
- [ ] **Policy Q&A over RuleHeader/RuleRegulation corpus** — DB-derived FAQ index, boost question fields
- [ ] **Livewire chat widget** — streaming/typing indicator, persisted history, role-aware access, rate limit, graceful degradation
- [ ] **Search evaluation** — golden dataset (LLM-generated from corpus docs) + precision/recall/F1@k, run in CI before merge
- [ ] **Feedback buttons + judge** — thumbs up/down and automatic relevance judge saved per answer (cheap, feeds everything else)
- [ ] **Cost/usage guard** — per-user daily cap + token budget + usage logging

### Add After Validation (v1.x)

- [ ] **Academic paper search** — trigger: catalog search validated; papers need different card layout (authors/year/advisers) and no availability
- [ ] **Summarization** — trigger: multi-result searches prove users want synthesis, not just lists
- [ ] **Agentic multi-search** — trigger: golden-set evaluation shows one-shot recall failing on paraphrase/sloppy queries; ship only if trajectory+answer scores beat one-shot
- [ ] **"Similar to Y" recommendations** — trigger: semantic index quality confirmed on catalog descriptions
- [ ] **Grafana dashboards** — trigger: usage exists to visualize (feedback, judge, latency, cost)

### Future Consideration (v2+)

- [ ] **Librarian-only tools (copy-level lookup)** — why defer: PII exposure risk needs careful role-scoping and audit; a whole phase
- [ ] **Follow-up suggestion chips** — why defer: polish; needs post-launch query analytics to pick good chips
- [ ] **Multi-agent research flows** — why defer: cost/latency; only if evaluation shows single-agent trajectory failures
- [ ] **Voice/multilingual persona features** — why defer: zero retrieval-quality value; scope risk

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Hybrid search (BM25+semantic RRF) | HIGH | HIGH | P1 |
| Availability in results (live SQL) | HIGH | MEDIUM | P1 |
| Citations + grounding rules | HIGH | LOW | P1 |
| Policy Q&A (DB-derived corpus) | HIGH | MEDIUM | P1 |
| Chat widget (streaming, history, role-aware, rate-limited) | HIGH | MEDIUM | P1 |
| Search golden set + precision/recall/F1 | HIGH | MEDIUM | P1 |
| Feedback + auto-judge | MEDIUM | LOW | P1 |
| Usage/cost guard + logging | MEDIUM | LOW | P1 |
| Academic paper search | MEDIUM | MEDIUM | P2 |
| Summarization | MEDIUM | MEDIUM | P2 |
| Agentic multi-search | MEDIUM | HIGH | P2 |
| "Similar to Y" recommendations | MEDIUM | MEDIUM | P2 |
| Grafana monitoring | MEDIUM | MEDIUM | P2 |
| Librarian-only tools | MEDIUM | HIGH | P3 |
| Follow-up suggestion chips | LOW | LOW | P3 |
| Multi-agent orchestration | LOW | HIGH | P3 |

**Priority key:**
- P1: Must have for launch
- P2: Should have, add when possible
- P3: Nice to have, future consideration

---

## Competitor Feature Analysis

| Feature | Generic AI Chatbot (ChatGPT-style, no library data) | Library Discovery Layer (OPAC/Ex Libris Primo-class search) | Our Approach |
|---------|--------------|--------------|--------------|
| Book search | Can't see the catalog; guesses titles and hallucinates availability | Precise faceted search, zero conversational flexibility; no "I liked X" queries | Hybrid BM25+semantic RRF over our own catalog — exact fields (ISBN/call no.) via BM25, paraphrase queries via embeddings |
| Availability | Invents it | Accurate but buried in record pages, no chat surface | Live `Inventory`/`BorrowTransaction` join injected into chat answers as structured context; badge on every card |
| Citations | Fabricated or generic | None in conversational form; records link internally | Numbered `[N]` markers mapped to real catalog/policy records; judge verifies citation accuracy |
| Policy answers | Vague generalities, may contradict institutional rules | Static policy PDFs — no Q&A | DB-derived FAQ index over `RuleHeader`/`RuleRegulation`; re-index on rule change; grounding enforced by prompt + negative golden cases |
| Paper search | Trained-data only, wrong authors/years | Keyword search over metadata | Semantic + keyword over `AcademicPaper`+authors with author/year/adviser fields surfaced as structured filters |
| Summarization | Good, but of arbitrary text | None | Only of retrieved library content (a paper, a rule topic, a result set) — keeps citations intact |
| Trust & evaluation | No evidence trail, no feedback loop | N/A | Golden sets per corpus + LLM-as-judge (answer + trajectory scores) + user thumbs + Grafana — the whole reference evaluation stack |
| Roles | Same answer for everyone | Login-based visibility, no chat | Role-aware tools: students get discovery; librarians get copy-level operational answers without PII leaks |

---

## Sources

- `rag-search-engine` (WSL ~/workspace/rag-search-engine): `cli/augmented_generation_cli.py` (rag/summarize/citations/question subcommands, citation + grounding prompts), `cli/lib/hybrid_search.py` (BM25 + semantic RRF fusion, k=60, weighted-search alternative), `cli/evaluation_cli.py` (golden dataset precision/recall/F1@k), `data/golden_dataset.json` (query → relevant_docs shape)
- `llm-zc` (D:\ai-eng\llm-zc): `assistant.py` + `rag_helper.py` (INSTRUCTIONS grounding rules, PROMPT_TEMPLATE, one-shot `rag()` vs agentic `rag_agent()` function-calling loop), `app.py` (chat UI, metrics display, +1/-1 feedback, built-in judge), `judge.py` (online RELEVANT/PARTLY_RELEVANT/NON_RELEVANT rubric), `04-evaluation/lessons/02-03` (LLM-generated ground truth, structured output, retries, cost tracking, parallelization), `04-evaluation/lessons/13-14` (LLM-as-judge good/bad + reasoning; agent answer_score + trajectory_score rubrics), `03-orchestration/flows/*.yaml` (with/without-RAG contrast, summarization agent, multi-agent research — used as anti-feature evidence)
- CEIT-Library codebase: `app/Models/*` (Inventory, BorrowTransaction, AcademicPaper, Author, RuleHeader, RuleRegulation, User, Role), `app/Livewire/*` (component patterns), `app/Providers/AppServiceProvider.php` (Gates), `database/migrations/*` (schema), README.md (existing features — not re-researched)

---
*Feature research for: CEIT-Library v2.0 AI Assistant milestone*
*Researched: 2026-08-13*
