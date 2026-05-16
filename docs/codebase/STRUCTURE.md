# STRUCTURE.md

## Directory Layout
- `app/`: Core application logic
    - `Http/`: Controllers and Middleware
    - `Livewire/`: Class-based Livewire components (Pages, Forms, Actions)
    - `Models/`: Eloquent models
    - `Providers/`: Service Providers
- `bootstrap/`: Application bootstrapping (middleware/exception registration)
- `config/`: Configuration files
- `database/`: Migrations, factories, and seeders
- `resources/`: Assets and views
    - `views/livewire/`: Blade templates for Livewire components
- `routes/`: Web and console route definitions
- `tests/`: Feature and Unit tests

## Key Files
- `bootstrap/app.php`: Entry point for middleware and routing configuration.
- `routes/web.php`: Primary web route definitions.
- `app/Models/User.php`: Main user model with role/permission logic.

## Evidence
- `docs/codebase/.codebase-scan.txt`
- `ls -R` outputs in session
