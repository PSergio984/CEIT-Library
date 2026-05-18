# Phase 1.5 Context: Initial Security Audit of Frontend Components

## Status
- **Phase**: 1.5
- **Focus**: Security Hardening (Authorization & Validation)
- **Decisions Locked**: 2026-05-18

## Implementation Decisions

### 1. Authorization Depth (Defense-in-Depth)
- **Decision**: Implement **redundant authorization** for all sensitive or destructive public methods in Livewire components.
- **Rationale**: While `mount()` provides initial access control, component-level method authorization (e.g., `$this->authorize('delete', $user)`) protects against unauthorized execution via state manipulation or direct method calls if the component state is somehow bypassed.
- **Priority**: High-risk actions (delete, update, assign roles).

### 2. Standardized Input Validation
- **Decision**: Implement **strict validation rules** for all public properties used for searching, filtering, or sorting.
- **Rules**:
    - Search inputs: `string`, `max:100`, `nullable`.
    - Filter inputs (enums/ids): `string` or `integer`, `nullable`, and where possible, validation against allowed values/database.
- **Implementation**: Use Livewire's `#[Validate]` attributes or explicit `validate()` calls in `updated[Property]` hooks.

### 3. Expanded Audit Scope
- **Decision**: The audit will cover **Admin, Librarian, and Student** namespaces.
- **Order of Execution**:
    1. **Admin/Librarian**: Critical infrastructure and management components.
    2. **Student**: Personal data access and interactive components (QR, Transactions).

### 4. Remediation Pattern (Refactoring)
- **Decision**: Use a **clean, idiomatic approach** for remediation.
- **Approach**: 
    - Ensure all components use the `AuthorizesRequests` trait.
    - Standardize authorization error handling (graceful toast notifications instead of raw 403 pages where appropriate).
    - Consolidate validation logic into Form Requests or standardized rules where shared across components.

## Target Components (High Priority)

### Admin
- `AdminManageRoles.php` (Actions: `assignRole`)
- `AdminUserList.php` (Actions: `saveChanges`, `deleteUser`)
- `AdminAssignLibrarians.php` (Actions: `createBatch`, `saveBatchAssignment`)
- `AdminAcademicPaperIndex.php` (Search/Filters)

### Librarian
- `LibrarianActionScanner.php` (Actions: `scan`)

### Student
- `AttendanceQr.php`
- `Transaction.php`

## Next Step
Initiate **1.5-RESEARCH.md** to map exact method-to-gate pairings and identify missing validation rules across the target components.
