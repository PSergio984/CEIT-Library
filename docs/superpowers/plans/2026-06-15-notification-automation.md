# Notification Automation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a centralized Notification Service with user channel preferences and a daily automated reminder system for borrowing.

**Architecture:** A singleton `NotificationService` routes alerts based on a new `notification_preferences` table. A scheduled command runs daily at 8:00 AM to scan for papers due soon or overdue.

**Tech Stack:** Laravel, Livewire, PHPUnit, Mail.

---

### Task 1: Database Schema for Preferences

**Files:**
- Create: `database/migrations/2026_06_15_000001_create_notification_preferences_table.php`
- Create: `app/Models/NotificationPreference.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Create the migration**
```php
Schema::create('notification_preferences', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('category'); // reminders, role_changes, account_alerts
    $table->boolean('email')->default(false);
    $table->boolean('push')->default(false);
    $table->boolean('in_app')->default(true); // Always true
    $table->timestamps();
    $table->unique(['user_id', 'category']);
});
```

- [ ] **Step 2: Run migration**
Run: `php artisan migrate`

- [ ] **Step 3: Define the Model and Relationship**
```php
// app/Models/NotificationPreference.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model {
    protected $fillable = ['user_id', 'category', 'email', 'push', 'in_app'];
}

// app/Models/User.php
public function notificationPreferences() {
    return $this->hasMany(NotificationPreference::class);
}
```

- [ ] **Step 4: Commit**
```bash
git add database/migrations/2026_06_15_000001_create_notification_preferences_table.php app/Models/NotificationPreference.php app/Models/User.php
git commit -m "feat: add notification preferences table and model"
```

### Task 2: Notification Router Service

**Files:**
- Create: `app/Services/NotificationService.php`
- Test: `tests/Unit/NotificationServiceTest.php`

- [ ] **Step 1: Write a unit test for routing**
```php
test('it routes notifications to enabled channels only', function () {
    $user = User::factory()->create();
    NotificationPreference::create([
        'user_id' => $user->id,
        'category' => 'reminders',
        'email' => true,
        'push' => false
    ]);

    $service = new NotificationService();
    // Logic to verify Mail::fake() received it but PushService didn't
});
```

- [ ] **Step 2: Implement the Service**
```php
namespace App\Services;
use App\Models\User;
use App\Models\Notification;

class NotificationService {
    public function notify(User $user, string $category, string $title, string $message, array $data = []) {
        // 1. Create DB Notification (In-App)
        Notification::create([...]);

        // 2. Check Preferences
        $prefs = $user->notificationPreferences()->where('category', $category)->first();
        
        if ($prefs?->email) {
            // Dispatch Mail
        }
        if ($prefs?->push) {
            // Dispatch Push
        }
    }
}
```

- [ ] **Step 3: Verify tests pass**
Run: `php artisan test tests/Unit/NotificationServiceTest.php`

- [ ] **Step 4: Commit**
```bash
git add app/Services/NotificationService.php tests/Unit/NotificationServiceTest.php
git commit -m "feat: implement NotificationService router"
```

### Task 3: Automated Reminder Command

**Files:**
- Create: `app/Console/Commands/SendBorrowingReminders.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/BorrowingRemindersTest.php`

- [ ] **Step 1: Create the Command**
Run: `php artisan make:command SendBorrowingReminders`

- [ ] **Step 2: Implement Logic**
Scan `borrow_transactions` where `status = borrowed` and `due_date` is today + 3 days (Reminder) or `due_date < now()` (Overdue).

- [ ] **Step 3: Schedule it**
```php
// routes/console.php
Schedule::command('library:send-reminders')->dailyAt('08:00');
```

- [ ] **Step 4: Commit**
```bash
git add app/Console/Commands/SendBorrowingReminders.php routes/console.php
git commit -m "feat: add automated daily borrowing reminders"
```
