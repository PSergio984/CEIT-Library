---
phase: 8
slug: hybrid-search-foundation
status: active
nyquist_compliant: true
wave_0_complete: true
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
| 08-01-T1 | 08-01 | 1 | SEARCH-07 | T-03, R3 | No inventory keys; field caps; control-char strip; never log doc contents | PHPUnit | `php artisan test --filter=ExportAiCorpusTest` | app/Services/CorpusExporter.php | ⬜ pending |
| 08-01-T2 | 08-01 | 1 | SEARCH-07 | T-01 | Export writes to storage path only; exit codes 0/1 | CLI | `php artisan ai:export-corpus` | app/Console/Commands/ExportAiCorpus.php | ⬜ pending |
| 08-01-T3 | 08-01 | 1 | SEARCH-07 | T-01 | Asserts no copy_number/status/email in JSON | PHPUnit | `php artisan test --filter=ExportAiCorpusTest` | tests/Feature/ExportAiCorpusTest.php | ⬜ pending |
| 08-02-T1 | 08-02 | 1 | SEARCH-01/07 | T-03 | Token env-only; .env gitignored; loopback defaults | CLI | `uv lock` + git log init commit | ceit-ai-sidecar/pyproject.toml | ⬜ pending |
| 08-02-T2 | 08-02 | 1 | SEARCH-07 | T-04 | Loud validation (schema_version, generated_at, dup ids) | pytest | `uv run pytest -q -k ingest` | ceit-ai-sidecar/app/ingest.py | ⬜ pending |
| 08-02-T3 | 08-02 | 1 | SEARCH-01 | T-01/T-05 | Constant-time token compare; RRF k=60; code pin | pytest | `uv run pytest -q` | ceit-ai-sidecar/app/search.py | ⬜ pending |
| 08-02-T4 | 08-02 | 1 | SEARCH-01/07 | T-01/T-02 | 401 without token; 200 with; rebuild atomicity; RRF math pins | pytest | `uv run pytest -q; uvx ruff check .` | ceit-ai-sidecar/tests/* | ⬜ pending |
| 08-03-T1 | 08-03 | 2 | SEARCH-01 | T-03 | xlsx guide-only; ids from real export | CLI | `php artisan ai:export-corpus` | 08-golden-mapping.md | ⬜ pending |
| 08-03-T2 | 08-03 | 2 | SEARCH-01 | T-01/T-02 | Golden set in private sidecar repo; snapshot-stamped; id-based | JSON | `uv run python -c "json.load(...)"` | ceit-ai-sidecar/data/golden_dataset.json | ⬜ pending |
| 08-03-T3 (checkpoint) | 08-03 | 2 | SEARCH-01 | T-01 | User review of real-name golden data (D-19) | Manual | checkpoint resume "approved" | — | ⬜ pending |
| 08-03-T4 | 08-03 | 2 | SEARCH-01 | T-02 | Held-out split; gates P@5/R@5/F1 + negative pass rate | CLI | `uv run eval --limit 5 --json` | ceit-ai-sidecar/app/eval.py | ⬜ pending |
| 08-04-T1 | 08-04 | 1 | SEARCH-07 | T-01/T-02 | Token env-only; loopback default URL; token placeholder empty in .env.example | Config | `php artisan config:show services` | config/services.php | ⬜ pending |
| 08-04-T2 | 08-04 | 1 | SEARCH-01 | T-03/T-04 | Fresh Http client; typed exceptions; retry on /search only; no body logging | PHPUnit | `php artisan test --filter=AiServiceTest` | app/Services/AiService.php | ⬜ pending |
| 08-04-T3 | 08-04 | 1 | SEARCH-01/07 | T-01 | Fixtures carry contract_version, no token | PHPUnit | `php artisan test --filter=AiServiceTest` | tests/fixtures/ai-sidecar/*.json | ⬜ pending |
| 08-05-T1 | 08-05 | 2 | SEARCH-01 | T-04 | No HTTP in #[Computed]; availability stays local (D-04); try/catch fallback | PHPUnit | `php artisan test --filter=AcademicPaperIndexHybridTest` | app/Livewire/Pages/Student/AcademicPaperIndex.php | ⬜ pending |
| 08-05-T2 | 08-05 | 2 | SEARCH-01 | T-01 | Blade `{{ }}` escaping only; fallback notice string | View | `php artisan view:cache` | .../academic-paper-index.blade.php | ⬜ pending |
| 08-05-T3 | 08-05 | 2 | SEARCH-01 | T-02/T-03 | assertSeeInOrder; ConnectionException fallback; filters forwarded | PHPUnit | `php artisan test --filter=AcademicPaperIndexHybridTest` | tests/Feature/AcademicPaperIndexHybridTest.php | ⬜ pending |
| 08-06-T1 | 08-06 | 2 | SEARCH-07 | T-01 | Deleted → immediate job; pivot $touches; 7 observe registrations | PHPUnit | `php artisan test --filter=AiIndexObserverTest` | app/Observers/*.php | ⬜ pending |
| 08-06-T2 | 08-06 | 2 | SEARCH-07 | T-03 | Unique ids; $tries=3; fail visibly into failed_jobs | PHPUnit | `php artisan test --filter=AiIndexObserverTest` | app/Jobs/AiIndexRebuildJob.php | ⬜ pending |
| 08-06-T3 | 08-06 | 2 | SEARCH-07 | T-04 | Counts-only logging; 26h freshness; --repair dispatch; exit codes | PHPUnit | `php artisan test --filter=ReconcileAiIndexTest` | app/Console/Commands/ReconcileAiIndex.php | ⬜ pending |
| 08-06-T4 | 08-06 | 2 | SEARCH-07 | T-01/T-03 | Trigger matrix + reconcile + live round-trip (env-gated) | PHPUnit | `php artisan test --filter="AiIndexObserverTest|ReconcileAiIndexTest"` | tests/Feature/AiIndexObserverTest.php | ⬜ pending |

*Populated by the planner — each task maps to a PHPUnit test (export JSON shape, observer dispatch, AiService call, reconcile) or pytest (RRF fusion dominance, golden-set P/R/F1). Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] Laravel: `tests/Feature/Ai/` test dir — export shape + observer dispatch covered by `tests/Feature/ExportAiCorpusTest.php` (08-01-T3) + `tests/Feature/AiIndexObserverTest.php` (08-06-T4)
- [x] Sidecar repo scaffold: RRF fusion dominance tests — `ceit-ai-sidecar/tests/test_rrf.py` (08-02-T4)
- [x] Sidecar: `data/golden_dataset.json` — 25-case id-based set from 08-RESEARCH.md §6 (08-03-T2, user-reviewed)

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Papers-page search box returns hybrid results with filters | SEARCH-01 | End-to-end Laravel↔sidecar on real corpus | UAT script in 08-RESEARCH.md: type title fragment → correct paper; apply department filter → only that dept; Taglish paraphrase query → sensible results |
| `/health` reports index coverage after export+rebuild | SEARCH-07 | Requires live sidecar process | Start sidecar, run `ai:export-corpus`, hit `/index/rebuild`, check `/health` counts vs DB |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 150s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
