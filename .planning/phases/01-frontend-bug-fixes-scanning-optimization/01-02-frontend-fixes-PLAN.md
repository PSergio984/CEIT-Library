---
must_haves:
  truths:
    - Admin modals can be opened and closed repeatedly without locking the UI.
    - QR scanner correctly identifies and allows toggling between available cameras.
  artifacts:
    - updated: resources/views/livewire/pages/Admin/admin-academic-paper-index.blade.php
    - updated: resources/views/livewire/qr-scanner.blade.php
  key_links:
    - from: app/Livewire/QrScanner.php
      to: resources/views/livewire/qr-scanner.blade.php
      via: Livewire binding
requirements:
  - R1.3
  - R1.4
depends_on: ["01-01-modernization-PLAN.md"]
---

# Plan: Phase 1.2 - Frontend Bug Fixes & QR Optimization

Resolve modal interaction issues and implement smart camera selection for the QR scanner.

<task id="fix_modal_sync" requirement="R1.3">
  <files>
    <file>resources/views/livewire/pages/Admin/admin-academic-paper-index.blade.php</file>
  </files>
  <action>
    Refactor the modal x-init logic. Instead of watching `showQrModal` on the dialog itself, move the dialog reference to a stable Alpine component or use Livewire v4's native dialog handling. Ensure the native `.close()` and `.showModal()` are called in sync with Alpine state.
  </action>
  <verify>
    <automated>php artisan test --filter=ModalBehaviorTest && vendor/bin/pint --dirty</automated>
  </verify>
  <done>
    Admin modals open and close without locking the UI.
  </done>
</task>

<task id="qr_camera_selection" requirement="R1.4">
  <files>
    <file>resources/views/livewire/qr-scanner.blade.php</file>
  </files>
  <action>
    Implement `Html5Qrcode.getCameras()` enumeration on scanner start. Add a "Flip" icon if 2 cameras are found, or a "Select" dropdown if > 2 cameras are found. Update the scanning start logic to use the selected camera ID.
  </action>
  <verify>
    <automated>php artisan test --filter=QrScannerTest && vendor/bin/pint --dirty</automated>
  </verify>
  <done>
    QR scanner supports manual camera selection.
  </done>
</task>
