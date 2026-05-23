# Phase 2: Security Hardening - Context

**Gathered:** 2026-05-23
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 2 focuses on enhancing application security by auditing and hardening middleware, route-level authorization, validation rules (specifically in Livewire components and form requests), and mitigating replay attacks on the student attendance QR scanner.

</domain>

<decisions>
## Implementation Decisions

### Middleware & Route Protection
- **D-01:** Redirect unauthorized users back to the student dashboard (if authenticated) or login page (if guest) with a toast notification rather than showing a raw 403 Access Denied page.
- **D-02:** Enforce `Gate` or `authorize()` checks both at the route level (middleware groups) and inside the component-level logic (e.g. `mount()` and action methods of all Livewire components) to achieve full defense-in-depth.
- **D-03:** Implement a global middleware registered in the `web` middleware group that blocks/redirects any user whose `account_status` is not `'active'` (e.g., suspended accounts).
- **D-04:** Implement specific rate limits on sensitive routes including login, QR scanning, search, and transaction actions to prevent abuse.

### Input Validation Standardization
- **D-05:** Use Livewire Form Objects (`Livewire\Form`) for complex CRUD operations and form submissions, while using inline `#[Validate]` attributes/rules for simple properties (e.g., search queries, simple filters).
- **D-06:** Mandate custom validation rules `NoHtmlTags` and `SafeText` for all user-submitted text/string inputs, including search, filter, and CRUD fields to prevent XSS and control character injection.
- **D-07:** Ensure all validation rules strictly specify size, length, and range bounds (e.g. `max:255`, `min:1`, regex checks) that match or are tighter than the database schema columns to prevent database-level constraint exceptions.
- **D-08:** Standardize on writing explicit, user-friendly custom validation messages for all validation rules to avoid leaking database schema details or raw field names to end users.

### Permanent QR Replay Mitigation
- **D-09:** Mitigate QR code replay attacks by embedding a short-lived timestamp (e.g., 30-second window) inside the client-side generated QR code. The student dashboard QR component must auto-refresh the QR code.
- **D-10:** Allow a ±60 second clock drift window during server-side scan verification to accommodate client-server time sync differences.

### the agent's Discretion
- **QR Replay Strategy selection**: The user opted for "You decide" for QR replay mitigation strategy, and we selected the short-lived timestamp approach (dynamic QR code refreshed on the client and verified on the server) as the most balanced trade-off between user convenience and security implementation.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Route & Middleware Definition
- [routes/web.php](file:///c:/Users/admin/Herd/CEIT-Library/routes/web.php) — Route registration and middleware declarations.
- [bootstrap/app.php](file:///c:/Users/admin/Herd/CEIT-Library/bootstrap/app.php) — Global middleware group registration and custom middleware aliases.
- [app/Http/Middleware/LibrarianOrAdmin.php](file:///c:/Users/admin/Herd/CEIT-Library/app/Http/Middleware/LibrarianOrAdmin.php) — Role-based authorization middleware.
- [app/Http/Middleware/AdminOnly.php](file:///c:/Users/admin/Herd/CEIT-Library/app/Http/Middleware/AdminOnly.php) — Admin-only authorization middleware.

### Scanning & Attendance Logic
- [app/Livewire/QrScanner.php](file:///c:/Users/admin/Herd/CEIT-Library/app/Livewire/QrScanner.php) — QR scanner validation and attendance processing.

### Forms & Validation
- [app/Livewire/Forms/](file:///c:/Users/admin/Herd/CEIT-Library/app/Livewire/Forms/) — Standardized Livewire Form objects directory.
- [app/Rules/NoHtmlTags.php](file:///c:/Users/admin/Herd/CEIT-Library/app/Rules/NoHtmlTags.php) — XSS/HTML tag injection prevention rule.
- [app/Rules/SafeText.php](file:///c:/Users/admin/Herd/CEIT-Library/app/Rules/SafeText.php) — Text character/control byte sanitization rule.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `App\Rules\NoHtmlTags` and `App\Rules\SafeText` rules can be reused across all search, filter, and form inputs.
- `Livewire\Form` subclasses in `app/Livewire/Forms/` serve as templates for any new validation forms.

### Established Patterns
- Hybrid validation pattern: Livewire Form Objects for CRUD, inline attributes for simple state.
- Standard toast notification dispatching via Mary UI's `Toast` trait.

### Integration Points
- `app/Http/Middleware/` to insert new global account status check middleware.
- `bootstrap/app.php` to register the new middleware and route rate limiters.
- `app/Livewire/QrScanner.php` and the client-side QR generation component (e.g. on student dashboard or QR utility) to embed and verify short-lived timestamps.

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches.

</specifics>

<deferred>
## Deferred Ideas

- **Skeleton Loaders & Prefetching**: Implement skeleton loaders for more heuristic UX and add prefetching for a feeling of faster performance. (Deferred to Phase 3: Stability & Performance or future UX optimization phase).

</deferred>

---

*Phase: 2-Security Hardening*
*Context gathered: 2026-05-23*
