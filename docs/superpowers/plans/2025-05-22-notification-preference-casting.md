# Notification Preference Boolean Casting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add boolean casts to `NotificationPreference` model fields to ensure they are consistently returned as booleans.

**Architecture:** Utilize Laravel's `casts()` method on the Eloquent model to define attribute casting.

**Tech Stack:** PHP 8.2, Laravel 11/13

---

### Task 1: Add regression test for casting

**Files:**
- Create: `tests/Unit/Models/NotificationPreferenceCastingTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceCastingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_casts_notification_fields_to_boolean()
    {
        $user = User::factory()->create();
        
        $preference = NotificationPreference::create([
            'user_id' => $user->id,
            'category' => 'test',
            'email' => 1,
            'push' => 0,
            'in_app' => 1,
        ]);

        $freshPreference = $preference->fresh();

        $this->assertIsBool($freshPreference->email, 'Email should be a boolean');
        $this->assertIsBool($freshPreference->push, 'Push should be a boolean');
        $this->assertIsBool($freshPreference->in_app, 'In-app should be a boolean');
        
        $this->assertTrue($freshPreference->email);
        $this->assertFalse($freshPreference->push);
        $this->assertTrue($freshPreference->in_app);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/NotificationPreferenceCastingTest.php`
Expected: FAIL (attributes will likely be returned as integers or strings depending on DB driver)

- [ ] **Step 3: Commit regression test**

```bash
git add tests/Unit/Models/NotificationPreferenceCastingTest.php
git commit -m "test: add regression test for NotificationPreference boolean casting"
```

### Task 2: Implement boolean casts

**Files:**
- Modify: `app/Models/NotificationPreference.php`

- [ ] **Step 1: Add casts() method**

```php
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email' => 'boolean',
            'push' => 'boolean',
            'in_app' => 'boolean',
        ];
    }
```

- [ ] **Step 2: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/NotificationPreferenceCastingTest.php`
Expected: PASS

- [ ] **Step 3: Run existing tests**

Run: `php artisan test tests/Feature/NotificationPreferenceTest.php`
Expected: PASS

- [ ] **Step 4: Lint code**

Run: `vendor/bin/pint --dirty`

- [ ] **Step 5: Commit fix**

```bash
git add app/Models/NotificationPreference.php
git commit -m "feat: add boolean casts to NotificationPreference model"
```
