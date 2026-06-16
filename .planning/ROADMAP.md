# Roadmap: CEIT-Library Improvements

## Milestone: Bug Fixes & Security Improvements (v1.1)

### Phase 1: Modernization & Frontend Bug Fixes [COMPLETE]
**Goal:** Upgrade to Livewire v4, modernize dependencies, and resolve critical frontend interaction issues.

**Scope:**
- [x] [R1.1] Upgrade Livewire from v3 to v4. (Completed: 2026-05-18)
- [x] [R1.2] Modernize dependencies (Mary UI v2, Tailwind CSS v4, daisyUI v5). (Completed: 2026-05-18)
- [x] [R1.3] Fix Admin Table modal interaction bug. (Completed: 2026-05-18)
- [x] [R1.4] Fix QR Scanner mobile camera issue. (Completed: 2026-05-18)
- [x] [R1.5] Initial security audit of frontend components. (Completed: 2026-05-18)
- [x] [R1.6] Mobile PWA expansion & Hybrid Navigation. (Completed: 2026-05-18)
**Success Criteria:**
- Application successfully runs on Livewire v4.
- Modals on Admin tables can be opened and closed repeatedly without locking the UI.
- QR Scanner on mobile devices defaults to the back-facing camera.
- All existing tests pass.

### Phase 2: Security Hardening
**Goal:** Enhance application security by auditing middleware and validation rules.

### Phase 3: Stability & Performance [COMPLETE]
**Goal:** Address remaining failing tests and optimize database queries. Transform the entry point into a premium, high-conversion landing page.

**Requirements:** [REQ-01, REQ-02, REQ-03]

**Plans:** 7/7 plans executed
- [x] 03-01-PLAN.md — Fix Authorization & Middleware Tests
- [x] 03-02-PLAN.md — Fix Auth, Profile & Seeder Tests
- [x] 03-03-PLAN.md — Fix UI & Lazy Loading Tests
- [x] 03-04-PLAN.md — Fix Notifications, QR Tests & DB Optimization
- [x] 03-05-PLAN.md — Create Premium Liquid Glass Landing Page
- [x] 03-06-PLAN.md — Global Branding & SEO Update
- [x] 03-07-PLAN.md — Reskin Auth Pages

### Phase 4: PWA Hardening & Offline Resilience [COMPLETE]
**Goal:** Upgrade the PWA from basic manifest registration to a resilient, native-like experience.

**Scope:**
- [x] [R4.1] Implement Cache-First asset handling via `injectManifest`.
- [x] [R4.2] Integrate App Badging API for unread notifications.
- [x] [R4.3] Design and implement Branded "Install CEIT Lib" banner.
- [x] [R4.4] Add real-time Offline Detection and safety overlays for the QR Scanner.

### Phase 5: Workflow Optimization & UX Polish [COMPLETE]
**Goal:** Resolve librarian system bugs and enhance overall user experience through performance refinements.

**Scope:**
- [x] [R5.1] Centralize and refactor Librarian Batch logic into a deterministic Service.
- [x] [R5.2] Implement elegant Skeleton Loaders for all major tables.
- [x] [R5.3] Enable balanced Hover Prefetching for sidebar navigation.
- [x] [R5.4] Add Loan Trends and Top Borrowers widgets to the Admin Dashboard.

### Phase 6: Stabilization & Automation [COMPLETE]
**Goal:** Achieve 100% test coverage and automate critical background tasks.

**Scope:**
- [x] [R6.1] Register and schedule `transactions:check-overdue` command.
- [x] [R6.2] Enable and verify Welcome Email flow for new registrations.
- [x] [R6.3] Map and verify all 90 manual test cases to automated tests.
- [x] [R6.4] Final stabilization run on PHP 8.4 environment.

## Milestone: Architecture & Analytics (v1.2)

### Phase 1: Structural Integrity [COMPLETE]
**Goal:** Decouple "fat" Livewire components and consolidate model behavior.

**Scope:**
- [x] [R1.1] Extract `BorrowService` and `AttendanceService` to handle core logic and QR decryption.
- [x] [R1.2] Consolidate `BorrowTransaction` model events into a unified `booted` method.
- [x] [R1.3] Modernize inline validation in components using Livewire 3 `#[Validate]` attributes.

### Phase 2: Advanced Analytics & Reporting [COMPLETE]
**Goal:** Expand dashboard utility and optimize query performance.

**Scope:**
- [x] [R2.1] Add high-performance cached "Quick Stats" mini-cards to Admin tables.
- [x] [R2.2] Implement advanced PDF export functionality using `barryvdh/laravel-dompdf`.
- [x] [R2.3] Optimize repeated distinctive queries (e.g., paper types) using Cache.