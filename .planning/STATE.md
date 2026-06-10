---
gsd_state_version: 1.0
milestone: v1.1
milestone_name: Bug Fixes & Security Improvements
status: active
last_updated: "2026-06-10T15:48:48.933Z"
progress:
  total_phases: 3
  completed_phases: 0
  total_plans: 15
  completed_plans: 3
  percent: 0
---

## Phase 1: Frontend Bug Fixes, Scanning, and Optimization (COMPLETE)

- Core stack upgraded to Livewire v4, Mary UI v2, Tailwind CSS v4, and daisyUI v5.
- Fixed Admin Table modal locking bug using native `<dialog>` and `x-teleport`.
- QR Scanner optimized with smart camera selection and default back-camera prioritization.
- Security Hardening: Redundant authorization and strict validation implemented.
- Mobile Expansion: PWA manifest/service-worker and Hybrid Bottom Navigation implemented.

## Phase 2: Security Hardening & Quality (COMPLETE)

- **SonarQube Compliance**: Resolved line length violations (>120 chars) in `_ide_helper.php`, `config/mail.php`, and factories. Applied PSR-12 brace formatting to `_ide_helper.php`.
- **UI Heuristics**: Implemented `x-skeleton` component and lazy loading for Academic Paper index. Added `wire:navigate.hover` prefetching to sidebar.
- **Security Hardening**: Fixed `CheckCreditScore` middleware regression; hardened Service Worker with origin verification.
- **Tiered Rate Limiting**: Configured 10x higher limits for Admins/Librarians on QR and search.
- **Infrastructure**: Modernized `Docker/start.sh` and `00-laravel-deploy.sh` to use `[[` conditionals.
