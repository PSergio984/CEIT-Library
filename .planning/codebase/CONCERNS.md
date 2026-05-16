# Codebase Concerns

**Analysis Date:** 2025-03-05

## Tech Debt

**Redundant Model Booting:**
- Issue: `BorrowTransaction` model implements both `boot()` and `booted()` methods for registering model events, which is unconventional in Laravel and can lead to maintenance confusion.
- Files: `app/Models/BorrowTransaction.php`
- Impact: Increased complexity and potential for double-registration of events if not handled carefully.
- Fix approach: Consolidate all event registrations and boot logic into the `booted()` method.

**Fat Livewire Components:**
- Issue: Extensive business logic for QR code processing, decryption, and attendance/borrowing state transitions is implemented directly within Livewire components.
- Files: `app/Livewire/QrScanner.php`, `app/Livewire/Pages/Admin/AdminBorrowTransactions.php`
- Impact: Difficulty in unit testing business logic, code duplication across components, and reduced maintainability.
- Fix approach: Extract business logic into dedicated Action or Service classes (e.g., `ProcessAttendanceAction`, `ValidateBorrowQrAction`).

**Inline Validation:**
- Issue: Some components use `$this->validate()` directly instead of using Form Request classes or dedicated Form objects, which violates the "Always use Form Request" guideline.
- Files: `app/Livewire/Pages/Admin/AdminBorrowTransactions.php`
- Impact: Validation logic is not reusable and makes the component harder to read.
- Fix approach: Move validation rules to Form Request classes or use Livewire's `Form` objects.

## Known Bugs

**Incomplete Overdue Feature:**
- Symptoms: Overdue book detection is not fully implemented according to test expectations.
- Files: `tests/Feature/LibrarySystemTest.php`, `app/Models/BorrowTransaction.php`
- Trigger: N/A - marked as TODO in the test suite.
- Workaround: None. The feature is missing required accessors like `is_overdue` and `days_remaining`.

## Security Considerations

**Permanent Attendance QR Codes:**
- Risk: Attendance QR codes are permanent and do not use nonces for replay prevention. A photo of a QR code can be used indefinitely to check in/out for a student.
- Files: `app/Livewire/QrScanner.php`
- Current mitigation: Basic HMAC verification for tamper protection and rate limiting.
- Recommendations: Implement time-based tokens (TOTP) or short-lived nonces to ensure the physical presence of the student.

**Raw QR Data Logging:**
- Risk: Logging raw QR data may leak sensitive session tokens or PII into application logs.
- Files: `app/Livewire/Pages/Admin/AdminBorrowTransactions.php`
- Current mitigation: None observed.
- Recommendations: Sanitize logs or remove raw data logging in production environments.

## Performance Bottlenecks

**Repeated Pluck Queries:**
- Problem: Distinct pluck query for `paper_type` runs on every render of the admin borrow transactions page.
- Files: `app/Livewire/Pages/Admin/AdminBorrowTransactions.php`
- Cause: `AcademicPaper::distinct()->pluck('paper_type')` is called within a property getter.
- Improvement path: Cache the results or use a separate computed property that only updates when necessary.

## Fragile Areas

**Temporal Logic in Models:**
- Files: `app/Models/User.php`, `app/Models/BorrowTransaction.php`
- Why fragile: Methods like `isLibrarian()` and `isOverdue()` depend on `now()`, which can cause race conditions or inconsistent state if multiple checks are performed during a single request at the boundary of an expiration.
- Safe modification: Pass a fixed timestamp to these methods or ensure they use a single cached `now()` instance during the request.
- Test coverage: Gaps in testing edge cases exactly at the expiration boundary.

## Missing Critical Features

**Overdue Book Detection Accessors:**
- Problem: `BorrowTransaction` lacks `is_overdue` and `days_remaining` accessors.
- Blocks: Automated overdue notifications and reporting features.

## Test Coverage Gaps

**Borrowing Edge Cases:**
- What's not tested: Specific overdue detection logic and penalty calculations.
- Files: `app/Models/BorrowTransaction.php`
- Risk: Incorrect penalty application or missed overdue notifications.
- Priority: Medium

---

*Concerns audit: 2025-03-05*
