# Technology Stack

**Analysis Date:** 2025-02-28

## Languages

**Primary:**
- PHP 8.2+ - Core application logic, controllers, models, and Livewire components.

**Secondary:**
- JavaScript (ES6+) - Frontend interactivity, QR code scanning logic.
- Blade - Templating engine for frontend views.
- CSS (Tailwind) - Styling and layout.

## Runtime

**Environment:**
- PHP 8.2 (FPM/CLI)
- Node.js 20+ (Build-time only for assets)

**Package Manager:**
- Composer 2.x - PHP dependency management.
- NPM 10.x - JavaScript dependency management.
- Lockfile: `composer.lock` and `package-lock.json` are both present.

## Frameworks

**Core:**
- Laravel 12.x - Backend framework.
- Livewire 3.6+ - Full-stack framework for dynamic interfaces.
- Volt 1.7+ - Functional API for Livewire components.
- Laravel Breeze 2.3+ - Authentication scaffolding.

**Testing:**
- PHPUnit 11.5+ - Unit and Feature testing.
- Laravel Boost 1.8+ - Development and testing utilities.

**Build/Dev:**
- Vite 7.0+ - Frontend asset bundling.
- Tailwind CSS 4.1+ - CSS framework (Vite plugin).
- Laravel Sail 1.41+ - Docker-based development environment.
- Laravel Pint 1.24+ - PHP code style fixer.
- Laravel Pail 1.2+ - CLI log viewer.

## Key Dependencies

**Critical:**
- `robsontenorio/mary` 2.4+ - UI component library for Laravel Livewire.
- `daisyui` 5.1+ - Tailwind CSS component library.

**Infrastructure:**
- `barryvdh/laravel-dompdf` 3.1+ - PDF generation.
- `simplesoftwareio/simple-qrcode` 4.2+ - QR code generation (Backend).
- `html5-qrcode` 2.3+ - QR code scanning (Frontend).
- `owenvoke/blade-fontawesome` 2.9+ - FontAwesome icon integration.

## Configuration

**Environment:**
- Configured via `.env` file.
- Key configs: `APP_KEY`, `DB_CONNECTION`, `QR_HMAC_SECRET`.

**Build:**
- `vite.config.js` - Vite configuration.
- `composer.json` - PHP dependencies and scripts.
- `package.json` - JS dependencies and scripts.

## Platform Requirements

**Development:**
- PHP 8.2+
- Composer
- Node.js & NPM
- Docker (optional, via Sail)
- Laravel Herd (recommended for macOS/Windows)

**Production:**
- PHP 8.2+ FPM
- Nginx
- MySQL/SQLite
- Docker (supported via `Dockerfile`)

---

*Stack analysis: 2025-02-28*
