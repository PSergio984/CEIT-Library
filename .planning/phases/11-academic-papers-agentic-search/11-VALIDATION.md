---
phase: 11
slug: academic-papers-agentic-search
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-08-15
---

# Phase 11 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.5 (Laravel) + pytest (sidecar) |
| **Config file** | `phpunit.xml` / `ceit-ai-sidecar\pytest.ini` (or pyproject) |
| **Quick run command** | `php artisan test --filter <class>` / `pytest tests/test_<module>.py::<test> -x` |
| **Full suite command** | `php artisan test` / `pytest` |
| **Estimated runtime** | ~53s (Laravel) + ~10s (sidecar) |

---

## Sampling Rate

- **After every task commit:** Run the touched suite's quick command (scoped `--filter`)
- **After every plan wave:** Run `php artisan test` + `pytest` (both repos)
- **Before `$gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 90 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 11-01-xx | 01 | 1 | SEARCH-05 | T-11-01 | rich doc shape, no corpus tag break | unit | `php artisan test --filter CorpusExporterTest` | ✅ | ⬜ pending |
| 11-01-xx | 01 | 1 | SEARCH-05 | T-11-01 | new author/adviser filters closed-schema | unit | `pytest tests/test_api.py::test_search_author_filter -x` | ✅ | ⬜ pending |
| 11-02-xx | 02 | 1 | SEARCH-05 | T-11-01 | paper tab mode + filter passthrough | feature | `php artisan test --filter AcademicPaperIndexPaperTabTest` | ✅ | ⬜ pending |
| 11-03-xx | 03 | 2 | CHAT-05 | T-11-02 | tool loop cap, fail-closed refusal | unit | `pytest tests/test_agentic_loop.py -x` | ✅ | ⬜ pending |
| 11-03-xx | 03 | 2 | CHAT-05 | T-11-02 | activity + citations SSE frames | feature | `php artisan test --filter ChatWidgetAgenticTest` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `ceit-ai-sidecar\tests\test_agentic_loop.py` — stubs for CHAT-05
- [ ] `tests/Feature/AcademicPaperIndexPaperTabTest.php` — stubs for SEARCH-05
- [ ] `tests/Feature/ChatWidgetAgenticTest.php` — stubs for CHAT-05 frames

*If none: "Existing infrastructure covers all phase requirements."*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Live loop quality feel (3-round latency, activity lines) | CHAT-05 | Requires live OpenRouter key + sidecar | `SIDECAR_LIVE_TEST=1` gated sidecar test + manual chat smoke per `09-RESEARCH.md` §6 |

*If none: "All phase behaviors have automated verification."*

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 90s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** {pending / approved YYYY-MM-DD}
