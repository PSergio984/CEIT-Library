# Notification Preferences Database Schema Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish the database schema and model relationships for user notification preferences across different channels (Email, Push, In-App).

**Architecture:** A one-to-many relationship between `User` and `NotificationPreference`. Each preference record represents a specific category of notifications for a user, with flags for each delivery channel.

**Tech Stack:** Laravel, Eloquent, PHPUnit

---

### Task 1: Foundation and Verification (TDD)

**Files:**
- Create: `tests/Feature/NotificationPreferenceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Database\QueryException;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_user_can_have_notification_preferences()
    {
        $user = User::factory()->create();

        $preference = NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'reminders',
            'email' => true,
            'push' => true,
            'in_app' => true,
        ]);

        $this->assertCount(1, $user->notificationPreferences);
        $this->assertEquals('reminders', $user->notificationPreferences->first()->category);
        $this->assertTrue((bool) $user->notificationPreferences->first()->email);
    }

    /** @test */
    public function notification_preferences_must_be_unique_per_user_and_category()
    {
        $user = User::factory()->create();

        NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'reminders',
            'email' => true,
        ]);

        $this->expectException(QueryException::class);

        NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'reminders',
            'email' => false,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/NotificationPreferenceTest.php`
Expected: FAIL (Class "App\Models\NotificationPreference" not found)

### Task 2: Database Schema

**Files:**
- Create: `database/migrations/2026_06_15_000001_create_notification_preferences_table.php`

- [ ] **Step 1: Create the migration file**

Run: `php artisan make:migration create_notification_preferences_table --create=notification_preferences --no-interaction`

- [ ] **Step 2: Define the schema**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
```

- [ ] **Step 3: Run migration**

Run: `php artisan migrate`
Expected: Migration successful.

### Task 3: Models and Relationships

**Files:**
- Create: `app/Models/NotificationPreference.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Create the NotificationPreference model**

Run: `php artisan make:model NotificationPreference --no-interaction`

- [ ] **Step 2: Configure the NotificationPreference model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'email',
        'push',
        'in_app',
    ];

    /**
     * Get the user that owns the preference.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 3: Add relationship to User model**

In `app/Models/User.php`, add the `notificationPreferences` method:

```php
    /**
     * Get the notification preferences for the user.
     */
    public function notificationPreferences(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }
```

### Task 4: Final Verification and Cleanup

- [ ] **Step 1: Run tests**

Run: `php artisan test tests/Feature/NotificationPreferenceTest.php`
Expected: PASS

- [ ] **Step 2: Run Pint**

Run: `./vendor/bin/pint --dirty`

- [ ] **Step 3: Commit changes**

```bash
git add database/migrations/2026_06_15_000001_create_notification_preferences_table.php app/Models/NotificationPreference.php app/Models/User.php tests/Feature/NotificationPreferenceTest.php
git commit -m "feat: add notification preferences table and model"
```
