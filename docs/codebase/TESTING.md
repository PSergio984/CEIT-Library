# TESTING.md

## Frameworks
- **Primary**: PHPUnit 11.
- **Style**: PHPUnit classes (no Pest detected in use).

## Organization
- `tests/Feature/`: High-level feature tests (Auth, Livewire, CRUD).
- `tests/Unit/`: Isolated model/logic tests.
- `tests/Traits/`: Reusable test helpers (e.g., `TestHelper.php`).

## Mocking
- Uses standard Mockery (required in `composer.json`) and Laravel's built-in mocking.

## Evidence
- `composer.json`
- `tests/` directory structure
- `tests/Feature/QrScannerTest.php` (Assumed based on `ls -R`)
ttp/Middleware/CheckCreditScore.php`
- `app/Livewire/QrScanner.php`
