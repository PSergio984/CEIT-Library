# Phase 5 Context: Workflow Optimization & UX Polish

## Decisions Locked (Senior Discretion Applied)

### 1. Librarian Batch Reliability
- **Decision:** Deterministic Refactor (Option B).
- **Rationale:** The current "flaky" failure suggests a race condition or a dependency on non-deterministic order. I will refactor the assignment logic to ensure statuses are updated atomically and verified before the operation completes.

### 2. Loading States
- **Decision:** Full Skeleton Loaders (Option A).
- **Rationale:** Given the "Liquid Glass" aesthetic we've established, skeleton loaders provide a much more premium feel and eliminate layout shifts (CLS), which is critical for mobile UX.

### 3. Navigation Speed (Prefetching)
- **Decision:** Balanced Prefetching (Option B).
- **Rationale:** We will enable `wire:navigate.hover` for the Sidebar's primary nodes (Dashboard, Papers, Rules). This provides the "instant" feel where it matters most without flooding the server with requests for deeper, less-frequent administrative pages.

### 4. Admin Analytics Widget
- **Decision:** Loan Trends (Option A).
- **Rationale:** For a library system, the primary KPI is resource circulation. A daily/weekly trend graph provides the most immediate value for librarians to monitor system activity.

## Downstream Guidance
- **Researcher:** Identify the exact line in `LibrarianBatchTest` causing the mismatch. Audit Mary UI components for built-in skeleton support or create a reusable Blade ghost-row component.
- **Planner:** 
  1. Fix and harden the Librarian Batch status logic.
  2. Implement `x-mary-loading` with skeletons on all main tables.
  3. Apply `wire:navigate.hover` to key navigation items.
  4. Build the Loan Trends chart using existing dashboard patterns.
