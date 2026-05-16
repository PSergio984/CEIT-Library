# Research: Phase 1 - Modernization & Frontend Bug Fixes

## 1. Modernization Audit (Livewire v4 & Tailwind v4)

### Dependency Updates
- **Livewire**: ^4.0 (Stable release as of May 2026).
- **Mary UI**: ^2.0 (Required for Livewire v4 compatibility).
- **Tailwind CSS**: ^4.0 (CSS-first engine).
- **daisyUI**: ^5.0 (Matches Tailwind v4).

### Key Breaking Changes & Migration Paths
- **Livewire v4**: 
    - Configuration keys have been renamed/moved.
    - `wire:model` timing: `.blur` and `.change` sync to the client-side state without `.live` by default, but server-side sync might require `.live` for immediate reactivity in some contexts.
    - Native support for class-based components (SFCs possible).
- **Tailwind v4**:
    - `tailwind.config.js` is replaced by CSS-first configuration using `@theme` and `@plugin`.
    - Automated migration tool: `npx @tailwindcss/upgrade`.

## 2. Admin Modal Bug: Root Cause Analysis

### Reproduction Steps
1. Navigate to Admin > Academic Papers.
2. Click "QR Code" for an available copy.
3. Close the modal using the "X" or "Close" button.
4. Try to open another QR code or interact with the table.
5. Observed: UI becomes unresponsive or backdrop remains.

### Root Cause
File: `resources/views/livewire/pages/Admin/admin-academic-paper-index.blade.php`
Lines: 359-364
```javascript
x-init="$watch('showQrModal', value => { 
    if (value) { $refs.qrModal.showModal() } 
    else { $refs.qrModal.close() } 
})"
```
- **Issue**: The `x-init` watch on the native `<dialog>` element desyncs when Livewire performs a DOM diff. If Livewire re-renders the dialog's parent, the native state of the dialog is lost, but the Alpine state (`showQrModal`) remains.
- **Fix**: Move the modal management to a more stable Alpine component or use Livewire v4's native dialog handling which is designed to handle these re-renders gracefully.

## 3. QR Scanner: Camera Selection Research

### Library Capabilities
- `Html5Qrcode` provides `getCameras()` which returns an array of `CameraDevice` objects `{ id, label }`.
- Selection logic:
    - `Html5Qrcode.getCameras().then(cameras => { ... })`
    - Filter for "environment" or "back" in labels to set a smart default.

### UI Design (Smart Selection)
- **State**: `cameras` (array), `selectedCameraId` (string).
- **Flip Logic**: If `cameras.length === 2`, show a toggle icon that swaps the ID.
- **Dropdown Logic**: If `cameras.length > 2`, show a `Select` component with camera labels.

## 4. Security Audit Mapping

### Critical Public Methods (Authorization Check Required)
The following methods must be verified for `Gate::authorize()` or equivalent checks:

**AdminAcademicPaperIndex.php**
- `requestQr`, `performDelete`, `create`, `edit`, `saveAcademicPaper`, `performCopyDelete`.

**AdminAssignLibrarians.php**
- `createBatch`, `saveBatchAssignment`, `updateBatchStatuses`.

**AdminManageRoles.php**
- `assignRole` (already has a check, but audit for completeness).

**ActiveUsersTab.php**
- `recordViolation`, `declareForgotTimeout`, `confirmDeclareForgotTimeout`.

**AdminAdvisersDeans.php**
- `save`, `delete`.

**AdminRuleAndRegulationIndex.php**
- `save`, `update`, `deleteConfirmed`.

### Input Validation (Sanitization Audit)
- All `search` properties across Admin pages.
- `AcademicPaperForm` and `ViolationForm` input fields.

## Risks & Regressions
- **Upgrade Risk**: Unexpected UI shifts due to Tailwind v4's new CSS engine.
- **Modal Risk**: Ensuring backdrop cleanup on all browsers (iOS Safari often has issues with `<dialog>`).
- **QR Risk**: Permissions must be handled before enumeration on some mobile devices.
