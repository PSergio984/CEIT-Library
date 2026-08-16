# Testing Patterns

## Core Sections (Required)

### 1) Test Stack and Commands

- Primary test framework: PHPUnit ^13.0, run through `php artisan test`
- Assertion/mocking tools: PHPUnit assertions, Mockery, Laravel factories; Livewire test helpers (`Livewire::test(...)`)
- Commands:

```bash
php artisan test                # full suite (Unit + Feature)
php artisan test --filter=ExportAiCorpusTest
php artisan test tests/Unit
php artisan test tests/Feature
```

### 2) Test Layout

- Test file placement: `tests/Unit/` (9 files) and `tests/Feature/` (85 files), with `Auth/`, `Livewire/`, `Security/` subdirectories; shared fixtures in `tests/fixtures/`, helpers in `tests/Traits/`
- Naming convention: `*Test.php` classes; methods use `#[Test]` attribute or `/** @test */` docblock (both in use)
- Setup files: `phpunit.xml` (sqlite `:memory:`, `BCRYPT_ROUNDS=4`, `QR_HMAC_SECRET`, `PULSE_ENABLED=false`, `TELESCOPE_ENABLED=false`, `NIGHTWATCH_ENABLED=false`); `tests/TestCase.php` base

### 3) Test Scope Matrix

| Scope | Covered? | Typical target | Notes |
|-------|----------|----------------|-------|
| Unit | yes (small) | models, services, helpers | `tests/Unit/` (9 files) |
| Feature | yes (bulk) | auth, borrowing, librarian batches, QR flows, Livewire components, AI chat/citations, security audits | `tests/Feature/` (85 files) |
| E2E | no | browser automation | not present; Livewire component tests approximate UI flows |
| Golden-set retrieval evals | separate | hybrid search quality | sidecar `app/eval.py` + `data/golden_dataset.json` (35 cases) — see sidecar `docs/codebase/TESTING.md` |

### 4) Mocking and Isolation Strategy

- Main mocking approach: Mockery mocks; Livewire `Livewire::test()` with `assertSet`/`assertHasNoErrors`/`assertDatabaseHas`; factories for seeded data
- Isolation guarantees: `RefreshDatabase` per test class; in-memory sqlite per run; deterministic env in `phpunit.xml`
- DB-in-paths: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` (env + CI); local `.env` also sqlite
- Common failure modes: (1) views rendering `@vite` need a built manifest (`npm ci && npm run build` before tests — CI does this); (2) weekday-dependent tests (Sundays forbidden for librarian duty — `LibrarianBatchTest` skips); (3) env var `AI_CORPUS_PATH=` empty-string overriding the storage default — fixed in `config/services.php` (`env('AI_CORPUS_PATH') ?: storage_path(...)`)

### 5) Coverage and Quality Signals

- Coverage tool: xdebug clover — **removed from CI** (ran >20 min on 2-core runner; suite is ~85s without it). `coverage.xml` no longer generated or consumed by SonarCloud. [TODO — if a coverage gate is wanted, use pcov, not xdebug]
- Current reported coverage: none in CI [TODO]
- Known gaps/flaky areas: weekday-dependent `LibrarianBatchTest` (skips Sundays); sidecar live chat test (`tests/Feature/SidecarLiveTest.php` on Laravel side / `test_chat_stream_live.py` on sidecar) gated by env — never in CI

### 6) Evidence

- `phpunit.xml`
- `.github/workflows/ci.yml` (test job: setup-laravel → .env → key:generate → npm ci/build → `php artisan test`)
- `tests/Feature/LibrarianBatchTest.php`, `tests/Feature/ExportAiCorpusTest.php`
- `tests/Feature/SidecarLiveTest.php` (env-gated live smoke)

## Extended Sections (Optional)

### CI pipeline (GitHub Actions)

- `ci.yml`: lint (Pint) → typecheck (PHPStan level 5 + baseline) → migration sanity → test (phpunit, ~85s + npm build) → SonarCloud quality gate (needs test) → Docker build (needs lint/typecheck/migrations/test)
- `codeql.yml`: CodeQL Advanced on push (weekly schedule)
- `sonar-secrets.yml`: SonarQube CLI secrets scan (offline, every push)
- Local parity: suite passes 600 + 3 skipped (Sunday) after CI fixes; ruff/sidecar see sidecar docs
