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
| 11-01-T1/T2 | 01 | 1 | SEARCH-05 | T-11-01 | rich doc shape, no corpus tag break | unit | `php artisan test --filter ExportAiCorpusTest` | ✅ | ⬜ pending |
| 11-02-T1/T2 | 02 | 1 | SEARCH-05 | T-11-01 | author/adviser filters closed-schema posture | unit | `cd ceit-ai-sidecar; uv run pytest tests/test_filters.py tests/test_api.py -x` | ✅ | ⬜ pending |
| 11-02-T3 | 02 | 1 | SEARCH-05 | T-11-01 | eval quality gate on rebuilt index | integration | `cd ceit-ai-sidecar; uv run python -m app.eval --corpus catalog` | ✅ | ⬜ pending |
| 11-04-T2/T3 | 04 | 2 | SEARCH-05 | T-11-01 | paper tab mode + filter passthrough + back-compat | feature | `php artisan test --filter AcademicPaperIndexHybridTest` | ✅ | ⬜ pending |
| 11-03-T1 | 03 | 2 | CHAT-05 | T-11-02 | tool loop cap, fail-closed refusal, frame ordering | unit | `cd ceit-ai-sidecar; uv run pytest tests/test_agentic_loop.py -x` | ✅ | ⬜ pending |
| 11-05-T1/T2 | 05 | 3 | CHAT-05 | T-11-02 | activity + citations SSE frames, refusal bubble | feature | `php artisan test --filter 'AiServiceChatTest|ChatWidgetTest'` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `ceit-ai-sidecar\tests\conftest.py` — rich single-title corpus fixtures (wave-0 agreement with 11-01 exporter shape; both land in wave 1)
- [ ] `ceit-ai-sidecar\tests\fixtures\chat-stream-agentic.txt` — agentic SSE fixture (created in 11-03/11-05)

*Existing infrastructure covers all other phase requirements.*

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
