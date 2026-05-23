# Phase 2: Security Hardening - Context

**Gathered:** 2026-05-23
**Status:** COMPLETE

<domain>
## Phase Boundary

Phase 2 focuses on enhancing application security, improving UI heuristics, and enforcing strict code quality standards (SonarQube compliance). It covers middleware, route authorization, QR security, infrastructure scripts, and PWA service worker hardening.

</domain>

<decisions>
## Implementation Decisions

### Middleware & Route Protection
- **D-01:** Redirect unauthorized users back to the student dashboard (if authenticated) or login page (if guest) with a toast notification rather than showing a raw 403 Access Denied page.
- **D-02:** Enforce `Gate` or `authorize()` checks both at the route level and inside the component-level logic (e.g. `mount()`) for defense-in-depth.
- **D-03:** Implement/Hardened `CheckAccountStatus` middleware to globally block users whose `account_status` is not `'active'`.
- **D-04:** **Tiered Rate Limiting**:
    - **Admins/Librarians**: 300/min (QR Scanning), 500/min (Search), 200/min (Transactions).
    - **Students**: 30/min (QR Scanning), 60/min (Search), 20/min (Transactions).
    - **Login**: 5/min (Global).

### Input Validation & Code Quality (SonarQube)
- **D-05:** Use Livewire Form Objects (`Livewire\Form`) for CRUD, mandate custom rules `NoHtmlTags` and `SafeText`.
- **D-06:** **SonarQube Compliance (Line Length)**: Fix all lines > 120 characters across the codebase, specifically targeting:
    - `_ide_helper.php` (automated transformation).
    - `config/mail.php`.
    - `database/factories/*` (AcademicPaper, Violation, etc.).
- **D-07:** **SonarQube Compliance (Formatting)**: Format `_ide_helper.php` to move open curly braces to the beginning of the next line and remove trailing whitespaces to improve readability and satisfy quality gates.

### UI Heuristics & Performance
- **D-08:** **Loaders & Skeletons**: Implement heuristic UI feedback (loading states, skeletons, or progress bars) for all long-running Livewire actions (e.g., searches, bulk processing).
- **D-09:** **Sidebar Prefetching**: Investigate and implement prefetching for sidebar links (using Mary UI/Livewire prefetching patterns) to improve perceived performance during navigation.

### Infrastructure & PWA Hardening
- **D-10:** **Shell Best Practices**: Update `Docker/start.sh` and `scripts/00-laravel-deploy.sh` to use `[[` instead of `[` for conditional tests.
- **D-11:** **Service Worker (sw.js)**: Hardened `sw.js` for security:
    - Verify message origins in the `message` listener.
    - Ensure URL handling in `notificationclick` is origin-safe (avoid open redirects).
- **D-12:** **Repository Cleanliness**: Add `graphify-out/cache` to `.gitignore` to keep commits lean.

### the agent's Discretion
- **Bug Fix Prioritization**: Fix the `CheckCreditScore` middleware to correctly block access (abort 403) for students with score < 1.

</decisions>

<canonical_refs>
## Canonical References

- [routes/web.php](file:///c:/Users/admin/Herd/CEIT-Library/routes/web.php)
- [bootstrap/app.php](file:///c:/Users/admin/Herd/CEIT-Library/bootstrap/app.php)
- [resources/views/components/layouts/app.blade.php](file:///c:/Users/admin/Herd/CEIT-Library/resources/views/components/layouts/app.blade.php) — Sidebar and Layout.
- [Docker/start.sh](file:///c:/Users/admin/Herd/CEIT-Library/Docker/start.sh)
- [public/sw.js](file:///c:/Users/admin/Herd/CEIT-Library/public/sw.js)
- [app/Providers/AppServiceProvider.php](file:///c:/Users/admin/Herd/CEIT-Library/app/Providers/AppServiceProvider.php) — Rate limits.
- [_ide_helper.php](file:///c:/Users/admin/Herd/CEIT-Library/_ide_helper.php) — Quality target.

</canonical_refs>

---

*Phase: 2-Security Hardening*
*Context gathered: 2026-05-23*
