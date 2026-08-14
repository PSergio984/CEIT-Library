---
phase: 10
status: clean
depth: standard
files_reviewed: 12
critical: 0
warning: 0
info: 6
total: 6
---

# Phase 10 — Code Review Report

Two-axis review (Standards / Spec) of `git diff 5f6bbcdf...HEAD` (Laravel) + `git diff ca78486...HEAD` (sidecar), fixed point = pre-execution HEAD `5f6bbcdf` / sidecar `ca78486`.

## Standards

No repo coding-standard file exists (AGENTS.md covers tracker/triage conventions only); Pint/ruff pass. Zero documented-standard violations. Judgement-call smells (all Info — none blocking):

| # | Smell | Location | Detail |
|---|-------|----------|--------|
| S-1 | Data Clump | `AcademicPaperIndex.php:378-433` | Eleven-field `recommendationsSnapshot` captured + manually restored; a `QueryState` bundle would keep capture/restore in lockstep |
| S-2 | Duplicated Code | `academic-paper-index.blade.php` | Similar-button trio pasted 5× (135-141, 209-215, 297-303, 376-382, 489-495, 579-585); extractable to a partial |
| S-3 | Duplicated Code | `AvailabilityService.php:37`, `SimilarPapersService.php:44`, `ChatWidget.php` | `'Available'` literal and `str_replace('paper-', ...)` re-implemented; shared constants |
| S-4 | Primitive Obsession | `AvailabilityService` + both Computed maps | `{available,total,checked_at}` docblocked array shape ×3; value object defensible at this size |
| S-5 | Shotgun Surgery | `AcademicPaperIndex.php:85,228-287` | `exitRecommendationsMode()` threaded through 9 hooks; Livewire idiom makes centralising awkward |
| S-6 | Duplicated guard | sidecar `app/main.py` | `_reject_unknown` guard duplicated across `/search` and `/chat/stream`; decorator would unify |

## Spec

Spec: ADRs 0010–0012 + 10-CONTEXT.md D-01..D-18 (SEARCH-02, SEARCH-06).

- **W-1 (partial)**: Zero-inventory citations render no chip cue — `forPapers()` omits papers with no Inventory rows, so no "0/N" suffix for zero-inventory cited papers (D-04). Cards have the `?? 0` fallback; chips don't.
- **W-2 (partial)**: First-entry loading overlay never appears — overlay lives inside the `recommendedFor` block, absent on first click (D-16).
- **W-3 (wrong-looking)**: Status badges computed outside the single source of truth — `SimilarPapersService.php:34-39,48` runs its own `withCount` + `$paper->status` stamp; rec cards render that instead of the hydrated shape (D-02/D-18).

All other D-01..D-18 contract points verified in the implementation. No scope creep of consequence.

## Verdict

No blockers. W-1..W-3 are candidates for a review-fix round (Phase 9 pattern: two-axis review → fix round → clean re-review); S-1/S-2 are optional refactors. Next: `gsd-code-review 10 --fix` or manual fix round, then re-verify.
