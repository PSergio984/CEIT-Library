# Codebase Concerns

**Analysis Date:** 2026-06-16

## Fragile Areas

**Temporal Logic in Models:**
- Files: `app/Models/User.php`, `app/Models/BorrowTransaction.php`
- Why fragile: Methods like `isLibrarian()` and `isOverdue()` depend on `now()`, which can cause race conditions or inconsistent state if multiple checks are performed during a single request at the boundary of an expiration.
- Safe modification: Pass a fixed timestamp to these methods or ensure they use a single cached `now()` instance during the request.
- Test coverage: Gaps in testing edge cases exactly at the expiration boundary.

---

*Concerns audit: 2026-06-16*
