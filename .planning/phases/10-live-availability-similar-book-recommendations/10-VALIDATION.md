---
phase: 10
slug: live-availability-similar-book-recommendations
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-08-14
---

# Phase 10 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit (Laravel feature + unit) · pytest (sidecar) |
| **Config file** | `phpunit.xml` · `ceit-ai-sidecar/pyproject.toml` |
| **Quick run command** | `php artisan test --filter=Availability\|Similar` (per-class `--filter` calls on PowerShell — no union filters) |
| **Full suite command** | `php artisan test` (Laravel ~52s) + `pytest` in `ceit-ai-sidecar` |
| **Estimated runtime** | ~60 seconds (Laravel full) · ~20s sidecar |

---

## Sampling Rate

- **After every task commit:** Run the task's targeted `--filter` test class
- **After every plan wave:** Run `php artisan test` + sidecar `pytest`
- **Before `$gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| (filled at planning) | | | SEARCH-02 | T-10-01 | Availability never in sidecar payloads | feature | `php artisan test --filter AvailabilityServiceTest` | ⬜ W0 | ⬜ pending |
| (filled at planning) | | | SEARCH-02 | T-10-01 | Citation payload stays exactly 6 keys | feature | `php artisan test --filter ChatWidgetTest` | ✅ | ⬜ pending |
| (filled at planning) | | | SEARCH-06 | — | Mechanism returns rank-ordered papers, self-excluded | feature | `php artisan test --filter SimilarPapersServiceTest` | ⬜ W0 | ⬜ pending |
| (filled at planning) | | | SEARCH-06 | — | Fail-closed on sidecar down / empty | feature | `php artisan test --filter SimilarPapersServiceTest` | ⬜ W0 | ⬜ pending |
| (filled at planning) | | | SEARCH-02 | — | Sidecar rejects unknown fields with 422 | pytest | `pytest tests/test_api.py tests/test_chat_stream.py` | ⬜ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/AvailabilityServiceTest.php` — hydrator unit + capture tests (D-07 #1/#4)
- [ ] `tests/Feature/SimilarPapersServiceTest.php` — mechanism + fail-closed tests
- [ ] `tests/Feature/AcademicPaperIndexSimilarTest.php` — recommendations-mode Livewire tests (D-14..D-18)
- [ ] `ceit-ai-sidecar/tests/test_api.py` + `tests/test_chat_stream.py` — unknown-field 422 tests (D-07 #3)

*Existing infrastructure (factories, Http::fake patterns, ChatWidgetTest) covers the rest.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Live smoke: real sidecar + real corpus returns recommendations with availability | SEARCH-02/06 | Requires running sidecar + exported corpus | `php artisan ai:export-corpus`; start sidecar; open student search page; click Similar; verify cards + "Back to results" |
| "Checked just now" caption visual placement | SEARCH-02 | Visual check | Open search page, inspect card Copies cell |
| Mobile two-button action row layout | SEARCH-06 | Visual check | Viewport < 1280px; verify View Details + Similar row |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 60s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** {pending / approved 2026-08-14}
