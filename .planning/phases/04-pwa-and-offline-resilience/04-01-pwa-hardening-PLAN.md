# Plan 04-01: PWA Hardening Implementation

Implement advanced PWA features including Cache-First asset handling, App Badging, and a branded installation banner.

## Goal
Upgrade the PWA from basic manifest registration to a resilient, native-like experience.

## User Review Required
> [!IMPORTANT]
> This plan switches the Service Worker from a manual file to a Vite-managed file. This ensures all compiled assets are automatically cached.

## Proposed Changes

### 1. Service Worker Migration
- Create `resources/js/sw.js` and move the Push Notification logic from `public/sw.js` into it.
- Integrate `workbox-precaching` for `self.__WB_MANIFEST`.

### 2. Vite Configuration
- Update `vite.config.js` to use `VitePWA` with `strategies: 'injectManifest'`.
- Define `runtimeCaching` for external assets (Bunny Fonts).

### 3. App Badging
- Add `navigator.setAppBadge` logic to the `push` event listener in `resources/js/sw.js`.
- Add `navigator.clearAppBadge` logic to the notifications page and layout.

### 4. Branded Installation Banner
- Create a new Blade component `resources/views/components/pwa-install-banner.blade.php`.
- Add Alpine.js logic to `app.blade.php` to handle `beforeinstallprompt`.

### 5. Offline Scanner Safety
- Update `resources/views/livewire/qr-scanner.blade.php` to include an offline detection overlay.

## Verification Plan

### Automated Tests
- No new automated tests required for browser-level PWA features, but we will verify `vite build` generates the correct manifest and service worker.

### Manual Verification
1. **PWA Installation:** Verify the branded banner appears and triggers the install prompt.
2. **Caching:** Inspect DevTools -> Application -> Cache Storage to confirm assets are cached.
3. **Offline Mode:** Toggle "Offline" in DevTools and confirm the app still loads and the scanner shows the offline warning.
4. **App Badging:** Send a test push notification and verify the badge appears on the app icon.
