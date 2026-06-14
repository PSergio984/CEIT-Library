# Phase 4 Context: PWA Hardening & Offline Resilience

## Decisions Locked

### 1. Caching Strategy
- **Approach:** Cache-First (Option A).
- **Goal:** Maximum speed and instant loading. UI assets (JS, CSS, Fonts, Icons) will be cached and served from the Service Worker immediately.
- **Update Mechanism:** Use the established 'autoUpdate' behavior in `vite-plugin-pwa` to refresh assets in the background, but prioritize the cached version for initial render.

### 2. Offline Behavior (Scanner)
- **Approach:** Warning State (Option B).
- **Experience:** If the device is offline, the QR scanner will display a clear "Offline - Reconnect to Scan" overlay/warning. 
- **Rationale:** Prevents data sync complexity and ensures real-time transaction validity which is critical for library security.

### 3. App Badging
- **Decision:** Enabled (Yes).
- **Implementation:** Utilize the App Badging API to show the unread notification count on the home screen icon. This will sync with the existing Laravel-based notification system via the Service Worker.

### 4. Custom Installation UX
- **Decision:** Branded "Install CEIT Lib" Banner.
- **Design:** A custom UI component (using Mary UI / Tailwind styles) that triggers the native installation prompt.
- **Persistence:** Ensure the banner is non-intrusive and respects "dismiss" actions.

## Downstream Guidance
- **Researcher:** Focus on `vite-plugin-pwa` `workbox` configuration for Cache-First strategies and the `experimental` or `modern` implementations of the Badging API.
- **Planner:** Break down into: 
  1. SW Caching configuration.
  2. Offline detection logic in Livewire components.
  3. Badging API integration.
  4. Branded Installation UI component.
