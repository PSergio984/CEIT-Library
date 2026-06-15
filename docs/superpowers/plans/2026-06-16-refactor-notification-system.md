# Refactor Notification System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor the notification system to use `NotificationService` instead of direct `Notification::create` calls. This centralizes notification logic and enables push/email notifications.

**Architecture:** Replace `Notification::create([...])` with `app(\App\Services\NotificationService::class)->notify($user, $type, $title, $message, $data)`. Ensure `User` model is available or fetched.

**Tech Stack:** Laravel 13, PHP 8.2

---

### Task 1: Refactor Console Commands

**Files:**
- Modify: `app/Console/Commands/SendDeadlineWarnings.php`
- Modify: `app/Console/Commands/SendPushNotification.php`

- [ ] **Step 1: Update SendDeadlineWarnings.php**
Replace `Notification::create` with `$service->notify`. Use `$transaction->user` (eager load if possible or just use relationship).

```php
// In SendDeadlineWarnings.php
// Ensure User is loaded or fetch it
$service = app(\App\Services\NotificationService::class);
$service->notify(
    $transaction->user,
    'paper_deadline_warning',
    'Deadline Approaching!',
    "Your borrowed material \"{$transaction->academicPaper->title}\" is due in less than 30 minutes. Please return it to avoid a penalty.",
    [
        'transaction_id' => $transaction->id,
        'paper_title' => $transaction->academicPaper->title,
        'expires_at' => $transaction->expires_at->format('M d, Y h:i A'),
        'url' => '/student/dashboard',
    ]
);
```

- [ ] **Step 2: Update SendPushNotification.php**
Replace `Notification::create` with `$service->notify`. `$user` is already available.

```php
// In SendPushNotification.php
app(\App\Services\NotificationService::class)->notify(
    $user,
    'manual_push',
    $title,
    $message,
    ['url' => $url]
);
```

### Task 2: Refactor Admin Livewire Pages

**Files:**
- Modify: `app/Livewire/Pages/Admin/ActiveUsersTab.php`
- Modify: `app/Livewire/Pages/Admin/AdminAssignLibrarians.php`
- Modify: `app/Livewire/Pages/Admin/AdminBorrowTransactions.php`
- Modify: `app/Livewire/Pages/Admin/AdminManageRoles.php`

- [ ] **Step 1: Update ActiveUsersTab.php**
Find `Notification::create` calls (e.g. for violations or manual notifications).
Ensure `User` is fetched if only `user_id` is present.

- [ ] **Step 2: Update AdminAssignLibrarians.php**
Replace calls where students are assigned to batches.
```php
$user = User::find($userId);
app(\App\Services\NotificationService::class)->notify(
    $user,
    'librarian_assigned',
    'You have been assigned as a Librarian',
    $message,
    [
        'batch_no' => $this->newBatchNo, // or editingBatchNo
        'assigned_by' => Auth::id(),
    ]
);
```

- [ ] **Step 3: Update AdminBorrowTransactions.php**
Replace manual notification calls if any.

- [ ] **Step 4: Update AdminManageRoles.php**
Replace `Notification::create` calls for role changes.
```php
app(\App\Services\NotificationService::class)->notify(
    $user,
    'role_changed',
    'Your Role Has Been Updated',
    $roleChangeMessage,
    $data
);
```

### Task 3: Refactor Models and Traits

**Files:**
- Modify: `app/Models/Attendance.php`
- Modify: `app/Models/BorrowTransaction.php`
- Modify: `app/Services/LibrarianStatusService.php`
- Modify: `app/Traits/ProcessesAttendanceQr.php`

- [ ] **Step 1: Update Attendance.php**
Replace notifications in `time_out` or other hooks. Use `$this->user`.

- [ ] **Step 2: Update BorrowTransaction.php**
Replace notifications for returns and overdue alerts. Use `$this->user`.

- [ ] **Step 3: Update LibrarianStatusService.php**
Replace notifications for status changes.

- [ ] **Step 4: Update ProcessesAttendanceQr.php**
Replace check-in/check-out notifications. Use the `$user` variable available in the trait method.

### Task 4: Cleanup and Verification

- [ ] **Step 1: Run Laravel Pint**
Run: `vendor/bin/pint`

- [ ] **Step 2: Verify application boots**
Run: `php artisan list`

- [ ] **Step 3: Run existing notification tests**
Run: `php artisan test --filter=Notification`
