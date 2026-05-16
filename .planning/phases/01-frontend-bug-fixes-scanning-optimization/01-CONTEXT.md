# Phase 1: Frontend Bug Fixes & Scanning Optimization - Context

**Gathered:** 2026-05-16
**Status:** Ready for planning

<domain>
## Phase Boundary

Resolve critical frontend interaction bottlenecks in the Admin dashboard and optimize the QR scanning hardware integration for mobile devices.

</domain>

<decisions>
## Implementation Decisions

### Performance & Navigation
- **D-01:** **Performance-First Approach.** Prioritize sub-100ms navigation. Focus on Lazy Loading for heavy components (like Admin tables) and auditing Tailwind v4/Vite output to strip unnecessary assets.
- **D-02:** **Isolate "Invisible" Blocking.** Investigate the "invisible" layer preventing mouse interaction after modal closure. This is likely a global `wire:loading` overlay or a Mary UI modal backdrop that fails to clear.
- **D-03:** **Local over Global Loading.** Refactor full-screen loading overlays to local, button-level spinners to prevent UI locking during AJAX requests.

### Hardware & UX
- **D-04:** **Force Back-Facing Camera.** Configure the QR scanner to strictly prefer the 'environment' (back-facing) camera on mobile devices by default, resolving the front-camera fallback issue.

### Security Hardening
- **D-05:** **Authorization Audit.** Initial security focus will be a comprehensive audit of all Livewire actions to ensure strict `authorize()` checks are implemented, preventing unauthorized state mutations.

### Claude's Discretion
- **D-06:** **Implementation Patterns.** Claude has discretion over the specific Livewire/Alpine patterns used to resolve the modal hang, provided they favor performance and prevent UI locking.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Project Infrastructure
- `.planning/ROADMAP.md` — Milestone v1.1 scope and goals.
- `.planning/PROJECT.md` — High-level project objectives and tech stack.
- `.planning/ARCHITECTURE_OVERVIEW.md` — System philosophy and core domain models.

### Scanning & UI
- `app/Livewire/QrScanner.php` — Current scanning logic.
- `resources/views/livewire/qr-scanner.blade.php` — Scanner UI and camera initialization script.
- `resources/views/livewire/pages/admin/admin-academic-paper-index.blade.php` — Example of Admin table with modal/loading logic.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **Mary UI Modals:** Currently used for admin actions. Need verification on backdrop clean-up.
- **jsQR:** Currently used for scanning. Initialization script in `qr-scanner.blade.php` needs adjustment for `facingMode`.

### Established Patterns
- **Global Loading Overlays:** Used in `admin-academic-paper-index.blade.php`. This pattern is a prime suspect for the UI "locking" issue.

### Integration Points
- **Admin Dashboard Tabs:** Where scanning and table interactions are most frequent.
- **Livewire Event Listeners:** `scanner-stopped` and `close-qr-modal` events are already dispatched but may need tighter integration with Alpine.js state.

</code_context>

<specifics>
## Specific Ideas
- **Mouse Interaction Recovery:** Ensure the `Fixed inset-0` div (likely the loading overlay) is properly removed from the DOM on request completion.
- **Livewire v4 Investigation:** While v3.6 is current, any planning should keep performance-centric features (like simpler hydration) in mind if an upgrade path to a newer major version is pursued.

</specifics>

<deferred>
## Deferred Ideas

- **Full Livewire Major Upgrade:** Deferred until performance audit confirms if current v3.x optimizations are insufficient.
- **Mobile UI Redesign:** Staying focused on functional bug fixes (camera/locking) for now.

</deferred>

---

*Phase: 01-Frontend Bug Fixes & Scanning Optimization*
*Context gathered: 2026-05-16*
