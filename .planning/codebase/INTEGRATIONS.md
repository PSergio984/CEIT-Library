# External Integrations

**Analysis Date:** 2025-02-28

## APIs & External Services

**QR Services:**
- Simple-QRCode - Backend QR code generation.
  - SDK: `simplesoftwareio/simple-qrcode`
- HTML5-QRCode / jsQR - Frontend QR code scanning.
  - SDK: `html5-qrcode`, `jsqr` (NPM)

**Asset Management:**
- Blade FontAwesome - Icon integration.
  - SDK: `owenvoke/blade-fontawesome`

## Data Storage

**Databases:**
- MySQL - Primary production database (`DB_CONNECTION=mysql`).
- SQLite - Default development/testing database (`database/database.sqlite`).
- Client: Eloquent ORM.

**File Storage:**
- Local filesystem (`storage/app/public`).
- AWS S3 - Support present in config, but not actively configured in `.env.example`.
  - SDK: `league/commonmark` (indirectly via framework), AWS placeholders in `.env`.

**Caching:**
- Database - Default cache driver.
- Redis - Supported but optional.

## Authentication & Identity

**Auth Provider:**
- Custom (Laravel Breeze)
  - Implementation: Session-based authentication using `App\Models\User`.
  - Authorization: Custom Gates defined in `app/Providers/AppServiceProvider.php`.

## Monitoring & Observability

**Error Tracking:**
- Laravel Pail - CLI-based log tailing.
- Laravel Debugbar - Web-based debugging (dev only).

**Logs:**
- Local logs stored in `storage/logs/laravel.log`.
- `LOG_CHANNEL=stack`.

## CI/CD & Deployment

**Hosting:**
- Docker-ready via `Dockerfile` (based on `richarvey/nginx-php-fpm`).

**CI Pipeline:**
- GitHub Actions - Workflows present in `.github/workflows/` (laravel.yml, codeql.yml, qodana_code_quality.yml).

## Environment Configuration

**Required env vars:**
- `APP_KEY` - Application encryption key.
- `QR_HMAC_SECRET` - Secret for QR code verification.
- `DB_CONNECTION`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

**Secrets location:**
- Stored in `.env` (not committed).
- Placeholders provided in `.env.example`.

## Webhooks & Callbacks

**Incoming:**
- None detected.

**Outgoing:**
- None detected.

---

*Integration audit: 2025-02-28*
