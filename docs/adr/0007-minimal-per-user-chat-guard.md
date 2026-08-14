# Minimal per-user chat guard: existing auth middleware, no new machinery

Before Phase 14 brings rate limits and cost guards, chat needs only the guard the app already has. **The widget mounts behind the existing `['auth', 'verified']` route middleware group, exactly like any authenticated page** — no new middleware, no guard class, no rate limiting (Phase 14). Unauthenticated and unverified users never see the chat affordance; suspended accounts are already redirected to login by the existing `CheckAccountStatus` middleware (`account_status !== 'active'`). Guests get no conversations, consistent with ADR 0005's `user_id` NOT NULL.

**No role gating.** Any authenticated active user — student, librarian, admin — can chat. Role-aware answer depth is CHAT-03, already out of scope (Phase 12); the widget uses the plain auth group, not the `librarian.or.admin` pattern, with no `can:` permission checks.

**The per-user boundary is Laravel-only.** Ownership enforcement from ADR 0005 stands (single owner, loads scoped to `auth()->id()`); the sidecar never receives user identity — `X-Sidecar-Token` (ADR 0004) is service-level auth, and the sidecar sees only `{query, mode, corpus?, top_k}`. This keeps the PII surface exactly at the Livewire boundary where OPS-03 (Phase 14) will sanitize it.

**No blocked-state rendering.** Because the guard is entrance-level (guests never get the widget, suspended users are redirected), there is no "blocked" UI state to design. The only residual case is an authenticated session expiring mid-use, which the app's existing auth behavior already covers (redirect to login on the next action). The widget assumes an authenticated user by construction.

_Considered (rejected):_ a dedicated chat guard middleware (duplicates the app's auth flow); role-restricted chat (CHAT-03's job, Phase 12); guest chat with nullable `user_id` (ADR 0005 rejects guest conversations); sending a user identifier to the sidecar (violates the Laravel-only boundary and widens the Phase 14 PII surface); an inline "sign in to chat" widget state (no guest exposure exists to warrant it).
