# CEIT-Library Architectural Overview

## System Philosophy
The CEIT-Library system is a modern Laravel 12 application designed for library management with a strong emphasis on user accountability and automation. It uses a **Credit Score** model to incentivize proper library usage and automated **Librarian Assignment** to handle staffing.

## Core Domain Models & Relationships
- **User**: The central entity. Roles (Student, Admin, Librarian) are managed via a `roles` table and transient `Librarian` assignments.
- **AcademicPaper**: Represents research papers. Has many **Authors**, **ResearchAdviser**, and **TechnicalAdviser**. Tracked via **Inventory** (individual copies).
- **BorrowTransaction**: Manages the lifecycle of borrowing papers. Statuses: `started`, `completed`, `overdue`, `late_return`.
- **Attendance**: Tracks library entry/exit using QR codes.
- **Credit Score System**:
    - **ScoreIncrement**: Adds points for positive actions (on-time returns, long study sessions).
    - **ViolationTransaction**: Deducts points for negative actions (overdue, forgot to time-out).
    - *Atomic Updates*: Credit scores are updated using atomic SQL operations (`User::query()->increment/decrement`) within model `booted` hooks to ensure data integrity and clamping (0-100).

## Key Business Flows
1. **QR Attendance**:
    - Students generate a canonical QR message.
    - Librarians scan the QR using the `QrScanner` (Livewire).
    - System handles `time-in` or `time-out` based on the user's active attendance session.
2. **Borrowing Process**:
    - Student requests a paper. Librarian scans the paper's QR code.
    - `BorrowTransaction` is created. Inventory status changes to `unavailable`.
    - On return, the transaction is marked `completed`. If returned on time, a `ScoreIncrement` is triggered.
3. **Automated Librarian Roles**:
    - `UpdateLibrarianRoles` command runs daily to assign/revoke the 'Librarian' role to students based on the current batch schedule.

## Technology Stack
- **Backend**: Laravel 12, PHP 8.2
- **Interactivity**: Livewire 3 (Volt for single-file components)
- **UI Components**: Mary UI (Tailwind CSS based)
- **Scanning**: QR-based system using canonical messages.
- **Database**: PostgreSQL (implied by constraints/atomic updates) or MySQL.

## Testing & Quality
- **Test-Driven Development**: Extensive use of PHPUnit (Feature and Unit tests).
- **Security**: Custom middleware (`AdminOnly`, `LibrarianOrAdmin`, `CheckCreditScore`) enforces access control and behavioral constraints.
- **Validation**: Strict input validation using custom rules (`NoHtmlTags`, `PlvEmailDomain`, `ProperName`, `SafeText`).

## Architectural Insights for Developers
- **Logic Placement**: Business rules are often encapsulated in Model `booted` hooks or dedicated Action classes to keep Controllers/Livewire components lean.
- **Atomic Operations**: Always use `ScoreIncrement::updateUserCreditScoreAtomic()` or similar patterns when adjusting scores to avoid race conditions.
- **Role Checks**: Use `$user->hasAdminAccess()` or `$user->isLibrarian()` for consistent authorization logic.
- **Vite/Frontend**: Run `npm run dev` or `npm run build` to reflect UI changes.
