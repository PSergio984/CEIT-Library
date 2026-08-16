# Technology Stack

## Core Sections (Required)

### 1) Runtime Summary

| Area | Value | Evidence |
|------|-------|----------|
| Primary language | PHP (backend), JavaScript (Vite/Tailwind frontend build) | `composer.json`, `package.json` |
| Runtime + version | PHP ^8.4 (CI pins 8.4 via `shivammathur/setup-php`) | `composer.json`, `.github/actions/setup-laravel/action.yml` |
| Package manager | Composer (PHP), npm (frontend) | `composer.json`, `package-lock.json` |
| Module/build system | Composer PSR-4 autoload; Vite for frontend assets | `composer.json` autoload, `vite.config.js` |

### 2) Production Frameworks and Dependencies

| Dependency | Version | Role in system | Evidence |
|------------|---------|----------------|----------|
| laravel/framework | ^13.0 | Web framework, Eloquent ORM, scheduler, queues | `composer.json` |
| livewire/livewire | ^4.0 | Interactive components (pages, chat widget, modals) | `composer.json` |
| livewire/volt | ^1.7 | Functional Livewire components | `composer.json` |
| robsontenorio/mary | ^2.0 | UI component kit (Mary UI) | `composer.json` |
| owenvoke/blade-fontawesome | ^3.0 | Icons | `composer.json` |
| simplesoftwareio/simple-qrcode | ^4.2 | QR code generation (borrow/return flows) | `composer.json` |
| minishlink/web-push | ^11.0 | Web Push notifications (VAPID) | `composer.json` |
| barryvdh/laravel-dompdf | ^3.1 | PDF exports (analytics/reporting) | `composer.json` |
| laravel/octane | ^2.17 | App server option (installed; runtime use unverified) | `composer.json` |
| fakerphp/faker | ^1.23 | Test/seed data | `composer.json` |
| Database | SQLite (default `DB_CONNECTION=sqlite`; CI uses `:memory:`) | `composer.json` has no DB driver package; `.env.example`, `phpunit.xml` |
| Frontend | Vite + Tailwind (v4 per ROADMAP) + daisyUI (v5 per ROADMAP) + Mary UI | `package.json`, `.planning/ROADMAP.md` |
| AI sidecar | FastAPI service at `http://127.0.0.1:8310` (separate repo `ceit-ai-sidecar`) | `.env.example` (`SIDECAR_URL`), `app/Services/AiService.php` |

### 3) Development Toolchain

| Tool | Purpose | Evidence |
|------|---------|----------|
| Laravel Pint | Formatting (PSR-12) — enforced in CI (`vendor/bin/pint --test`) | `.github/workflows/ci.yml` |
| PHPStan + Larastan | Static analysis, level 5 + `phpstan-baseline.neon` (202 baseline errors) | `phpstan.neon`, `phpstan-baseline.neon` |
| PHPUnit | Tests (^13.0) via `php artisan test` | `composer.json`, `phpunit.xml` |
| Mockery | Mocking in tests | `composer.json` |
| Laravel Breeze / Sail / Pail / Debugbar / IDE-Helper / Boost | Auth scaffold, Docker dev, logs, debugging | `composer.json` require-dev |
| CodeRabbit / Copilot | AI code review configs | `.coderabbit.yaml`, `.github/copilot-instructions.md` |

### 4) Key Commands

```bash
composer install
npm ci && npm run build          # frontend assets (required before tests that render @vite views)
php artisan migrate --seed       # DB + seed
php artisan serve                # dev server
php artisan test                 # full test suite
vendor/bin/pint --test           # format check
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php artisan ai:export-corpus     # export catalog + policy corpus JSON for the sidecar
php artisan ai:sync-index        # trigger sidecar rebuild
php artisan ai:reconcile-index --repair  # verify + optionally repair index freshness
```

### 5) Environment and Config

- Config sources: `.env` (from `.env.example`), `config/*.php`, `phpunit.xml`, CI job env
- Required env vars: `APP_KEY`, `SIDECAR_URL`, `SIDECAR_TOKEN` (shared with sidecar), `AI_CORPUS_PATH` (empty = default `storage/app/ai-corpus`), `SEED_SUPER_ADMIN_PASSWORD` (when `RUN_DB_SEED=true`)
- Deployment/runtime constraints: the AI sidecar runs on **FastAPI Cloud** (production) or loopback (dev) — `SIDECAR_URL` in `.env` must match; scheduled commands need the scheduler running (`Schedule::command` in `routes/console.php`); CI needs frontend build before tests (Vite manifest).

### 6) Evidence

- `composer.json` / `composer.lock`
- `.env.example`
- `.github/workflows/ci.yml`, `.github/actions/setup-laravel/action.yml`
- `phpunit.xml`, `phpstan.neon`, `package.json`
