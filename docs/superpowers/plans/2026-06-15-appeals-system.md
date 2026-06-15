# Appeals System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a formal system for students to appeal library violations and a shared queue for admins to process them.

**Architecture:** Create an `Appeal` model linked to `Violation`. Extend the student notification UI to include an "Appeal" button and create a new Admin dashboard page for processing appeals.

**Tech Stack:** Laravel, Livewire/Volt, PHPUnit.

---

### Task 1: Appeal Model and Database

**Files:**
- Create: `database/migrations/2026_06_15_000002_create_appeals_table.php`
- Create: `app/Models/Appeal.php`
- Modify: `app/Models/Violation.php`

- [ ] **Step 1: Create Migration**
```php
Schema::create('appeals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('violation_id')->constrained()->onDelete('cascade');
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->text('reason');
    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
    $table->foreignId('reviewer_id')->nullable()->constrained('users');
    $table->text('admin_response')->nullable();
    $table->timestamps();
});
```

- [ ] **Step 2: Model Setup**
```php
// app/Models/Violation.php
public function appeal() {
    return $this->hasOne(Appeal::class);
}
```

- [ ] **Step 3: Commit**
```bash
git add database/migrations/2026_06_15_000002_create_appeals_table.php app/Models/Appeal.php
git commit -m "feat: create appeals table and model"
```

### Task 2: Student Appeal Form (Volt)

**Files:**
- Create: `resources/views/livewire/pages/Student/student-appeal-form.blade.php`
- Modify: `resources/views/livewire/pages/Student/student-notifications.blade.php`

- [ ] **Step 1: Add "Appeal" button to Notification list**
Update the violation notification card to show an "Appeal" button if `violation->appeal` doesn't exist.

- [ ] **Step 2: Create the Appeal Form Component**
Use Volt to create a modal or page where the student writes their reason and saves the `Appeal`.

- [ ] **Step 3: Commit**
```bash
git add resources/views/livewire/pages/Student/student-appeal-form.blade.php
git commit -m "feat: add student appeal submission form"
```

### Task 3: Shared Admin Appeals Queue

**Files:**
- Create: `app/Livewire/Pages/Admin/AdminAppealsQueue.php`
- Create: `resources/views/livewire/pages/Admin/admin-appeals-queue.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Register Route**
`Route::get('/admin/appeals', AdminAppealsQueue::class)->middleware(['auth', 'librarian.or.admin']);`

- [ ] **Step 2: Build the Queue Table**
List all `pending` appeals with columns: Student Name, Violation Type, Reason, Date.

- [ ] **Step 3: Implement Actions (Approve/Reject)**
- `approve()`: Mark appeal as approved, soft-delete the `Violation`, restore credit score.
- `reject()`: Mark as rejected, notify user.

- [ ] **Step 4: Commit**
```bash
git add app/Livewire/Pages/Admin/AdminAppealsQueue.php resources/views/livewire/pages/Admin/admin-appeals-queue.blade.php
git commit -m "feat: implement shared admin appeals queue"
```
