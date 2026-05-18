---
must_haves:
  truths:
    - All identified public mutation methods in Admin/Librarian/Student components have explicit Gate authorization.
    - All search and filter properties have strict validation rules.
    - Authorization failures provide graceful Toast feedback.
  artifacts:
    - created: tests/Feature/Security/FrontendSecurityAuditTest.php
    - updated: app/Livewire/Pages/Admin/*.php
    - updated: app/Livewire/Pages/Student/*.php
  key_links:
    - from: AdminManageRoles::assignRole
      to: Gate::manage-user-roles
    - from: AdminUserList::saveChanges
      to: Gate::manage-students
    - from: AdminAssignLibrarians::saveBatchAssignment
      to: Gate::manage-librarian-batches
    - from: QrScanner::handleScan
      to: Gate::librarian-or-admin-access
requirements:
  - R1.5
depends_on: []
---

# Plan: Phase 1.5 - Security Audit of Frontend Components

Implement defense-in-depth authorization and standardized input validation across high-priority Livewire components using a TDD approach.

<task id="security_verification_suite" requirement="R1.5">
  <files>
    <file>tests/Feature/Security/FrontendSecurityAuditTest.php</file>
  </files>
  <action>
    Create a comprehensive security verification suite following TDD.
    The suite should test:
    - Unauthorized access to mutation methods (expecting Toast/403 behavior).
    - Validation failures for long search strings (>100 chars).
    - Authorized access happy paths for all target components.
    Initial run of this task should result in failing tests.
  </action>
  <verify>
    <automated>php artisan test tests/Feature/Security/FrontendSecurityAuditTest.php</automated>
  </verify>
  <done>
    Security verification suite created and failing as expected.
  </done>
</task>

<task id="admin_librarian_authorization" requirement="R1.5">
  <files>
    <file>app/Livewire/Pages/Admin/AdminManageRoles.php</file>
    <file>app/Livewire/Pages/Admin/AdminUserList.php</file>
    <file>app/Livewire/Pages/Admin/AdminAssignLibrarians.php</file>
    <file>app/Livewire/QrScanner.php</file>
  </files>
  <action>
    Add redundant `$this->authorize()` or `Gate::authorize()` checks within mutation methods:
    - AdminManageRoles: `assignRole`
    - AdminUserList: `saveChanges`, `deleteUser`
    - AdminAssignLibrarians: `createBatch`, `saveBatchAssignment`
    - QrScanner: `handleScan`
    Implement try-catch blocks to catch `AuthorizationException` and dispatch Mary UI Toasts.
  </action>
  <verify>
    <automated>php artisan test tests/Feature/Security/FrontendSecurityAuditTest.php --filter=Authorization</automated>
  </verify>
  <done>
    Redundant authorization checks implemented and passing security tests.
  </done>
</task>

<task id="validation_hardening" requirement="R1.5">
  <files>
    <file>app/Livewire/Pages/Admin/AdminUserList.php</file>
    <file>app/Livewire/Pages/Admin/AdminManageRoles.php</file>
    <file>app/Livewire/Pages/Admin/AdminAssignLibrarians.php</file>
    <file>app/Livewire/Pages/Admin/AdminAcademicPaperIndex.php</file>
    <file>app/Livewire/Pages/Admin/ActiveUsersTab.php</file>
    <file>app/Livewire/Pages/Student/AcademicPaperIndex.php</file>
  </files>
  <action>
    Apply `#[Validate('string|max:100|nullable')]` to all public search and filter properties:
    - AdminUserList: `$search`, `$statusFilter`, `$creditScoreFilter`, `$roleFilter`
    - AdminManageRoles: `$search`, `$filterRole`
    - AdminAssignLibrarians: `$search`, `$batchSearch`, `$filterStatus`
    - AdminAcademicPaperIndex: `$search`, `$statusFilter`, `$yearFilter`
    - ActiveUsersTab: `$searchActiveUsers`
    - Student/AcademicPaperIndex: `$search`
  </action>
  <verify>
    <automated>php artisan test tests/Feature/Security/FrontendSecurityAuditTest.php --filter=Validation</automated>
  </verify>
  <done>
    All search and filter properties have strict validation attributes and passing tests.
  </done>
</task>

<task id="student_security_audit" requirement="R1.5">
  <files>
    <file>app/Livewire/Pages/Student/Transaction.php</file>
    <file>app/Livewire/Pages/Student/AttendanceQr.php</file>
  </files>
  <action>
    Audit and harden Student-facing components:
    - Transaction: Add ownership check `Gate::authorize('update', $transaction)` in `extendTransaction`.
    - AttendanceQr: Ensure component state only allows viewing the auth user's QR.
  </action>
  <verify>
    <automated>php artisan test tests/Feature/Security/FrontendSecurityAuditTest.php --filter=Student</automated>
  </verify>
  <done>
    Student namespace components audited and hardened.
  </done>
</task>
