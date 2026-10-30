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
The deterministic recommendation list for a catalog paper, produced by `SimilarPapersService` calling the sidecar's `/recommend` (the paper is the single seed and is also excluded) — a list, never an LLM answer.
_Avoid_: Recommendations from the AI, "books like this" via chat

**Personal recommendations**:
The deterministic, borrow-seeded "Recommended for you" list for a user, built by `PersonalRecommendationsService` — seeded from the user's recent borrows, falling back to the popular list only on cold start; only paper ids reach the Sidecar.
_Avoid_: Recommended by the AI, user profile

**Seed**:
A paper id the Sidecar is asked to find neighbours of — the viewed paper for Similar books, a recent borrow for Personal recommendations.
_Avoid_: query, anchor user

**Operational question**:
A staff-tier question about physical copies and circulation state — counts, per-copy status, due-back times — answered from library data, never from corpora or LLM guesswork.
_Avoid_: Ops query, staff mode

**Borrower identity**:
The binding between a person and a loan — who holds which copy. Never surfaces through the Assistant at any role tier.
_Avoid_: User data, patron info

**Bag deposit**:
A record of belongings left at the entrance counter, bound to a numbered Slot and to the user who scanned it in — opened and closed by counter scans, independent of Attendance.
_Avoid_: baggage ticket, locker rental

**Courier**:
The user who physically interacts with the counter for a Bag deposit — depositing user at drop-off, claimant at pickup; the two may differ.
_Avoid_: owner, bag holder

**Slot**:
The numbered bin position a Bag deposit is stored in; bins carry the number physically, but no token is handed to the student — claim is digital. Freed when the deposit closes.
_Avoid_: locker, box

**Claim code**:
The short shareable code (4–6 digits) generated per Bag deposit and visible to the depositor; any registered user who scans their own QR and provides it is bound as claimant — delegation is sharing the code, and it can never be "lost" because it lives in the account.
_Avoid_: claim PIN on a physical tag, ticket number

**Rescue lookup**:
The staff-side override for a claimant with no QR or Claim code — the librarian searches open deposits by depositor name, slot, or description, releases with a mandatory written note and physical ID check, and the release is flagged in the audit trail.
_Avoid_: manual claim, backdoor claim

**Temporary access**:
A logged scan event that opens a Bag deposit's Slot for item retrieval without closing the deposit; notifies the depositor when the accessor is someone else.
_Avoid_: partial claim, early claim

**Unclaimed deposit**:
A Bag deposit still open past closing; listed on the counter's unclaimed view and swept with a depositor notification.
_Avoid_: lost bag, abandoned item
