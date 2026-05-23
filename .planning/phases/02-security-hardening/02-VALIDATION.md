---
phase: 2
slug: security-hardening
status: approved
nyquist_compliant: true
wave_0_complete: true
created: 2026-05-23
---

# Phase 2 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit v11 |
| **Config file** | phpunit.xml |
| **Quick run command** | `php85 artisan test --filter=Security` |
| **Full suite command** | `php85 artisan test` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php85 artisan test --filter=Security`
- **After every plan wave:** Run `php85 artisan test`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 10 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 02-01-01 | 01 | 1 | D-01 / D-03 / D-04 | T-02-02 | Global middleware blocks suspended users, exception handler redirects unauthorized with toast, rate limiters configured | feature | `php85 artisan test --filter=MiddlewareTest` | ✅ W0 | ⬜ pending |
| 02-01-02 | 01 | 1 | D-02 | T-02-01 | Component-level authorization gates checked in mount() and action methods | feature | `php85 artisan test --filter=MiddlewareTest` | ✅ W0 | ⬜ pending |
| 02-02-01 | 02 | 2 | D-05 / D-06 | T-02-03 | Form Object validation using custom SafeText / NoHtmlTags rules | feature | `php85 artisan test --filter=FormValidationTest` | ✅ W0 | ⬜ pending |
| 02-02-02 | 02 | 2 | D-07 / D-08 | T-02-04 | Inputs strictly bound by database constraints and custom messages verified | feature | `php85 artisan test --filter=FormValidationTest` | ✅ W0 | ⬜ pending |
| 02-03-01 | 03 | 3 | D-09 | T-02-05 | QR code dynamically includes timestamp and client auto-refreshes every 15s | manual | N/A | ✅ W0 | ⬜ pending |
| 02-03-02 | 03 | 3 | D-09 / D-10 | T-02-05 | QR scan time-window check enforces ±60s clock drift and nonce replay cache | feature | `php85 artisan test --filter=QrScannerTest` | ✅ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Client QR Refresh | D-09 | Relies on client-side JS / wire:poll execution | Log in as student, verify QR code visual image rotates/regenerates every 30 seconds. |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 10s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-05-23
