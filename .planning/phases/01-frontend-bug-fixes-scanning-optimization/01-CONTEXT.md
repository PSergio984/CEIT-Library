# Phase 1 Context: Frontend Bug Fixes & Scanning Optimization

## Status
- **Phase**: 1
- **Focus**: UI Stability, QR Optimization, Security Audit
- **Decisions Locked**: 2026-05-16

## Implementation Decisions

### 1. Modernization (Upgrade)
- **Livewire**: Upgrade from v3 to **v4**. 
- **Ecosystem**: Modernize to **Mary UI v2**, **Tailwind CSS v4**, and **daisyUI v5**.
- **Approach**: Perform dependency updates first, followed by a compatibility audit of existing components. Ensure all components extend `Livewire\Component`.

### 2. Modals & UI Stability
- **Approach**: Stick with **Alpine.js** for modal state management (priority: performance). 
- **Note**: Re-evaluate if Livewire v4 native features or Mary UI v2's native `<dialog>` handling removes the need for custom Alpine logic.
- **Goal**: Fix the UI "locking" bug by ensuring native `<dialog>` cleanup and proper state synchronization.
- **Action**: Audit `AdminAcademicPaperIndex.php` and its modal components to ensure backdrop/scroll-lock is released on close.

### 3. QR Scanner Optimization
- **Approach**: Implement a **Smart Camera Selection UI**.
- **Logic**:
    - If 2 cameras detected: Use a **Flip** icon to toggle between them.
    - If > 2 cameras detected: Use a **Dropdown** list for explicit selection.
- **Goal**: Resolve "front-facing camera" issues on mobile devices by giving users manual control if defaults fail.

### 4. Security Audit (A & B)
- **Scope**: Comprehensive audit of all roles (Super Admin, Admin, Librarian, Student).
- **Priority A (Action Authorization)**: Ensure all `wire:click` and public methods in Livewire components have explicit `Gate` or `authorize()` checks.
- **Priority B (XSS/Input Validation)**: Audit all search inputs, filter parameters, and form submissions for proper sanitization and validation rules.

## Research Questions for Next Step
- Identify breaking changes in Livewire v4 affecting existing components.
- Audit Tailwind v4 migration requirements (removal of `tailwind.config.js`).
- Identify the exact line in `admin-academic-paper-index.blade.php` causing the native `<dialog>` state desync.
- Test `html5-qrcode` device enumeration reliability on various browser agents.
- Map all public Livewire methods in the `Admin` and `Librarian` pages for the authorization audit.

## Next Step
Initiate **01-RESEARCH.md** to verify reproduction of the modal bug and scan for authorization gaps.
