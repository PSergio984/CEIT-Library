# Testing Patterns

**Analysis Date:** 2025-05-14

## Test Framework

**Runner:**
- PHPUnit 11.5.3
- Config: `phpunit.xml`

**Assertion Library:**
- PHPUnit assertions
- Laravel testing helpers (e.g., `$this->assertDatabaseHas`)
- Livewire testing helpers (e.g., `->assertHasNoErrors`)

**Run Commands:**
```bash
php artisan test                                      # Run all tests
php artisan test --filter=test_method_name            # Run specific test
php artisan test tests/Feature/AcademicPapersTest.php # Run specific file
```

## Test File Organization

**Location:**
- Separate: `tests/Feature` and `tests/Unit`.

**Naming:**
- `*Test.php` (e.g., `AcademicPaperFormTest.php`)

**Structure:**
```
tests/
├── Feature/
│   ├── Auth/
│   ├── Livewire/
│   └── [FeatureName]Test.php
├── Unit/
│   └── [ModelName]Test.php
└── Traits/
    └── CreatesTestDatabase.php
```

## Test Structure

**Suite Organization:**
```php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Custom setup
    }

    #[Test]
    public function specific_behavior_description()
    {
        // Act
        // Assert
    }
}
```

**Patterns:**
- **Setup pattern:** Overriding `setUp()` to initialize data, flush cache, and create acting users.
- **Teardown pattern:** Standard PHPUnit/Laravel teardown (RefreshDatabase handled automatically).
- **Assertion pattern:** Database assertions combined with state assertions (e.g., checking counts after actions).

## Mocking

**Framework:** Mockery (included in `composer.json`).

**Patterns:**
- Used for external services or complex dependencies.
- Laravel's Facade mocking is preferred (e.g., `Cache::shouldReceive('flush')`).

**What to Mock:**
- Time-sensitive operations (if not using `Carbon::setTestNow`).
- External API calls.
- Cache/Session if they interfere with test isolation.

**What NOT to Mock:**
- Database (use `RefreshDatabase` and SQLite in-memory/file).
- Models (test real model logic).

## Fixtures and Factories

**Test Data:**
```php
$user = User::factory()->create(['role_id' => 3]);
$paper = AcademicPaper::factory()->create();
```

**Location:**
- `database/factories/` contains all model factories.

## Coverage

**Requirements:** None explicitly enforced in `phpunit.xml`, but comprehensive testing of happy/failure paths is required.

**View Coverage:**
```bash
php artisan test --coverage
```

## Test Types

**Unit Tests:**
- Focus on single model methods or business logic in isolation.
- Located in `tests/Unit`.

**Integration Tests:**
- Test interactions between models, controllers, and Livewire components.
- Located in `tests/Feature`.

**E2E Tests:**
- Handled via Livewire component testing which simulates user interaction.

## Common Patterns

**Async Testing:**
- Not applicable (mostly synchronous PHP).

**Error Testing:**
```php
Livewire::test(AdminAcademicPaperIndex::class)
    ->set('form.title', '')
    ->call('saveAcademicPaper')
    ->assertHasErrors(['form.title']);
```

**Custom Database Creation:**
The project uses a custom trait `Tests\Traits\CreatesTestDatabase` to manually create schema in tests, bypassing standard migrations. This is likely due to SQLite compatibility or performance.

---

*Testing analysis: 2025-05-14*
