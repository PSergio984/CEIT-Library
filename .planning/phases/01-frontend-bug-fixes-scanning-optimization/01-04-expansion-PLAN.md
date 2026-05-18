---
must_haves:
  truths:
    - Vite PWA plugin is configured and generating manifest.json and a service worker.
    - Application is installable on supported devices.
    - Hybrid Bottom Navigation is implemented for mobile views (hidden on desktop).
    - Image assets (plvbg.jpg, logos) are lazy-loaded.
    - Modals are teleported to the body to prevent UI locking.
  artifacts:
    - created: public/manifest.webmanifest
    - updated: vite.config.js
    - updated: resources/views/components/layouts/app.blade.php
    - updated: package.json
requirements:
  - R1.6
depends_on: ["01-02-frontend-fixes-PLAN.md"]
---

# Plan: Phase 1.4 - Mobile Expansion & Performance Optimization

Implement Progressive Web App (PWA) features, Hybrid Bottom Navigation for mobile, and optimize performance via Vite bundling and asset lazy loading.

<task id="pwa_setup" requirement="R1.6">
  <files>
    <file>package.json</file>
    <file>vite.config.js</file>
    <file>resources/views/components/layouts/app.blade.php</file>
  </files>
  <action>
    Install `vite-plugin-pwa`. Configure `vite.config.js` to use `VitePWA` with a basic manifest (name: "CEIT Library", theme_color: "#0046ad", icons: ceit-logo.png). Add the `@vitePWA` directive or generated PWA tags to the `app.blade.php` head.
  </action>
  <verify>
    <automated>npm run build</automated>
  </verify>
  <done>
    PWA plugin integrated and manifest generated successfully.
  </done>
</task>

<task id="hybrid_bottom_nav" requirement="R1.6">
  <files>
    <file>resources/views/components/layouts/app.blade.php</file>
  </files>
  <action>
    Implement Hybrid Bottom Navigation for mobile (`lg:hidden`). Add a fixed bottom bar with 4 core icons (Home, Papers, Scan, Notifications) and a "More" menu triggering the existing Mary UI Drawer. Hide the standard top hamburger menu on mobile. Add `pb-16` padding to the main content wrapper to avoid content hiding behind the bottom nav.
  </action>
  <verify>
    <automated>vendor/bin/pint --dirty</automated>
  </verify>
  <done>
    Hybrid Bottom Navigation implemented for mobile devices.
  </done>
</task>

<task id="performance_and_ui_polishing" requirement="R1.6">
  <files>
    <file>resources/views/components/layouts/app.blade.php</file>
    <file>resources/views/livewire/pages/Admin/admin-academic-paper-index.blade.php</file>
  </files>
  <action>
    1. Add `loading="lazy"` to the CEIT logo image in the sidebar.
    2. Add `x-teleport="body"` to any Mary UI modal instances (e.g., in `admin-academic-paper-index.blade.php`) to ensure they render at the root level.
    3. Apply `wire:transition` to the sidebar drawer to ensure smooth slide-in.
  </action>
  <verify>
    <automated>vendor/bin/pint --dirty</automated>
  </verify>
  <done>
    Images lazy-loaded and modals teleported for UI stability.
  </done>
</task>
