# Phase 8: Hybrid Search Foundation - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-08-13
**Phase:** 8-Hybrid Search Foundation
**Areas discussed:** Corpus scope & fields, Search surface in Phase 8, Index freshness semantics, Language support, Sidecar repo placement, Bootstrap golden set

---

## Corpus Scope & Fields

| Option | Description | Selected |
|--------|-------------|----------|
| Catalog + policy | RuleHeader/RuleRegulation + catalog; papers corpus later | ✓ |
| Catalog only | Policy deferred to Phase 9 | |
| Everything now | Pull Phase 11 corpus forward | |

**User's choice:** Catalog + policy.
**Notes:** User asked to see the actual schema first. Schema surfaced: catalog = `academic_papers` (+ authors pivot, advisers, dean) — no separate books table. Searchable free-text: title, people names, catalog_code. Filters: paper_type, department, publication_year. Availability excluded (live in Phase 10). Policy chunking: header + regulation docs.

| Option | Description | Selected |
|--------|-------------|----------|
| Title, type, dept, people | All metadata free-text | |
| Title + people + code | Free text = title, people, code; metadata = filters | ✓ |

**User's choice:** "for now title, am i right? paper type you could filter it using filters, same with departments and other metadata" → title + people + catalog_code free-text; paper_type/department/year as filters.

---

## Search Surface in Phase 8

| Option | Description | Selected |
|--------|-------------|----------|
| Minimal in-app search | Search box on existing papers page | ✓ |
| Headless only | API + tests, no UI | |
| Admin debug page | /ai/debug for trying queries | |

**User's choice:** Minimal in-app search on the existing academic papers browse page, with paper_type/department/year filter dropdowns rendered in Phase 8.

---

## Index Freshness Semantics

| Option | Description | Selected |
|--------|-------------|----------|
| Event + scheduled + nightly | Observers debounced → rebuild; hourly export; nightly reconciliation | ✓ |
| Scheduled only | Hourly export only | |
| Immediate per edit | No debounce | |

**User's choice:** Event + scheduled + nightly. Deletions → immediate rebuild. Always full rebuild from exported JSON.

---

## Language Support

| Option | Description | Selected |
|--------|-------------|----------|
| Multilingual | paraphrase-multilingual-MiniLM-L12-v2 | ✓ |
| English-only | all-MiniLM-L6-v2 | |

**User's choice:** Multilingual. Policy corpus is English; titles mostly English with localized proper nouns (Valenzuela, Maysan) — BM25 covers those.

---

## Sidecar Repo Placement

| Option | Description | Selected |
|--------|-------------|----------|
| Subdir in this repo | ai-sidecar/ | |
| Separate repo | Own GitHub repo | ✓ |

**User's choice:** Separate repo (matches rag-search-engine/llm-zc split). Fresh repo, port pieces from rag-search-engine (no clone). Corpus via configured shared path (Laravel exports to storage/app/ai-corpus). Dev loop: local venv/uv.

---

## Bootstrap Golden Set

| Option | Description | Selected |
|--------|-------------|----------|
| Agent drafts, user reviews | Drafted from real catalog data | ✓ |
| User authors | User writes seed queries | |

**User's choice:** Agent drafts 20-30 queries (exact title, code, paraphrase, people, Taglish, policy, negatives), user reviews. Lives at `data/golden_dataset.json` in the sidecar repo.

**Notes:** User provided `CEIT ACADEMIC PAPER.xlsx` (repo root) — real library catalog as a GUIDE for realistic values (titles, names, departments, paper types, adviser fields). MUST NOT be used as production data (data protection). Sheets: Electrical Engineering, Civil Engineering, Information Technology, Documents. ~137 EE papers. Columns: TYPE OF ACADEMIC PAPER, CATALOG NUMBER, APPROVED OFFICIAL LONG TITLE, COPIES, MEMBER 1-7, TECHNICAL ADVISER, RESEARCH PROJECT ADVISER, DEPARTMENT, DEAN.

---

## the agent's Discretion

- Debounce window for event-driven rebuilds
- RRF parameters / BM25 tuning (port from rag-search-engine)
- Result card layout on the papers page
- Export JSON schema details

## Deferred Ideas

- None — discussion stayed within phase scope.
