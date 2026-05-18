# Phase 1: Frontend Bug Fixes, Scanning, and Optimization - Research

**Researched:** 2026-05-18
**Domain:** Frontend Security & Optimization (Laravel Livewire)
**Confidence:** MEDIUM

## Summary

This research focuses on the Phase 1.5 Security Audit of Livewire components, specifically targeting Admin, Librarian, and Student namespaces. The goal is to implement redundant authorization (defense-in-depth) and standardized input validation for search and filter properties.

**Primary recommendation:** Implement explicit `$this->authorize()` or `Gate::authorize()` calls in all public mutation methods and apply `#[Validate]` attributes to search/filter properties.

## 1. Modernization Audit (Livewire v4 & Tailwind v4)
*(... existing research on Livewire v4 and Tailwind v4 ...)*

## 2. Admin Modal Bug: Root Cause Analysis
*(... existing research on Admin Modal Bug ...)*

## 3. QR Scanner: Camera Selection Research
*(... existing research on QR Scanner ...)*

## 4. Security Audit Research (Phase 1.5)

### Authorization Mapping (Redundant Checks)
While `mount()` provides initial access control, the following methods require redundant internal authorization checks to ensure defense-in-depth.

| Component | Public Method | Required Gate | Status |
|-----------|---------------|---------------|--------|
| `AdminManageRoles` | `assignRole` | `manage-user-roles` | Missing |
| `AdminUserList` | `saveChanges` | `manage-students` | Missing |
| `AdminUserList` | `deleteUser` | `manage-students` | Missing |
| `AdminAssignLibrarians` | `createBatch` | `manage-librarian-batches` | Missing |
| `AdminAssignLibrarians` | `saveBatchAssignment` | `manage-librarian-batches` | Missing |
| `AdminAcademicPaperIndex` | `performDelete` | `manage-academic-papers` | SECURE |
| `AdminAcademicPaperIndex` | `saveAcademicPaper` | `manage-academic-papers` | SECURE |
| `LibrarianActionScanner` | `scan` | `librarian-only` | Missing |
| `QrScanner` | `handleScan` | `librarian-or-admin-access` | Missing |
| `Transaction` (Student) | `extendTransaction` | (User ownership) | Missing |

### Validation Gaps (Search/Filters)
Most components use public properties for searching/filtering without any `#[Validate]` attributes. We recommend applying `#[Validate('string|max:100|nullable')]` to these.

| Component | Property | Current Validation |
|-----------|----------|--------------------|
| `AdminUserList` | `$search`, `$statusFilter`, `$creditScoreFilter`, `$roleFilter` | None |
| `AdminManageRoles` | `$search`, `$filterRole` | None |
| `AdminAssignLibrarians`| `$search`, `$batchSearch`, `$filterStatus` | None |
| `AdminAcademicPaperIndex` | `$search`, `$statusFilter`, `$yearFilter` | None |
| `AcademicPaperIndex` (Student) | `$search` | None |
| `ActiveUsersTab` | `$searchActiveUsers` | None |

### UX for Authorization Failures & Toast Compatibility
- **Findings**: `Mary\Traits\Toast` is used in most Admin components.
- **Strategy**: Instead of allowing `AuthorizesRequests` to throw a 403, wrap sensitive logic in `Gate::allows()` checks and use `$this->error('Unauthorized action.')` (Mary UI) or `$this->toast()` to provide a better UX.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Route-level middleware is insufficient for defense-in-depth | 4.1 | Low - Redundant auth is a standard security practice. |
| A2 | Missing `#[Validate]` means no validation is occurring | 4.2 | Medium - Components might use `updated*` hooks for validation, but attributes are preferred for consistency. |

## Next Steps
- Create `01-05-security-audit-PLAN.md` based on these findings.
- Implement redundant authorization in identified methods.
- Apply validation attributes to search/filter properties.
- Verify with unit tests.
