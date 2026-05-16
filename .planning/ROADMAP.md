# Roadmap: CEIT-Library Improvements

## Milestone: Bug Fixes & Security Improvements (v1.1)

### Phase 1: Frontend Bug Fixes & Scanning Optimization
**Goal:** Resolve critical frontend interaction issues and optimize the QR scanning experience on mobile.

**Scope:**
- Fix Admin Table modal interaction bug (UI becomes unresponsive after modal close).
- Fix QR Scanner mobile camera issue (force back-facing camera instead of front-facing).
- Initial security audit of frontend components.

**Success Criteria:**
- Modals on Admin tables can be opened and closed repeatedly without locking the UI.
- QR Scanner on mobile devices defaults to the back-facing camera.
- All existing tests pass.

### Phase 2: Security Hardening
**Goal:** Enhance application security by auditing middleware and validation rules.

### Phase 3: Stability & Performance
**Goal:** Address remaining failing tests and optimize database queries.
