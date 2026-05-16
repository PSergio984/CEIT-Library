# Phase 1: Frontend Bug Fixes & Scanning Optimization - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-05-16
**Phase:** 1-Frontend Bug Fixes & Scanning Optimization
**Areas discussed:** Performance, Modal Locking, QR Camera, Security Focus, Interaction Definition

---

## Performance & Upgrade Path

| Option | Description | Selected |
|--------|-------------|----------|
| Audit & Optimize v3 | Audit current v3.x bottlenecks (Lazy Loading, Asset size) | ✓ |
| Aggressive Upgrade | Immediate upgrade to v4 (if stable) | |

**User's choice:** "Best approach" + Performance focus.
**Notes:** User is very concerned about a 1s navigation delay on Laravel Cloud. Recommended a "Performance-First" audit before aggressive major version hopping.

---

## Admin Table Modal "Locking"

| Option | Description | Selected |
|--------|-------------|----------|
| Fix Global Overlays | Debug and keep full-screen loading states | |
| Local Spinners | Refactor to button-level spinners/spanners | ✓ |

**User's choice:** "Best approach" (Local preferred to prevent global locking).
**Notes:** User reports an "invisible layer" blocking the mouse after modal interactions.

---

## QR Scanner Camera Preference

| Option | Description | Selected |
|--------|-------------|----------|
| Cycle Button | Let user toggle between front and back | |
| Force Back Camera | Strictly prefer 'environment' camera by default | ✓ |

**User's choice:** "Sure do that" (Force back camera).
**Notes:** Mobile devices are currently defaulting to front-facing, which is inefficient for scanning.

---

## Security Audit Focus

| Option | Description | Selected |
|--------|-------------|----------|
| Authorization | Strict `authorize()` check audit on all Livewire actions | ✓ |
| QR Integrity | Encryption/Hashing audit | |
| Data Exposure | Public property audit | |

**User's choice:** Authorization.
**Notes:** Ensuring all state mutations are properly guarded.

---

## Interaction Definition

| Option | Description | Selected |
|--------|-------------|----------|
| Mouse | Primary mouse/touch responsiveness recovery | ✓ |
| Keyboard | ESC/Tab navigation recovery | |

**User's choice:** Mouse.
**Notes:** Focus is on the AJAX/Modal hang that prevents mouse interaction.

---

## Claude's Discretion

- Specific loading patterns (Livewire vs Alpine implementation).
- Tooling for performance auditing (Lighthouse vs Laravel Pail/Debugbar).

## Deferred Ideas

- **Livewire v4 Upgrade:** Re-evaluate after baseline optimizations in v3.
- **UI Aesthetic Changes:** Deferred to focus on functional stability.
