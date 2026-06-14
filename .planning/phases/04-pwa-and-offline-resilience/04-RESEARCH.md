# Phase 4 Research: PWA Hardening & Offline Resilience

## 1. Caching Strategy: Cache-First Implementation
To achieve instant loading, we will transition from basic PWA to a **Cache-First** strategy for all static assets.

### Technical Approach: `injectManifest`
Currently, the project uses a manual `sw.js`. We will switch to the `injectManifest` strategy in `vite-plugin-pwa` to allow custom logic (Push Notifications) while leveraging Workbox for automatic asset precaching and runtime caching.

**Vite Config Updates:**
- Strategy: `injectManifest`
- Source: `resources/js/sw.js`
- Injection Point: `self.__WB_MANIFEST`

**Workbox Patterns:**
- **Precache:** All Vite-built assets (JS, CSS, fonts).
- **Runtime Caching (CacheFirst):** External fonts (Bunny Fonts) and static images in `public/images`.
- **NetworkFirst:** API routes and Livewire updates to ensure data freshness.

## 2. App Badging API
We will implement the **App Badging API** to show the unread notification count on the app icon.

### Implementation Details:
- **Service Worker:** Listen for `push` events. If the payload contains an `unread_count`, call `navigator.setAppBadge(count)`.
- **Client Side:** When the user navigates to the notifications page or marks notifications as read, call `navigator.clearAppBadge()`.
- **Compatibility:** Graceful fallback for browsers that do not support the API.

## 3. Custom Installation UX: Branded Banner
To improve user conversion, we will replace the browser's generic install prompt with a branded "Install CEIT Lib" banner.

### Design Pattern:
- **Trigger:** Listen for the `beforeinstallprompt` event.
- **State Management:** Use Alpine.js to store the event and show/hide the banner.
- **Component:** A persistent but dismissible bottom banner with CEIT branding, appearing on the dashboard for unread users.

## 4. Offline Detection (QR Scanner)
Per the "Level 2" requirement, we will implement strict offline detection in the QR scanner to prevent invalid transaction attempts.

### UX Plan:
- **Detection:** Use `window.navigator.onLine` and `online`/`offline` window events.
- **Interaction:**
  - If the user clicks "Scan" while offline, show an immediate "System Offline" alert.
  - If the connection is lost while the scanner is active, display a "Connection Lost" overlay and pause the scanner.

## 5. Directory & File Changes
- **Create:** `resources/js/sw.js` (Manual SW source).
- **Modify:** `vite.config.js` (PWA plugin configuration).
- **Modify:** `resources/views/components/layouts/app.blade.php` (Custom Install logic).
- **Modify:** `resources/views/livewire/qr-scanner.blade.php` (Offline detection UI).
- **Delete:** `public/sw.js` (Replaced by Vite-built version).

## Next Steps
- [ ] Move `public/sw.js` logic to `resources/js/sw.js`.
- [ ] Configure `vite.config.js` for `injectManifest`.
- [ ] Implement the `InstallBanner` component.
- [ ] Add App Badging logic to SW and Notification pages.
