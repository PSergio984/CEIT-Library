# Plan 05-01: UX Polish & Librarian Hardening

Refactor the Librarian Batch logic and implement high-performance UX components (Skeletons & Prefetching).

## Goal
Eliminate "flaky" logic in the librarian system and provide a near-instant, premium feel to the UI.

## Proposed Changes

### 1. Librarian System Refactor
- Create `App\Services\LibrarianStatusService` to handle status transitions and role synchronization.
- Remove redundant status logic from `AdminAssignLibrarians.php`.
- Ensure role syncing is atomic and happens in a single transaction.

### 2. Premium UX Components
- Create `resources/views/components/table-skeleton.blade.php`.
- Integrate the skeleton into `AdminAcademicPaperIndex`, `AdminTransactions`, and `StudentNotifications`.
- Enable `wire:navigate.hover` for primary sidebar and bottom-nav links.

### 3. Admin Analytics
- Implement a "Loan Trends" Chart component in the Admin Dashboard.
- Show borrowing volume for the last 7 days.

## Verification Plan

### Automated Tests
- `php artisan test tests/Feature/LibrarianBatchTest.php` (Must pass 100%).
- `php artisan test tests/Feature/NavigationTest.php` (Verify no regressions).

### Manual Verification
1. **Librarian Assignment:** Assign a batch to "Today" and verify students immediately receive the librarian role.
2. **Skeleton Check:** Open the Academic Papers table and verify the pulse animation appears briefly during load.
3. **Speed Test:** Hover over menu items and verify requests trigger in the network tab (prefetching).
4. **Dashboard:** Verify the trend chart displays correct counts.
