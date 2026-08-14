# Requirements: CEIT-Library — Milestone v2.0 AI Assistant

**Defined:** 2026-08-13
**Core Value:** Users can discover books and papers, and get trustworthy, cited answers about the library — through a conversational AI librarian.

## v1 Requirements

Requirements for milestone v2.0. Each maps to roadmap phases.

### Search

- [x] **SEARCH-01**: User can search the library catalog with natural-language queries (hybrid BM25 + semantic, RRF fusion)
- [x] **SEARCH-02**: Search results show live availability status (copies available/total) sourced from Inventory/BorrowTransaction, never from the LLM
- [ ] **SEARCH-03**: Chat answers include numbered [N] citations linked to real retrieved catalog records
- [ ] **SEARCH-04**: Assistant answers "I don't have enough information" instead of guessing when retrieval finds nothing (grounding rules)
- [ ] **SEARCH-05**: User can search academic papers by topic, author, year, or adviser
- [x] **SEARCH-06**: User can ask for "books similar to X" and get recommendation results
- [x] **SEARCH-07**: Catalog, paper, and policy data sync from the Laravel database into the search index automatically (export + rebuild)

### Chat

- [ ] **CHAT-01**: User can chat with the assistant through an in-app widget with streamed responses
- [ ] **CHAT-02**: Chat history persists and remains viewable across sessions
- [ ] **CHAT-03**: Assistant access and answer depth are role-aware (student vs librarian)
- [ ] **CHAT-04**: User can ask library policy questions and get answers grounded in the rulebook (RuleHeader/RuleRegulation corpus)
- [ ] **CHAT-05**: Assistant runs agentic multi-step search (function-calling loop) when one-shot retrieval is insufficient
- [ ] **CHAT-06**: Librarians can get copy-level operational answers without PII leaks

### Evaluation

- [ ] **EVAL-01**: Golden datasets exist per corpus (catalog, papers, policy) including negative "I don't know" cases
- [ ] **EVAL-02**: Search quality is measured with precision/recall/F1@k against golden sets
- [ ] **EVAL-03**: Answer quality is scored by an LLM-as-judge (answer + citation accuracy)
- [ ] **EVAL-04**: Users can rate answers (thumbs up/down) and feedback feeds the evaluation pipeline

### Operations

- [ ] **OPS-01**: Usage, latency, and cost metrics are collected (Prometheus) and visible in Grafana dashboards
- [ ] **OPS-02**: Rate limits and cost guards prevent runaway token usage
- [ ] **OPS-03**: Chat queries and history are sanitized of PII at the Livewire boundary
- [ ] **OPS-04**: The sidecar stack deploys reproducibly via Docker compose (sidecar + Prometheus + Grafana)

## v2 Requirements

Deferred to a future milestone. Tracked but not in this roadmap.

### Interaction

- **CHAT-07**: User can ask the assistant to summarize a paper, a policy topic, or a set of search results (deferred by scoping decision)

## Out of Scope

| Feature | Reason |
|---------|--------|
| Open web search (agent browsing the internet) | Anti-feature per research: expands attack surface (SSRF, prompt injection) and hallucination risk; catalog-grounded retrieval only |
| LLM-executed borrow/attendance transactions | Safety: the LLM never mutates library state; all mutations stay in existing Livewire workflows |
| Multi-agent orchestration | Research P3: no user-facing value at this scale; single agentic loop suffices |
| Follow-up suggestion chips | Research P3: nice-to-have; reconsider after launch feedback |
| Memory-RAG (long-term personalization) | Anti-feature per research: privacy risk and complexity without a validated user need |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| SEARCH-01 | Phase 8: Hybrid Search Foundation | Complete |
| SEARCH-02 | Phase 10: Live Availability & Similar-Book Recommendations | Complete |
| SEARCH-03 | Phase 9: RAG Chat & Policy Q&A | Pending |
| SEARCH-04 | Phase 9: RAG Chat & Policy Q&A | Pending |
| SEARCH-05 | Phase 11: Academic Papers & Agentic Search | Pending |
| SEARCH-06 | Phase 10: Live Availability & Similar-Book Recommendations | Complete |
| SEARCH-07 | Phase 8: Hybrid Search Foundation | Complete |
| CHAT-01 | Phase 9: RAG Chat & Policy Q&A | Pending |
| CHAT-02 | Phase 9: RAG Chat & Policy Q&A | Pending |
| CHAT-03 | Phase 12: Role-Aware Access & Librarian Tools | Pending |
| CHAT-04 | Phase 9: RAG Chat & Policy Q&A | Pending |
| CHAT-05 | Phase 11: Academic Papers & Agentic Search | Pending |
| CHAT-06 | Phase 12: Role-Aware Access & Librarian Tools | Pending |
| EVAL-01 | Phase 13: Evaluation Stack | Pending |
| EVAL-02 | Phase 13: Evaluation Stack | Pending |
| EVAL-03 | Phase 13: Evaluation Stack | Pending |
| EVAL-04 | Phase 13: Evaluation Stack | Pending |
| OPS-01 | Phase 14: Monitoring, Hardening & Reproducible Deployment | Pending |
| OPS-02 | Phase 14: Monitoring, Hardening & Reproducible Deployment | Pending |
| OPS-03 | Phase 14: Monitoring, Hardening & Reproducible Deployment | Pending |
| OPS-04 | Phase 14: Monitoring, Hardening & Reproducible Deployment | Pending |

**Coverage:**
- v1 requirements: 21 total
- Mapped to phases: 21
- Unmapped: 0 ✅ (full traceability)

---
*Requirements defined: 2026-08-13*
*Last updated: 2026-08-13 after milestone scoping*
