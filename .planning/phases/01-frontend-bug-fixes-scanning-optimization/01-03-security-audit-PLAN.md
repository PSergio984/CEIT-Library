---
must_haves:
  truths:
    - Administrative actions are protected by explicit authorization gates.
    - Search and filter inputs are sanitized and safely rendered.
  artifacts:
    - updated: app/Livewire/Pages/Admin/AdminAcademicPaperIndex.php
    - updated: app/Livewire/Pages/Admin/ActiveUsersTab.php
  key_links:
    - from: app/Providers/AppServiceProvider.php
      to: app/Livewire/Pages/Admin/
      via: Gate::authorize()
requirements:
  - R1.5
depends_on: ["01-02-frontend-fixes-PLAN.md"]
---

# Plan: Phase 1.3 - Security Authorization & Input Audit

Harden administrative actions and audit user inputs for sanitization.

<task id="auth_hardening" requirement="R1.5">
  <files>
    <file>app/Livewire/Pages/Admin/AdminAcademicPaperIndex.php</file>
    <file>app/Livewire/Pages/Admin/ActiveUsersTab.php</file>
  </files>
  <action>
    Add explicit `Gate::authorize()` or `$this->authorize()` calls to all public methods identified in research (e.g. `performDelete`, `recordViolation`). Ensure they match the permissions defined in `AppServiceProvider`.
  </action>
  <verify>
    <automated>php artisan test --filter=RoleBasedAccessControlTest && vendor/bin/pint --dirty</automated>
  </verify>
  <done>
    Administrative actions are explicitly authorized.
  </done>
</task>

<task id="input_sanitization" requirement="R1.5">
  <files>
    <file>app/Livewire/Pages/Admin/AdminAcademicPaperIndex.php</file>
  </files>
  <action>
    Audit search and filter properties. Ensure they are correctly handled in Eloquent queries and that any user-provided string used in the UI is safely rendered.
  </action>
  <verify>
    <automated>php artisan test --filter=FormValidationTest && vendor/bin/pint --dirty</automated>
  </verify>
  <done>
    All user inputs are validated and sanitized.
  </done>
</task>
