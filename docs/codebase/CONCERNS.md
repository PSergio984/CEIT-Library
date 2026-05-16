# CONCERNS.md

## Technical Debt
- **Volt Ambiguity**: `composer.json` includes `livewire/volt`, but no `.blade.php` files using `@volt` or functional patterns were found in standard locations.
- **Redundant Seeders**: Both `DatabaseSeeder.php` and `accountSeeder.php` contain super-admin creation logic.

## Security
- **QR Tampering**: `QrScanner.php` implements HMAC verification, which is good, but relies on a permanent QR code (no replay prevention via nonce/timestamp for current version).

## Performance
- **Large Components**: Some Livewire components (e.g., `AdminAcademicPaperIndex.php`) are very large (>40KB), which may lead to maintenance issues.

## Evidence
- `app/Livewire/QrScanner.php`
- `database/seeders/`
- `ls -R` outputs
