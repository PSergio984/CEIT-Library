# QR Scanner Overhaul Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete overhaul of the QR scanning system to optimize payload size, improve scannability, and unify scanning libraries for better reliability.

**Architecture:** 
1. **Payload Optimization:** Remove redundant data (PII) from the QR payload and use shorter nonces to reduce QR code density.
2. **Library Unification:** Switch primarily to `Html5Qrcode` for both camera and file scanning, removing the complex manual `jsQR` loop.
3. **Robust Validation:** Update backend validation to handle the new optimized format while maintaining security with HMAC and encryption.

**Tech Stack:** Laravel, Livewire, Alpine.js, html5-qrcode, jsQR (fallback).

---

### Task 1: Payload Optimization (Trait & Generation)

**Files:**
- Modify: `app/Traits/CreatesQrCanonicalMessage.php`
- Modify: `app/Livewire/Pages/Student/AttendanceQr.php`

- [ ] **Step 1: Update Trait with optimized canonical message logic**
Update `createCanonicalMessage` to be more flexible and add a helper for attendance.

- [ ] **Step 2: Update AttendanceQr to use optimized payload**
Bump `QR_CODE_VERSION` to `v6`, remove `user` object from payload, and shorten `nonce` to 16 chars.

- [ ] **Step 3: Clear QR cache to force regeneration**
Run `php artisan cache:clear` (or specific keys if possible).

---

### Task 2: Backend Validation Overhaul

**Files:**
- Modify: `app/Livewire/QrScanner.php`
- Modify: `app/Livewire/TestQrScanner.php`

- [ ] **Step 1: Update decryption and validation logic**
Update `decryptAndValidateAttendanceData` to handle the v6 format (missing `user` object).

- [ ] **Step 2: Add logging for better debugging**
Ensure clear logs when validation fails.

---

### Task 3: Frontend Scanning Overhaul (JS)

**Files:**
- Modify: `resources/views/livewire/qr-scanner.blade.php`

- [ ] **Step 1: Replace jsQR camera loop with Html5Qrcode**
Use `Html5Qrcode.start()` for camera scanning. This handles multiple cameras and resolutions natively.

- [ ] **Step 2: Unify file upload scanning**
Use `Html5Qrcode.scanFile()` as the primary method for file uploads, with `jsQR` as a fallback if needed.

- [ ] **Step 3: Fix Alpine.js errors**
Ensure proper cleanup and method binding to avoid "Illegal invocation" errors.

---

### Task 4: Verification & Testing

**Files:**
- Create: `tests/Feature/QrScannerTest.php`

- [ ] **Step 1: Write integration tests**
Verify that a generated v6 QR code is correctly validated by the `QrScanner` component.

- [ ] **Step 2: Verify camera UI**
Manually check the UI components for any layout regressions.

- [ ] **Step 3: Final Linting**
Run `vendor/bin/pint --dirty` to ensure code style consistency.
