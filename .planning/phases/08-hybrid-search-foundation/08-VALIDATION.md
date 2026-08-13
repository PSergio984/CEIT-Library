---
phase: 8
slug: hybrid-search-foundation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-08-13
---

# Phase 8 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11 (Laravel feature/unit) + pytest (Python sidecar) |
| **Config file** | `phpunit.xml` (exists); `ai-sidecar/tests/` + `pyproject.toml` (new repo) |
| **Quick run command** | `php artisan test --filter=Ai` |
| **Full suite command** | `php artisan test && cd <sidecar> && pytest` |
| **Estimated runtime** | ~60 seconds (PHP) + ~90 seconds (pytest with cached embeddings) |

---

## Sampling Rate

- **After every task commit:** Run the task's mapped test filter (table below)
- **After every plan wave:** Run the full mapped suite for that wave
- **Before `$gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 150 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| — | — | 0 | SEARCH-07 | — | N/A | — | — | — | ⬜ pending |

*Populated by the planner — each task maps to a PHPUnit test (export JSON shape, observer dispatch, AiService call, reconcile) or pytest (RRF fusion dominance, golden-set P/R/F1). Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] Laravel: `tests/Feature/Ai/` test dir — stubs for export shape + observer dispatch (factories for AcademicPaper/Author/Inventory/RuleHeader/RuleRegulation already exist)
- [ ] Sidecar repo scaffold: `tests/test_search.py`, `tests/test_fusion.py` — RRF dominance tests (Pitfall 4, debug_hybrid.py lesson)
- [ ] Sidecar: `data/golden_dataset.json` — 25-case draft from 08-RESEARCH.md, id-based matching

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Papers-page search box returns hybrid results with filters | SEARCH-01 | End-to-end Laravel↔sidecar on real corpus | UAT script in 08-RESEARCH.md: type title fragment → correct paper; apply department filter → only that dept; Taglish paraphrase query → sensible results |
| `/health` reports index coverage after export+rebuild | SEARCH-07 | Requires live sidecar process | Start sidecar, run `ai:export-corpus`, hit `/index/rebuild`, check `/health` counts vs DB |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 150s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
