# Phase 7 Context: Security & Privacy Hardening (Milestone v1.3)

## Decisions Locked

### 1. The Offline QR Contradiction
- **Decision:** Embrace Offline Accessibility (Option B).
- **Rationale:** The system previously instructed users to download/screenshot their QR codes for offline use, but strictly enforced a 60-second expiration. Per the user's decision, we will remove the 60-second timestamp validation for Attendance and Borrowing. We will rely solely on the one-time-use "nonce" cache check to prevent immediate double-scanning replay attacks. This ensures the downloaded PNG files actually work when students bring them to the library desk.

### 2. Log Sanitization & Privacy
- **Decision:** Global Log Sanitizer (Option B).
- **Rationale:** Rather than manually hunting for `Log::info` statements, we will implement a custom Monolog Processor in Laravel. This processor will automatically detect and mask Personally Identifiable Information (PII) such as emails, and redact sensitive payload structures (like QR tokens) before they are written to `storage/logs/laravel.log`.

## Downstream Guidance
- **Researcher:** 
  1. Identify where the timestamp validation occurs in `AttendanceService` and `BorrowService`.
  2. Research how to register a custom Monolog Processor in Laravel 13 (`bootstrap/app.php` or `config/logging.php`).
- **Planner:**
  1. Draft a task to remove the `timeDiff` > 60 validation in both services while keeping the `nonce` caching logic.
  2. Draft a task to create and register a `PiiSanitizerProcessor` for the application's logging channels.