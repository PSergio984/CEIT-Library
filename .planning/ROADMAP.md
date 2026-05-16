# Roadmap: CEIT-Library Improvements

## Milestone: Bug Fixes & Security Improvements (v1.1)

### Phase 1: Modernization & Frontend Bug Fixes
**Goal:** Upgrade to Livewire v4, modernize dependencies, and resolve critical frontend interaction issues.

**Scope:**
- [R1.1] Upgrade Livewire from v3 to v4.
- [R1.2] Modernize dependencies (Mary UI v2, Tailwind CSS v4, daisyUI v5).
- [R1.3] Fix Admin Table modal interaction bug (UI becomes unresponsive after modal close).
- [R1.4] Fix QR Scanner mobile camera issue (force back-facing camera instead of front-facing).
- [R1.5] Initial security audit of frontend components.

**Success Criteria:**
- Application successfully runs on Livewire v4.
- Modals on Admin tables can be opened and closed repeatedly without locking the UI.
- QR Scanner on mobile devices defaults to the back-facing camera.
- All existing tests pass.

### Phase 2: Security Hardening
**Goal:** Enhance application security by auditing middleware and validation rules.

### Phase 3: Stability & Performance
**Goal:** Address remaining failing tests and optimize database queries.
