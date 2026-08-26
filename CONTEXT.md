# CEIT Library

A library management system for the CEIT (College of Engineering and Information Technology) — academic paper catalog, rulebook/policy corpus, borrowing workflows, and (in progress) an AI assistant built on a hybrid-search sidecar.

## Language

**Assistant**:
The in-app chat assistant students and librarians talk to; answers grounded in the library's corpora.
_Avoid_: Chatbot, AI widget

**Conversation**:
A persisted, user-owned chat session between one user and the Assistant, made of Messages.
_Avoid_: Thread, chat log

**Message**:
One exchange within a Conversation — a user question or an Assistant answer (which may carry Citations).
_Avoid_: Entry, turn

**Citation**:
A numbered `[N]` marker in an Assistant answer that links to a real retrieved record (a catalog paper or a policy rule), with the source list rendered under the answer.
_Avoid_: Reference, source note

**Grounding**:
The rule that an Assistant answer is drawn only from retrieved corpus content; when retrieval finds nothing usable, the Assistant refuses instead of guessing ("I don't have enough information").
_Avoid_: Hallucination guard

**Policy question**:
A question answered from the policy corpus (RuleHeader / RuleRegulation records) — the rulebook Q&A surface.
_Avoid_: Rules question, FAQ query

**Catalog question**:
A question answered from the catalog corpus (academic papers), the same corpus hybrid search already covers.

**Sidecar**:
The FastAPI service that owns the search index and (in Phase 9) the chat/RAG endpoint; the Laravel app talks to it over HTTP with a shared token.
_Avoid_: AI server, backend AI

**Availability**:
Live copy counts (`available`/`total` per catalog id, plus a fetch-time `checked_at`) hydrated from `inventory` rows at render time by `AvailabilityService` — never from the sidecar or the LLM.
_Avoid_: Stock, status count from AI

**Similar books**:
The deterministic recommendation list for a catalog paper, produced by running the paper's title as a `/search` query through `SimilarPapersService` (title-as-query, no metadata filters, self-excluded by id) — a list, never an LLM answer.
_Avoid_: Recommendations from the AI, "books like this" via chat

**Operational question**:
A staff-tier question about physical copies and circulation state — counts, per-copy status, due-back times — answered from library data, never from corpora or LLM guesswork.
_Avoid_: Ops query, staff mode

**Borrower identity**:
The binding between a person and a loan — who holds which copy. Never surfaces through the Assistant at any role tier.
_Avoid_: User data, patron info
