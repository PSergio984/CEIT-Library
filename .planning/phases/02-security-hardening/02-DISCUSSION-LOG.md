# Phase 2: Security Hardening - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-05-23
**Phase:** 2-Security Hardening
**Areas discussed:** Middleware Auditing & Route Protection, Input Validation Standardization, Permanent QR Replay Mitigation

---

## Middleware Auditing & Route Protection

| Option | Description | Selected |
|--------|-------------|----------|
| Standard HTTP 403 Page | Keep aborting with standard 403 and custom messages (consistent with current codebase). | |
| Redirect with Toast | Redirect unauthorized users back to the student dashboard/login page with a toast notification. | ✓ |
| You decide | Let the system choose the best approach based on standard security practices. | |

**User's choice:** Redirect with Toast

---

| Option | Description | Selected |
|--------|-------------|----------|
| Full Defense-in-Depth | Enforce Gate/authorize() checks in route middleware AND within mount() / action methods of all Livewire components. | ✓ |
| Route-Only Authorization | Rely solely on route-level middleware and route-gate checks to keep component code clean. | |
| You decide | Let the system choose the best pattern based on codebase consistency. | |

**User's choice:** Full Defense-in-Depth

---

| Option | Description | Selected |
|--------|-------------|----------|
| Global Account Status Middleware | Add middleware to the web group that blocks/redirects any user whose account status is not 'active'. | ✓ |
| Component-Level Checks | Perform checks on specific pages or actions where status matters. | |
| You decide | Let the system choose the best enforcement method. | |

**User's choice:** Global Account Status Middleware

---

| Option | Description | Selected |
|--------|-------------|----------|
| Sensitive Routes Rate Limiting | Rate limit login, QR scanning, and search/transaction routes. | ✓ |
| Global Rate Limiting | Apply a default rate limit to all authenticated web routes. | |
| No Rate Limiting | Do not add route-level rate limiting at this time. | |
| You decide | Let the system choose what to rate limit. | |

**User's choice:** Sensitive Routes Rate Limiting

---

## Input Validation Standardization

| Option | Description | Selected |
|--------|-------------|----------|
| Hybrid Approach | Use Livewire Form Objects for complex forms (CRUD) and inline #[Validate] attributes/rules for simple properties (e.g., search, single filter inputs). | ✓ |
| Strict Form Objects | Require Livewire Form Objects for all components, even simple ones with single-property inputs. | |
| Traditional Form Requests | Use Laravel Form Requests, resolving/validating them manually within Livewire components. | |
| You decide | Apply the cleanest approach for each case, matching current codebase pattern. | |

**User's choice:** Hybrid Approach

---

| Option | Description | Selected |
|--------|-------------|----------|
| Comprehensive Injection Protection | Apply NoHtmlTags and SafeText validation rules to all user-submitted text inputs, including search, filter, and CRUD fields. | ✓ |
| CRUD-Only Protection | Apply these rules only to CRUD inputs (inserts/updates) that write to the database, leaving searches with default validation. | |
| You decide | Let the system choose when and where to apply these rules based on risk profile. | |

**User's choice:** Comprehensive Injection Protection

---

| Option | Description | Selected |
|--------|-------------|----------|
| Strict Schema-Matched Constraints | Always include size, length, and range bounds in validation rules (e.g. max:255, min:1) to match or be stricter than database columns. | ✓ |
| Lazy Validation | Rely on database exceptions for constraint enforcement and keep validation rules lightweight. | |
| You decide | Enforce schema-matching validation for high-risk columns only. | |

**User's choice:** Strict Schema-Matched Constraints

---

| Option | Description | Selected |
|--------|-------------|----------|
| Explicit Custom Messages | Define custom, user-friendly validation messages for all rules to prevent leaking database structure or field names. | ✓ |
| Default Messages | Use default Laravel validation messages, only writing custom overrides when business logic requires it. | |
| You decide | Standardize validation messages based on current project patterns. | |

**User's choice:** Explicit Custom Messages

---

## Permanent QR Replay Mitigation

| Option | Description | Selected |
|--------|-------------|----------|
| Short-Lived Timestamps | Embed a 30-second expiry timestamp in the QR code, requiring students to show the live QR code in the app (auto-refreshes). | |
| TOTP-based Rolling Codes | Implement standard TOTP rolling codes generated via a shared secret between client and server. | |
| Keep Permanent with Rate Limiting | Keep QR codes permanent (for ease of printing) but implement stricter rate limits/IP-based checks. | |
| You decide | Let the system choose the best trade-off between user convenience and security. | ✓ |

**User's choice:** You decide (Selected: Short-Lived Timestamps with client auto-refresh and ±60s clock drift window)

---

| Option | Description | Selected |
|--------|-------------|----------|
| Graceful Clock Drift Window | Allow a ±60 second clock drift window to prevent false failures from minor client-server time differences. | ✓ |
| Server-Generated Token | Have the client fetch a short-lived token from the server, removing reliance on client device clocks (requires network to generate QR). | |
| Strict Hard Expiry | Enforce a strict 30-second window with zero drift tolerance, asking users to sync device clocks on failure. | |
| You decide | Choose the most reliable method to handle time synchronization. | |

**User's choice:** Graceful Clock Drift Window

---

## the agent's Discretion

- **QR Replay Strategy selection**: The user chose "You decide" for QR replay mitigation strategy, and we selected the short-lived timestamp approach (dynamic QR code refreshed on the client and verified on the server) as the most balanced trade-off between user convenience and security implementation.

## Deferred Ideas

- **Skeleton Loaders & Prefetching**: Implement skeleton loaders for more heuristic UX and add prefetching for a feeling of faster performance. (Deferred to Phase 3: Stability & Performance or future UX optimization phase).
