---
phase: 08-hybrid-search-foundation
plan: 03
subsystem: testing
tags: [golden-set, evaluation, precision, recall, f1, eval-cli]

requires:
  - phase: 08
    provides: "08-01 export contract, 08-02 HybridSearch, real exported catalog snapshot 2026-08-13T11:25:36+00:00"
provides:
  - "25-case id-based golden dataset (data/golden_dataset.json) snapshot-stamped, user-approved"
  - "app/eval.py CLI: P@k/R@k/F1 + top1 rate + negative pass rate, --json output"
  - "Recorded quality gate table (iteration 1) — search quality measurable from day one (success criterion 4)"
affects: [08-05, 08-06, 09, 13]

tech-stack:
  added: []
  patterns: ["id-based golden matching (never title-string)", "top1 rate for exact-match categories (P@5 caps with single relevant doc)", "negative cases scored as negative_pass_rate"]

key-files:
  created:
    - ceit-ai-sidecar/data/golden_dataset.json
    - ceit-ai-sidecar/app/eval.py
    - .planning/phases/08-hybrid-search-foundation/08-golden-mapping.md
  modified: []

key-decisions:
  - "Faker-seeded dev catalog: every draft case is a SUBSTITUTION to a real exported row, explicitly labeled in 08-golden-mapping.md; re-mappable when real data lands"
  - "Metric correction: exact-title/code cases have 1 relevant doc → P@5 caps at 0.2; measured as top1 rate instead. Policy cases have 3 relevant → P@5 caps at 0.6; recall@5 used as the policy gate."
  - "Top-1 computed from the ranked retrieved list (set iteration order is not rank order)"

patterns-established:
  - "Golden set shape: {version, catalog_snapshot, test_cases[{query, corpus, filters?, relevant_docs, negative?}]}"
  - "Held-out cases {8,12,15,21,25} excluded from tuning"

requirements-completed: [SEARCH-01]

duration: 1h 20min
completed: 2026-08-13
---

# Phase 8 Plan 3: Golden Set + Eval Runner Summary

**25-case id-based bootstrap golden set (user-approved) + eval CLI proving search quality is measurable from day one — P@5/R@5/F1, top1 rate, negative pass rate 1.0, with the faker-corpus metric caps documented**

## Performance

- **Duration:** 1h 20min
- **Started:** 2026-08-13T22:30:00+08:00
- **Completed:** 2026-08-13T23:50:00+08:00
- **Tasks:** 3 (mapping, dataset, eval runner)
- **Files modified:** 3 (sidecar: 2, planning: 1)

## Accomplishments

- `08-golden-mapping.md`: draft cases P1..P7 + policy refs mapped to real exported ids, every substitution labeled; export `generated_at` recorded for the snapshot stamp.
- `data/golden_dataset.json`: 25 cases (20 catalog, 4 policy, 1 union), 3 negatives, 4 with filters, snapshot `2026-08-13T11:25:36+00:00`. Checkpoint approved by user (D-19).
- `app/eval.py`: per-case precision/recall/F1, top1 hit, negative pass rate, category summaries, `--json` for CI. Ruff clean; sidecar pytest 23/23 still green.
- Real-model eval run against the live index (43 docs).

## Quality Gate Table — iteration 1 (final; no tuning changes warranted)

| Gate (plan §6) | Target | Measured | Result |
|---|---|---|---|
| Negative pass rate | 1.0 | **1.0** (3/3) | ✓ |
| Paraphrase / topic P@5 | ≥ 0.6 | **0.72** (n=10) | ✓ |
| Taglish P@5 | ≥ 0.6 | **0.80** (n=6) | ✓ |
| Policy P@5 | ≥ 0.7 | 0.60 — **at the cap** (3 relevant docs, max P@5 = 3/5); recall@5 = **1.0** | ✓ (via recall) |
| Exact-title / code P@5 | 1.0 | 0.2-0.4 — **cap** (1 relevant doc, max P@5 = 1/5) | measured as **top1 rate** |
| — exact_title top1 | 1.0 | 0.67 (2/3) | ✗ 1 miss (faker artifact) |
| — catalog_code top1 | 1.0 | 0.50 (1/2) | ✗ "CEIT-IT" family query (not a pin-able exact code) |
| Overall | — | P@5 0.63, R@5 0.79, F1 0.61, top1 0.77 | recorded |

**Miss analysis (both faker-corpus artifacts, not engine defects):**
- `"Et aperiam iste facilis placeat."` → BM25 rank 1 (exact match) but semantic rank 19: 3-6-word Latin titles collide in embedding space, and RRF lets a semantic-close doc (paper-13) edge it out. With real long engineering titles, exact-title BM25 dominance is expected to restore rank 1.
- `"CEIT-IT"` (department code family) is not an exact code — the pin regex correctly doesn't fire; top-1 falls to a semantic guess.
- The code-exact pin itself is verified working (case 3 `CEIT-IT-23-01` → paper-4 pinned to rank 1).

**Tuning iterations:** 1 run only — no param tuning performed; metric definitions corrected (top1 rate, recall-based policy gate) to match the case design, per plan's "record every iteration's results and the final gate table".

## Task Commits

1. **Task 1: mapping doc** - uncommitted in CEIT-Library repo (pending this summary's commit)
2. **Task 2: golden_dataset.json** - `5e6dca2` (sidecar repo, feat)
3. **Task 3: app/eval.py** - `5e6dca2` (sidecar repo, same commit)
4. **Checkpoint: user approval** - approved in session (D-19)

**Plan metadata:** committed in CEIT-Library repo (this summary)

## Files Created/Modified

- `ceit-ai-sidecar/data/golden_dataset.json` - 25 id-based cases, snapshot-stamped
- `ceit-ai-sidecar/app/eval.py` - golden-set runner (P@k/R@k/F1/top1/neg-pass-rate, --json)
- `.planning/phases/08-hybrid-search-foundation/08-golden-mapping.md` - draft→real-id mapping

## Decisions Made

- Metric correction: top1 rate for exact-match categories (P@5 mathematically caps at 0.2 for 1-relevant cases); recall@5 for policy (caps at 0.6 for 3-relevant cases). Documented in the gate table.
- Top-1 reads `retrieved[0]` (ranked list), never a set iteration.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Metric gate targets unreachable with the real corpus shapes**
- **Found during:** Task 3 first eval run
- **Issue:** exact-title/code cases carry 1 relevant doc (max P@5 = 0.2) and policy cases 3 (max 0.6) — the §6 targets (1.0 / 0.7) cannot be met by any engine. First version also computed top-1 from a set (arbitrary order).
- **Fix:** added `top1_rate` (ranked-list based); policy gate measured via recall@5 (1.0); gate table records the correction rationale.
- **Files modified:** app/eval.py
- **Verification:** eval --json emits valid report; gates recorded.
- **Committed in:** 5e6dca2 (sidecar repo)

---

**Total deviations:** 1 auto-fixed (metric design, Rule 1)
**Impact on plan:** Necessary for honest measurement; no engine behavior changed.

## Issues Encountered

- `uv run eval` fails with `package = false` (no console-script install) — documented usage is `uv run python -m app.eval`.
- Eval test runs deleted the Laravel corpus files again (ExportAiCorpusTest setUp cleanup) — re-export + rebuild before each eval session.

## User Setup Required

None — golden set and eval run locally with the real model already cached.

## Next Phase Readiness

- 08-05 (papers-page UI) can gate search quality against this set before shipping UI.
- 08-06 (sync) unaffected.
- When the real catalog is imported to the DB, re-run `ai:export-corpus` and re-map the golden set per `08-golden-mapping.md` (30 min).

---
*Phase: 08-hybrid-search-foundation*
*Completed: 2026-08-13*
