# Codebase Structure

## Core Sections (Required)

### 1) Top-Level Map

| Path | Purpose | Evidence |
|------|---------|----------|
| `app/` | Application code (see Module Boundaries) | scan output, `composer.json` autoload |
| `app/Console/Commands/` | 16 artisan commands (AI export/sync/reconcile, librarian batches, overdue transactions, VAPID keys, notifications) | `app/Console/Commands/*.php` |
| `app/Services/` | Domain services: `AiService` (sidecar SSE client), `AvailabilityService` (live copy counts), `SimilarPapersService` (title-as-query), `BorrowService`, `AttendanceService`, `LibrarianStatusService`, `NotificationService`, `CorpusExporter` | `app/Services/` |
| `app/Livewire/` | Livewire v4 pages/components (Admin, Student areas, chat widget) | `app/Livewire/` |
| `app/Models/` | 22 Eloquent models (`User`, `Role`, `Librarian`, `AcademicPaper`, `Conversation`, `Message`, `RuleHeader`, `RuleRegulation`, `Inventory`, `BorrowTransaction`, …) | `app/Models/` |
| `app/Http/` | Controllers + Middleware (`AdminOnly`, `LibrarianOrAdmin`, `CheckAccountStatus`, `CheckCreditScore`) | `app/Http/` |
| `app/Jobs/` | Queued jobs: `AiIndexRebuildJob`, `AiIndexRebuildImmediateJob`, `SendPushNotificationJob` | `app/Jobs/` |
| `app/Observers/` | Eloquent observers that trigger AI index rebuilds (`AcademicPaperObserver`, `RulebookObserver`, `PeopleNameObserver`) | `app/Observers/` |
| `app/Logging/` | `PiiSanitizerProcessor` — global log PII redaction | `app/Logging/` |
| `routes/` | `web.php`, `auth.php`, `console.php` (schedule definitions) | `routes/` |
| `database/` | Migrations, factories, seeders | `database/` |
| `tests/` | PHPUnit suites: `Unit` (9 files), `Feature` (85 files incl. `Auth/`, `Livewire/`, `Security/` subdirs), `fixtures/`, `Traits/` | `tests/` |
| `resources/views/` | Blade + Livewire views (incl. `livewire/pages/admin/admin-assign-librarians.blade.php` etc.) | `resources/views/` |
| `public/build/` | Vite build output (`manifest.json` required by `@vite` at render time) | `public/build/` |
| `storage/app/ai-corpus/` | Exported corpus JSON (`catalog.json`, `policies.json`) consumed by the sidecar | `app/Console/Commands/ExportAiCorpus.php` |
| `.github/` | Workflows (`ci.yml`, `codeql.yml`, `sonar-secrets.yml`), `actions/setup-laravel` composite action | `.github/` |
| `docs/` | `adr/` (14 decision records), `agents/`, `codebase/` (this set), `superpowers/` (plans/specs) | `docs/` |
| `.planning/` | GSD planning artifacts per phase (PROJECT.md, ROADMAP.md, REQUIREMENTS.md, phases/8-11) | `.planning/` |
| `CONTEXT.md` | Domain glossary (Assistant, Conversation, Citation, Grounding, Sidecar, Availability, Similar books) | `CONTEXT.md` |

### 2) Entry Points

- Main runtime entry: `public/index.php` (standard Laravel front controller)
- Worker/CLI entries: `artisan` commands; queued jobs (database queue); scheduler defined in `routes/console.php`
- How entry is selected: `php artisan serve` / web server → `public/index.php`; `php artisan schedule:run` executes `routes/console.php` schedules

### 3) Module Boundaries

| Boundary | What belongs here | What must not be here |
|----------|-------------------|------------------------|
| `app/Services/` | Domain orchestration (borrow, attendance, librarian batches, availability, similar books, AI SSE client) | DB queries spread across Livewire components; HTTP calls inline in views |
| `app/Livewire/` | UI state + interaction; thin calls into Services | Business rules duplicated from Services |
| `app/Models/` | Eloquent definitions, relations, scopes, model events (`booted`), casts | HTTP/SSE logic |
| `app/Http/Middleware/` | Request auth/role gates (`AdminOnly`, `LibrarianOrAdmin`, `CheckAccountStatus`) | Domain decisions |
| `app/Console/Commands/` | Scheduled/CLI automation (corpus export, index sync, overdue checks, batch statuses) | One-off test code |
| `app/Jobs/` | Queued side effects (index rebuilds, push notifications) | Synchronous user-request work that must be fast |

### 4) Naming and Organization Rules

- File naming: PHP classes PascalCase (one per file), matching class name (`AiService.php`)
- Directory organization: by layer (`Services/`, `Models/`, `Jobs/`, `Observers/`) with sub-organization in `Livewire/Pages/<Area>/`
- Tests mirror app layout: `tests/Feature/` ↔ feature behavior, `tests/Unit/` for isolated logic
- ADRs: `docs/adr/NNNN-kebab-title.md`

### 5) Evidence

- `.codebase-scan.txt` (directory tree section)
- `composer.json` (PSR-4 autoload)
- `routes/console.php` (scheduler)
- `app/Services/AiService.php`, `app/Console/Commands/ExportAiCorpus.php`
