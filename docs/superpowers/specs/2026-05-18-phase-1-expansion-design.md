# Design Spec: Phase 1 Expansion - Experience & Speed

**Date:** 2026-05-18
**Status:** DRAFT
**Phase:** 1 (Modernization & UX)

## 1. Overview
This design expands Phase 1 of the CEIT-Library project to prioritize mobile portability, performance, and overall user experience. The goal is to transform the existing web application into a professional, app-like experience (PWA) that is fast, intuitive for one-handed use, and visually polished.

## 2. PWA (Progressive Web App) Implementation
To achieve the "App-Like Feel", we will implement PWA standards.

### Core Components
- **Vite PWA Plugin:** Integration of `vite-plugin-pwa` for automated manifest and service worker generation.
- **Web Manifest:**
    - `name`: CEIT Library (PLV)
    - `short_name`: CEIT Library
    - `theme_color`: #0046ad (PLV Blue - verified from branding)
    - `background_color`: #ffffff
    - `display`: standalone
    - `icons`: Generated from `resources/images/ceit-logo.png` (192x192, 512x512).
- **Service Worker:** Basic "Cache-First" strategy for static assets (fonts, icons, CSS) to ensure fast repeat loads.

## 3. Hybrid Bottom Navigation (Mobile-Only)
On mobile devices (below `lg` breakpoint), the traditional hamburger sidebar will be replaced by a hybrid navigation bar.

### Navigation Slots
1. **Home:** `/dashboard` (Icon: `o-home`)
2. **Papers:** `/academic-papers` (Icon: `o-book-open`)
3. **Action (Center):** 
    - *Student View:* "My QR" (Triggers `AttendanceQr` modal/overlay)
    - *Librarian/Admin View:* "Scan" (Triggers `QrScanner` view)
    - Icon: `o-qr-code` (Distinctive color or size)
4. **Inbox:** `/notifications` (Icon: `o-bell` with badge)
5. **Menu (More):** Slide-up drawer using Mary UI `Drawer` containing:
    - Rules & Regulations
    - Profile
    - Credit Score History
    - Admin Dashboard (if permitted)
    - Theme Toggle

## 4. Performance Optimization
- **Image Handling:**
    - Backgrounds and logos converted to WebP format.
    - Implementation of native `loading="lazy"` on all images.
- **Vite Bundling:**
    - Chunk splitting: Separate vendors (Mary UI, Livewire, Alpine) into their own JS files for better browser caching.
- **State Management:**
    - Leverage Livewire v4's optimized diffing.
    - Use `#[Locked]` attributes for immutable properties to reduce payload size.

## 5. UI Polishing & Interactions
- **Transitions:** Apply `wire:transition` to all Livewire-driven overlays and slide-ins.
- **Teleportation:** Move all modals to the root level using `<div x-teleport="body">` to prevent layout breaking or "locking" from nested CSS.
- **Stacking Tables:** Use Tailwind `hidden sm:block` for desktop tables and `block sm:hidden` for mobile card-based views in `AdminUserList` and `AcademicPaperIndex`.
- **Haptic Feedback:** Simple JS dispatch on successful scan: `if (window.navigator.vibrate) { window.navigator.vibrate(50); }`.

## 6. Success Criteria
- [ ] App is installable on Android/iOS (PWA prompt works).
- [ ] Bottom Nav is fully functional and responsive (hides/shows correctly).
- [ ] First Contentful Paint (FCP) improved by ~20%.
- [ ] No "UI Locking" when opening/closing modals on mobile.
