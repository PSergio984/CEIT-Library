# Phase 5 Research: Workflow Optimization & UX Polish

## 1. Librarian Batch Root Cause Analysis
The test failure `librarian batch can be assigned to specific date` is caused by a race condition between `saveBatchAssignment()` and `updateBatchStatuses()`.

### Findings:
- **Redundant Logic:** `saveBatchAssignment()` manually sets the status to `active` if the date is today.
- **Boot Method Conflict:** The `Librarian` model has a `boot()` method that sets status to `expired` if `isExpired()` is true.
- **Role Sync:** The role synchronization (User role ID) happens in two different places (the component and the `updateBatchStatuses` method), leading to inconsistent state if transactions aren't perfectly ordered.

### Refactor Plan:
- **Centralize Status Logic:** Move all status and role-sync logic into a dedicated service class or a clean model method (`syncStatusAndRole()`).
- **Deterministic Transitions:** Use explicit status constants and ensure `updateBatchStatuses()` is called atomically.
- **Fix Test:** Ensure the test environment's "Today" matches the logic's expectation.

## 2. Skeleton Loaders (UX)
Mary UI/daisyUI does not provide a native "Skeleton Table" component, but we can build a high-performance reusable Blade component.

### Implementation:
- **Component:** `resources/views/components/table-skeleton.blade.php`.
- **Logic:** Use `wire:loading` to swap the real table body with a ghost version using Tailwind's `animate-pulse`.
- **Target Locations:** 
  - `AdminAcademicPaperIndex`
  - `AdminTransactions`
  - `StudentNotifications`

## 3. Prefetching Strategy
We will use `wire:navigate.hover` for primary navigation.

### Locations:
- Sidebar: Dashboard, Academic Papers, Rules & Regulations, Profile.
- Mobile Nav: Home, Papers, Alerts.

## 4. Admin Analytics (Loan Trends)
We will add a Chart.js or simple SVG-based trend widget to the Admin Dashboard.

### Metrics:
- X-Axis: Last 7 Days.
- Y-Axis: Number of borrow transactions.
- Data Source: `BorrowTransaction::where('created_at', '>=', now()->subDays(7))->count()`.

## Next Steps
- [ ] Refactor `Librarian` status logic to `app/Services/LibrarianStatusManager.php`.
- [ ] Create `table-skeleton` Blade component.
- [ ] Apply prefetching to `app.blade.php`.
- [ ] Build the Loan Trends widget for `AdminDashboard`.
