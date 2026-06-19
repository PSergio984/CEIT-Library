# Phase 5: Workflow Optimization & UX Polish

## Status: DISCUSSING

## Goal
Address pre-existing logic bugs in the librarian system and enhance the overall user experience through performance optimizations and visual refinements.

## Gray Areas to Resolve

### 1. Librarian Batch Reliability
- **Problem:** The test `librarian batch can be assigned to specific date` is failing sporadically.
- **Decision Needed:** Should we refactor the assignment logic to use a more deterministic scheduling algorithm, or simply fix the status transition bug in the existing controller?

### 2. Skeleton Loaders vs. Spinners
- **Problem:** Tables currently use standard loading spinners or no loading state at all, causing layout shifts.
- **Option A:** Full Skeleton Loaders (Ghost rows matching table structure).
- **Option B:** Modern Progress Bars (Thin line at the top of the table).

### 3. Prefetching Intensity
- **Goal:** Use `wire:navigate.hover` to speed up navigation.
- **Decision Needed:** Should we enable hover-prefetching for *all* sidebar links, or only for high-traffic pages (Dashboard, Academic Papers) to save server resources?

### 4. Admin Analytics Scope
- **Goal:** "Library at a Glance" widget.
- **Scope:** Which metric is most critical for the dashboard?
  - A) Loan Trends (Daily/Weekly graph).
  - B) Attendance Heatmap.
  - C) Top Borrowed Papers (List).

## Next Steps
- [x] Finalize decisions in `05-CONTEXT.md`.
- [x] Investigate the `LibrarianBatchTest` failure root cause.
- [x] Draft execution plans.
- [x] Refactor Librarian Status logic (Plan 05-01).
- [x] Implement Skeleton Loaders and Prefetching.
- [x] Build Admin Analytics Widget.
