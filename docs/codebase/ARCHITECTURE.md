# ARCHITECTURE.md

## Layers & Patterns
- **Presentation**: Livewire 3 (Class-based components).
- **Domain**: Eloquent Models in `app/Models/` containing business logic (e.g., `User`, `Role`, `Librarian`).
- **Authorization**: Laravel Gates defined in `AppServiceProvider` and custom Middleware (`AdminOnly`, `LibrarianOrAdmin`).
- **Data Flow**: Reactive updates via Livewire; transactions handled in components or models (e.g., `QrScanner.php` uses `DB::transaction`).

## Key Patterns
- **Role-Based Access Control (RBAC)**: Roles defined in `roles` table; checked via methods on `User` model.
- **Batch Duty System**: Temporary "Librarian" roles assigned via `librarians` table with expiration.
- **Service Injection**: PHP 8 constructor property promotion in providers/services (where applicable).

## Evidence
- `app/Models/User.php`
- `app/Providers/AppServiceProvider.php`
- `bootstrap/app.php`
- `app/Livewire/QrScanner.php`
