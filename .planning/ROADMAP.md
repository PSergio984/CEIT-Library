# Roadmap: CEIT-Library

## Overview

CEIT-Library has shipped three milestones — frontend modernization and security (v1.1), architecture and analytics (v1.2), and logging/QR hardening (v1.3). Milestone v2.0 gives the library an AI librarian: a RAG agent over the catalog, academic papers, and the library policy corpus, delivered through an in-app chat widget, measured by a full evaluation stack, and run behind Prometheus/Grafana monitoring.

## Milestones

- ✅ **v1.1 Bug Fixes & Security Improvements** — Phases 1-6 (shipped 2026-05-18)
- ✅ **v1.2 Architecture & Analytics** — Phases 1-2 (shipped)
- ✅ **v1.3 Security & Privacy Hardening** — Phase 7 (shipped)
- 🚧 **v2.0 AI Assistant** — Phases 8-14 (in progress — roadmap defined 2026-08-13)

<details>
<summary>✅ v1.1 Bug Fixes & Security Improvements (Phases 1-6) — SHIPPED 2026-05-18</summary>

### Phase 1: Modernization & Frontend Bug Fixes
**Goal:** Upgrade to Livewire v4, modernize dependencies, and resolve critical frontend interaction issues.
**Scope:** Livewire v3→v4 upgrade, Mary UI v2 / Tailwind v4 / daisyUI v5, Admin Table modal fix, QR Scanner mobile camera fix, initial frontend security audit, mobile PWA expansion & hybrid navigation.

### Phase 2: Security Hardening
**Goal:** Enhance application security by auditing middleware and validation rules.

### Phase 3: Stability & Performance
**Goal:** Address remaining failing tests, optimize database queries, and transform the entry point into a premium landing page.
**Plans:** 7/7 executed — 03-01..03-07 (auth/middleware/UI/notification test fixes, DB optimization, Liquid Glass landing page, branding & SEO, auth reskin).

### Phase 4: PWA Hardening & Offline Resilience
**Goal:** Upgrade the PWA from basic manifest registration to a resilient, native-like experience.
**Scope:** Cache-First `injectManifest`, App Badging API, branded install banner, offline detection & QR safety overlays.

### Phase 5: Workflow Optimization & UX Polish
**Goal:** Resolve librarian system bugs and enhance UX through performance refinements.
**Scope:** Librarian Batch Service refactor, skeleton loaders for tables, hover prefetching, Loan Trends & Top Borrowers widgets.

### Phase 6: Stabilization & Automation
**Goal:** Achieve 100% test coverage and automate critical background tasks.
**Scope:** `transactions:check-overdue` scheduling, Welcome Email flow, 90 manual test cases → automated tests, PHP 8.4 stabilization run.

</details>

<details>
<summary>✅ v1.2 Architecture & Analytics (Phases 1-2) — SHIPPED</summary>

### Phase 1: Structural Integrity
**Goal:** Decouple "fat" Livewire components and consolidate model behavior.
**Scope:** `BorrowService`/`AttendanceService` extraction, unified `BorrowTransaction` `booted` events, Livewire 3 `#[Validate]` attributes.

### Phase 2: Advanced Analytics & Reporting
**Goal:** Expand dashboard utility and optimize query performance.
**Scope:** Cached "Quick Stats" mini-cards, PDF export via `laravel-dompdf`, cache-optimized repeated queries.

</details>

<details>
<summary>✅ v1.3 Security & Privacy Hardening (Phase 7) — SHIPPED</summary>

### Phase 7: Logging and QR Resiliency
**Goal:** Implement offline-first QR codes and prevent PII leakage in logs.
**Scope:** Removed strict 60-second QR timestamp validation (offline screenshot use), kept single-use nonce caching, Global Log Sanitization via `PiiSanitizerProcessor`.

</details>

## Milestone: AI Assistant (v2.0) — IN PROGRESS

**Milestone Goal:** Give CEIT-Library an AI librarian — a RAG agent that finds books with live availability, answers questions with citations and grounding, searches academic papers, answers library-policy questions, and serves through a role-aware chat widget backed by a full evaluation stack and Grafana monitoring.

### Phase 8: Hybrid Search Foundation
**Goal**: Walking skeleton: Laravel exports the catalog + policy corpus (`ai:export-corpus`), the FastAPI sidecar serves hybrid BM25+semantic search (RRF fusion), and event-driven + scheduled sync with nightly reconciliation keeps the index fresh.
**Depends on**: Phase 7 (v1.3)
**Requirements**: [SEARCH-01, SEARCH-07]
**Success Criteria** (what must be TRUE):
  1. User can find catalog records with natural-language queries (paraphrase or exact ISBN/title) via hybrid BM25 + semantic search.
  2. Catalog changes appear in search results within minutes of an edit, and nightly reconciliation confirms the index matches the database.
  3. `ai:export-corpus` reproduces the corpus JSON files from the database on demand, and the sidecar `/health` reports index coverage.
  4. Search quality is measurable against a bootstrap golden set from day one.
**Plans**: TBD

### Phase 9: RAG Chat & Policy Q&A
**Goal**: One-shot `/chat` with numbered citations and strict grounding rules, library policy Q&A over the RuleHeader/RuleRegulation corpus, and the Livewire chat widget with streamed responses and persisted history.
**Depends on**: Phase 8
**Requirements**: [SEARCH-03, SEARCH-04, CHAT-01, CHAT-02, CHAT-04]
**Success Criteria** (what must be TRUE):
  1. User can chat with the assistant through the in-app widget and watch answers stream in.
  2. Every answer includes numbered [N] citations linked to real retrieved catalog records.
  3. Assistant answers "I don't have enough information" instead of guessing when retrieval finds nothing.
  4. User can ask library policy questions and get answers grounded in the rulebook corpus.
  5. Chat history persists and remains viewable across sessions.
**Plans**: TBD

### Phase 10: Live Availability & Similar-Book Recommendations
**Goal**: Enrich search results and chat citations with live copy availability resolved from Inventory/BorrowTransaction (never LLM-generated), and add "books similar to X" recommendations.
**Depends on**: Phase 9
**Requirements**: [SEARCH-02, SEARCH-06]
**Success Criteria** (what must be TRUE):
  1. Search results and cited books show live copies available/total, sourced from the database with a check timestamp — never decided by the model.
  2. User can ask "books similar to X" and get a list of recommendation results.
**Plans**: TBD

### Phase 11: Academic Papers & Agentic Search
**Goal**: Add the academic paper corpus (AcademicPaper + authors + advisers) with paper-specific chunking and topic/author/year/adviser search, and enable agentic multi-step search (function-calling loop) when one-shot retrieval is insufficient.
**Depends on**: Phase 9
**Requirements**: [SEARCH-05, CHAT-05]
**Success Criteria** (what must be TRUE):
  1. User can search academic papers by topic, author, year, or adviser and open a paper's catalog page from the result.
  2. Assistant resolves multi-hop questions that one-shot retrieval cannot answer, via a capped agentic search loop.
**Plans**: TBD

### Phase 12: Role-Aware Access & Librarian Tools
**Goal**: Make assistant access and answer depth role-aware through existing Gates, and give librarians copy-level operational answers without PII leaks.
**Depends on**: Phase 9
**Requirements**: [CHAT-03, CHAT-06]
**Success Criteria** (what must be TRUE):
  1. Student users get student-appropriate answers, while librarians unlock deeper operational detail for the same questions.
  2. Librarian copy-level answers never expose borrower PII in responses, logs, or metrics.
**Plans**: TBD

### Phase 13: Evaluation Stack
**Goal**: Golden datasets per corpus (including negative "I don't know" cases), precision/recall/F1@k retrieval metrics, LLM-as-judge answer scoring, and user feedback flowing back into the evaluation pipeline.
**Depends on**: Phases 9-11
**Requirements**: [EVAL-01, EVAL-02, EVAL-03, EVAL-04]
**Success Criteria** (what must be TRUE):
  1. Golden datasets exist for the catalog, papers, and policy corpora, including negative cases where the correct answer is "I don't know".
  2. Search quality is measured with precision/recall/F1@k against the golden sets.
  3. Answer quality (including citation accuracy) is scored by an LLM-as-judge.
  4. User thumbs up/down feedback feeds the evaluation pipeline for tuning.
**Plans**: TBD

### Phase 14: Monitoring, Hardening & Reproducible Deployment
**Goal**: Prometheus metrics + Grafana dashboards, rate limits and cost guards, PII sanitization at the Livewire boundary, and reproducible docker compose deployment of the whole stack.
**Depends on**: Phase 13
**Requirements**: [OPS-01, OPS-02, OPS-03, OPS-04]
**Success Criteria** (what must be TRUE):
  1. Usage, latency, and cost metrics are visible in Grafana dashboards.
  2. Rate limits and cost guards prevent runaway token usage.
  3. Chat queries and history are sanitized of PII before they leave the Laravel boundary.
  4. Sidecar + Prometheus + Grafana deploy reproducibly via docker compose.
**Plans**: TBD

## Progress

**Execution Order:** Phases execute in numeric order: 8 → 9 → 10 → 11 → 12 → 13 → 14

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Modernization & Frontend Bug Fixes | v1.1 | — | Complete | 2026-05-18 |
| 2. Security Hardening | v1.1 | — | Complete | 2026-05-18 |
| 3. Stability & Performance | v1.1 | 7/7 | Complete | 2026-05-18 |
| 4. PWA Hardening & Offline Resilience | v1.1 | — | Complete | 2026-05-18 |
| 5. Workflow Optimization & UX Polish | v1.1 | — | Complete | 2026-05-18 |
| 6. Stabilization & Automation | v1.1 | — | Complete | 2026-05-18 |
| 1. Structural Integrity | v1.2 | — | Complete | — |
| 2. Advanced Analytics & Reporting | v1.2 | — | Complete | — |
| 7. Logging and QR Resiliency | v1.3 | — | Complete | — |
| 8. Hybrid Search Foundation | v2.0 | 4/6 | In Progress|  |
| 9. RAG Chat & Policy Q&A | v2.0 | 0/TBD | Not started | - |
| 10. Live Availability & Recommendations | v2.0 | 0/TBD | Not started | - |
| 11. Academic Papers & Agentic Search | v2.0 | 0/TBD | Not started | - |
| 12. Role-Aware Access & Librarian Tools | v2.0 | 0/TBD | Not started | - |
| 13. Evaluation Stack | v2.0 | 0/TBD | Not started | - |
| 14. Monitoring, Hardening & Deployment | v2.0 | 0/TBD | Not started | - |

---

*Roadmap v2.0 created: 2026-08-13 (Phases 8-14, continuous numbering from v1.3's Phase 7)*
