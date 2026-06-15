# Design Spec: Milestone v1.2 - Automation & Engagement

**Date:** 2026-06-15
**Status:** Approved
**Phase:** 06 (New Milestone)

## 1. Overview
This milestone focuses on closing the loop between library actions and student engagement. It introduces automated communication, a formal appeals process for transparency, and data-driven reporting for administrators.

## 2. Requirements

### 2.1 Omnichannel Notifications
- **Goal:** Notify users through their preferred channels.
- **Channels:** In-App (Mandatory), Email (Optional), Browser Push (Optional).
- **Automation:** Daily 8:00 AM scan for "Due Soon" (3 days) and "Overdue" papers.
- **Preferences:** Students can toggle notifications for Reminders, Role Changes, and Account Alerts.

### 2.2 Shared Appeals System
- **Goal:** Provide a fair process for disputing violations.
- **Workflow:**
    1. Student receives violation -> Clicks "Appeal" in notification.
    2. Student submits reason via form.
    3. Appeal enters a **Shared Admin Queue**.
    4. Admin/Librarian reviews and Approves (clears violation) or Rejects.
- **Transparency:** All actions are logged and notified back to the student.

### 2.3 Reporting & Analytics
- **Goal:** Empower administrators with library health data.
- **Dashboard:** New `/admin/reports` page.
- **Exports:** PDF (via `dompdf`) and CSV for Borrowing Trends, Violations, and Librarian Activity.
- **Weekly Digest:** Automated Monday morning summary for the Head Librarian.

## 3. Architecture

### 3.1 Data Model
- **`Appeal` Model:**
    - `violation_id` (foreign key)
    - `user_id` (student)
    - `reason` (text)
    - `status` (enum: pending, approved, rejected)
    - `reviewer_id` (nullable foreign key)
    - `response` (nullable text from admin)
- **`NotificationPreference` (JSON on `User` or separate table):**
    - `toggles` (map of category -> channel -> boolean)

### 3.2 Components
- **`NotificationService`:** Singleton service to handle multi-channel dispatch logic.
- **`AdminAppealsQueue`:** Livewire table for managing pending appeals.
- **`AdminReports`:** Dashboard with interactive charts (optional) and export buttons.

## 4. Testing Strategy
- **Unit:** Test `NotificationService` routing logic with mocked channels.
- **Feature:**
    - Create violation -> Verify "Appeal" button appears.
    - Submit appeal -> Verify it appears in the Shared Queue.
    - Approve appeal -> Verify violation is deleted/soft-deleted and score is restored.
- **Automation:** Run the reminder command and verify emails/notifications are queued for the correct users.

## 5. Success Criteria
- Students can receive email/push notifications for overdue books.
- Students can dispute a violation and see its status update in real-time.
- Admins can download a PDF report of monthly library activity.
- No regressions in existing borrowing/librarian workflows.
