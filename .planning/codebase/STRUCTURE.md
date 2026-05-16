# Codebase Structure

**Analysis Date:** 2025-05-16

## Directory Layout

```
[project-root]/
├── app/                # Core application logic
│   ├── Console/        # CLI commands
│   ├── Http/           # Controllers and Middleware
│   ├── Livewire/       # Livewire component classes (Page logic)
│   ├── Models/         # Eloquent models
│   ├── Notifications/  # System notifications
│   ├── Providers/      # Service providers
│   ├── Rules/          # Custom validation rules
│   ├── Traits/         # Shared logic (e.g. QR encryption)
│   └── View/           # View components
├── bootstrap/          # Application bootstrap and config registration
├── config/             # Configuration files
├── database/           # Migrations, Seeders, and Factories
├── documentations/     # System guides and technical docs
├── public/             # Public assets (CSS, JS, images, index.php)
├── resources/          # Frontend assets and templates
│   ├── css/            # Tailwind CSS source
│   ├── js/             # JavaScript source
│   └── views/          # Blade templates and Volt components
├── routes/             # Route definitions (web, api, console)
├── storage/            # Logs, cache, and uploaded files
└── tests/              # PHPUnit and Livewire tests
```

## Directory Purposes

**app/Livewire/Pages:**
- Purpose: Contains the main logic for every interactive page in the application.
- Contains: Component classes for Admin and Student pages.
- Key files: `AdminDashboard.php`, `AcademicPaperIndex.php`.

**app/Livewire/Forms:**
- Purpose: Encapsulates validation and state for forms.
- Contains: Livewire Form objects.
- Key files: `AcademicPaperForm.php`, `ViolationForm.php`.

**resources/views/livewire:**
- Purpose: Contains the Blade templates for Livewire components and single-file Volt components.
- Contains: `.blade.php` files.
- Key files: `qr-scanner.blade.php`, `pages/Admin/admin-dashboard.blade.php`.

**app/Models:**
- Purpose: Defines the data structure and relationships.
- Contains: Eloquent model classes.
- Key files: `User.php`, `AcademicPaper.php`, `BorrowTransaction.php`.

## Key File Locations

**Entry Points:**
- `routes/web.php`: Primary web route definitions.
- `public/index.php`: The main entry point for all requests.

**Configuration:**
- `bootstrap/app.php`: Registers middleware, exception handling, and routing.
- `config/app.php`: Global application configuration.
- `config/auth.php`: Authentication and role guards configuration.

**Core Logic:**
- `app/Livewire/QrScanner.php`: Main QR scanning logic.
- `app/Traits/CreatesQrCanonicalMessage.php`: Shared logic for QR data integrity.

**Testing:**
- `tests/Feature`: Integration and feature tests for components.
- `tests/Unit`: Low-level unit tests.

## Naming Conventions

**Files:**
- Livewire components: `CamelCase.php` (e.g., `AdminUserList.php`)
- Blade views: `kebab-case.blade.php` (e.g., `admin-user-list.blade.php`)
- Models: `CamelCase.php` singular (e.g., `AcademicPaper.php`)

**Directories:**
- PSR-4 namespaces for PHP classes (e.g., `app/Livewire/Pages/Admin/`).
- Feature-based grouping in `resources/views/livewire/` (e.g., `pages/admin/`).

## Where to Add New Code

**New Feature (Page):**
- Implementation: Create a Livewire component in `app/Livewire/Pages/` and a corresponding view in `resources/views/livewire/pages/`.
- Routes: Register the component in `routes/web.php`.
- Tests: Create a test in `tests/Feature/`.

**New Component (Reusable UI):**
- Implementation: Use Volt single-file components in `resources/views/livewire/components/` or standard Blade components in `resources/views/components/`.

**New Business Rule:**
- Implementation: Add to the relevant `Model`, `Form`, or create a custom `Rule` in `app/Rules/`.

**Utilities:**
- Shared helpers: Add to a Trait in `app/Traits/` if related to model/component logic, or a custom class in `app/Services/` (if created).

## Special Directories

**graphify-out/:**
- Purpose: Contains the knowledge graph for the codebase.
- Generated: Yes.
- Committed: Yes (for agent context).

**.planning/:**
- Purpose: Contains implementation plans and codebase mapping.
- Generated: Yes.
- Committed: Yes.

---

*Structure analysis: 2025-05-16*
