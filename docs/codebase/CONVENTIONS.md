# Coding Conventions

## Core Sections (Required)

### 1) Naming Rules

| Item | Rule | Example | Evidence |
|------|------|---------|----------|
| Files/classes | PascalCase, one class per file | `AiService.php`, `LibrarianStatusService.php` | `app/` tree |
| Functions/methods | camelCase, descriptive | `hasAdminAccess`, `saveBatchAssignment` | `app/Services/`, `app/Livewire/` |
| Models | Singular TitleCase | `AcademicPaper`, `BorrowTransaction` | `app/Models/` |
| Tables | snake_case plural (Laravel default) | `librarians`, `rule_regulations` | `database/migrations/` |
| Env vars | UPPER_SNAKE | `SIDECAR_TOKEN`, `AI_CORPUS_PATH` | `.env.example` |
| ADR files | `NNNN-kebab-title.md` | `0014-agentic-search-loop.md` | `docs/adr/` |
| Commits | Conventional commits with phase scope | `fix(11-03): ...`, `style(ci): ...` | git log |

### 2) Formatting and Linting

- Formatter: Laravel Pint (PSR-12); config defaults + `pint.json` if present
- Linter: PHPStan + Larastan (level 5) with `phpstan-baseline.neon` (202 baseline entries)
- Enforced rules: PSR-12 style, `no_extra_blank_lines`, `ordered_imports` (observed in CI fixes)
- Run commands:
```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
```

### 3) Import and Module Conventions

- Import grouping: `use` statements ordered (Pint `ordered_imports`), framework imports before app imports
- No aliasing beyond what Laravel provides (`Tests\`, `App\` PSR-4 roots)
- `composer.json` autoload: `App\` → `app/`, `Database\Factories\`, `Database\Seeders\`; dev: `Tests\` → `tests/`

### 4) Error and Logging Conventions

- Services throw exceptions; Livewire components catch and surface errors via `$this->error(...)` (validation-style errors) — e.g., `AdminAssignLibrarians::saveBatchAssignment`
- `LibrarianStatusService` throws `RuntimeException` when required roles are missing
- Logging: standard Laravel channels; **PII redaction enforced globally** via `PiiSanitizerProcessor` on the log stack (v1.3)
- AI sidecar failures: `AiService` logs failures and throws on non-OK responses; truncation mid-stream throws (fail-closed)

### 5) Testing Conventions

- Test file naming/location: `tests/Unit/` and `tests/Feature/` (subdirs `Auth/`, `Livewire/`, `Security/`), class names `*Test.php`; test methods `#[Test]` attributes or `/** @test */` docblocks (both styles present)
- DB isolation: `RefreshDatabase` with SQLite `:memory:` (`phpunit.xml`); `BCRYPT_ROUNDS=4`, `QR_HMAC_SECRET` test value
- Mocking: Mockery + factory-based fixtures
- Coverage: no threshold configured; xdebug coverage removed from CI (was >20 min on runner); `coverage.xml` no longer produced [TODO — restore via pcov if coverage gate is desired]
- Weekday-dependent tests must guard (e.g., `LibrarianBatchTest` skips Sundays — business rule forbids Sunday duty)

### 6) Evidence

- `phpstan.neon`, `phpstan-baseline.neon`
- `phpunit.xml`
- `app/Services/LibrarianStatusService.php`, `app/Logging/PiiSanitizerProcessor.php`
- git log (commit style), `docs/adr/` (decision style)

## Extended Sections (Optional)

### Repo conventions

- Domain terminology enforced via `CONTEXT.md` glossary (Assistant, Conversation, Citation, Grounding, Sidecar, Availability, Similar books — with explicit "Avoid" words)
- New AI features crystallize as ADRs before implementation (0013/0014 pattern from `.planning/phases/11-*`)
- CI gates: Pint, PHPStan (level 5 + baseline), migrations, PHPUnit, SonarCloud, CodeQL, secrets scan — see `docs/codebase/TESTING.md`
- Larastan 3.10 + Laravel 13 cannot resolve Eloquent relations (systematic false positive) — the accepted mechanism is docblock `@property-read` annotations for new code, baseline regeneration as fallback
