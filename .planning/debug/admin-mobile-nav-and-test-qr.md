---
status: resolved
trigger: "https://ceit-library.test/test-qr this isnt suppseod t be used anywhere, by te admin, i nticed that the mbile nav bar nl exist on stduent view noitng no admin"
created: 2026-06-16T20:50:00+08:00
updated: 2026-06-16T20:55:00+08:00
---

# Debug Session: Admin Mobile Nav and Test QR Route

## Symptoms

### 1. Expected Behavior
- The `/test-qr` route should not be accessible or exposed.
- Admin views should have a mobile navigation bar just like student views.

### 2. Actual Behavior
- `/test-qr` was active and accessible.
- Admin views lacked a mobile navigation bar.

### 3. Error Messages
- None.

### 4. Timeline
- New feature/change where admin views were recently introduced, and mobile nav is missing.

### 5. Reproduction Steps
- Log in as an admin on a mobile device/view and observe that there is no mobile navigation bar.
- Navigate to `https://ceit-library.test/test-qr` and check accessibility.

## Current Focus
- Resolved all symptoms.

## Evidence
- `routes/web.php` contained an environment-restricted `/test-qr` route.
- `app.blade.php` contained a hardcoded `/test-qr` link for the bottom mobile navigation.
- `admin.blade.php` lacked the bottom mobile navigation container.
- `academic-paper-index.blade.php` had `<dialog>` modals that did not sync native closing events to Alpine.js.
- `ViolationsTab.php` and `ViolationForm.php` had name regexes that blocked any digits `0-9`.
- The `/sw.js` route response did not disable browser caching, leading to 404 errors during updates.

## Resolution
- **Service Worker Caching:** Added `Cache-Control: no-cache, no-store, must-revalidate` headers to `/sw.js` and rebuilt production assets.
- **Sidebar Navigation:** Persisted both layout sidebars using Livewire's `@persist` directive to prevent blinking.
- **Admin Mobile Navigation:** Added a bottom mobile navigation bar in the admin layout `admin.blade.php` with proper spacing wrapper (`pb-16 lg:pb-0`) on mobile.
- **Scan Route Integration:** Changed bottom-nav "Scan" links to target the live `/admin/attendance` route with the `scan=1` parameter and auto-trigger the QR scanner modal.
- **Modals State Sync:** Added `@close` event listeners to native `<dialog>` elements to keep Alpine states in sync.
- **Violation Creation:** Updated name validation regexes to allow digits `0-9` and enabled the submit button by default.
