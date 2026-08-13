---
phase: 08-hybrid-search-foundation
plan: 04
subsystem: api
tags: [laravel, http-client, sidecar, gateway, exceptions, fixtures]

requires:
  - phase: 08
    provides: "RESEARCH §4.2/§4.3 API contract (endpoints, payloads, timeouts, retries)"
provides:
  - "AiService: sole Laravel HTTP gateway to the sidecar (search/rebuildIndex/health) with X-Sidecar-Token auth and typed exceptions"
  - "ai_sidecar config block + SIDECAR_URL/SIDECAR_TOKEN/AI_CORPUS_PATH env keys"
  - "3 recorded contract fixtures (health/search/rebuild) for deterministic CI"
  - "8 feature tests covering payload, token header, auth/connection/5xx mapping, retry, rebuild, health"
affects: [08-05, 08-06]

tech-stack:
  added: []
  patterns: ["Http facade with per-call fresh client (Octane-safe)", "Typed RuntimeException subclasses per failure class", "Contract fixtures under tests/fixtures/ai-sidecar/"]

key-files:
  created:
    - app/Services/AiService.php
    - app/Exceptions/AiServiceUnavailableException.php
    - app/Exceptions/AiServiceAuthException.php
    - tests/fixtures/ai-sidecar/health.json
    - tests/fixtures/ai-sidecar/search.json
    - tests/fixtures/ai-sidecar/rebuild.json
    - tests/Feature/AiServiceTest.php
  modified:
    - config/services.php
    - .env.example

key-decisions:
  - "Auth header is X-Sidecar-Token (NOT Authorization/Bearer) — the sidecar contract keys on this name"
  - "ConnectionException is NOT swallowed by retry(throw: false) — each method wraps it in try/catch → AiServiceUnavailableException"
  - "401 → AiServiceAuthException; failed()/5xx → AiServiceUnavailableException; /search retry(2,250) idempotent only"

patterns-established:
  - "Never direct sidecar HTTP — always through AiService"
  - "Fixture files carry contract_version v1 so Laravel and sidecar test against the same recorded contract"

requirements-completed: [SEARCH-01, SEARCH-07]

duration: 40min
completed: 2026-08-13
---

# Phase 8 Plan 4: AiService Gateway Summary

**AiService HTTP gateway with X-Sidecar-Token auth, typed failure mapping (auth/connection/5xx), recorded contract fixtures, and 8 passing feature tests — every later Laravel↔sidecar integration goes through it**

## Performance

- **Duration:** 40 min
- **Started:** 2026-08-13T19:20:00+08:00
- **Completed:** 2026-08-13T20:00:00+08:00
- **Tasks:** 3
- **Files modified:** 9 created/modified

## Accomplishments

- `AiService::search/rebuildIndex/health` with locked timeouts (3s connect / 10s search / 120s rebuild / 5s health) and retries.
- Typed exceptions: `AiServiceAuthException` (401, no token in message), `AiServiceUnavailableException` (connection/timeout/5xx).
- `ai_sidecar` config block + 3 env keys in `.env.example` (token placeholder empty, never committed).
- Contract fixtures matching RESEARCH §4.2 shapes exactly, with `contract_version: v1`.
- 8 tests; full suite 498 passed, 2 skipped.

## Task Commits

1. **Task 1: Config + env + exceptions** - `7fca66c2` (feat)
2. **Task 2: AiService gateway** - `c0f3ca2a` (feat)
3. **Task 3: Fixtures + tests** - `ce54a21b` (test)

**Plan metadata:** pending

## Files Created/Modified

- `app/Services/AiService.php` - HTTP gateway, per-call fresh client, X-Sidecar-Token header
- `app/Exceptions/AiService{Unavailable,Auth}Exception.php` - typed failures
- `config/services.php` - ai_sidecar block (base_url/token/corpus_path)
- `.env.example` - SIDECAR_URL/SIDECAR_TOKEN/AI_CORPUS_PATH
- `tests/fixtures/ai-sidecar/{health,search,rebuild}.json` - recorded contract
- `tests/Feature/AiServiceTest.php` - 8 tests

## Decisions Made

- `withToken()` sends `Authorization: Bearer`; the sidecar contract requires `X-Sidecar-Token` — switched to `withHeaders(['X-Sidecar-Token' => ...])`.
- ConnectionException propagates despite `retry(throw: false)` — wrapped per method.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Wrong auth header name**
- **Found during:** Task 3 test execution
- **Issue:** Plan locked `X-Sidecar-Token`; implementation used `withToken()` → `Authorization: Bearer`. Test debug showed header mismatch.
- **Fix:** Replaced with `withHeaders(['X-Sidecar-Token' => config(...)])`.
- **Files modified:** app/Services/AiService.php
- **Verification:** AiServiceTest 8/8 green.
- **Committed in:** c0f3ca2a (Task 2 commit)

**2. [Rule 1 - Bug] ConnectionException not converted**
- **Found during:** Task 3 test execution
- **Issue:** `retry(throw: false)` does not catch ConnectionException thrown from fakes; raw exception escaped instead of `AiServiceUnavailableException`.
- **Fix:** try/catch around each Http call → AiServiceUnavailableException with previous chained.
- **Files modified:** app/Services/AiService.php
- **Verification:** connection-failure test passes.
- **Committed in:** c0f3ca2a (Task 2 commit)

---

**Total deviations:** 2 auto-fixed (2 bugs, Rule 1)
**Impact on plan:** Both necessary for the locked contract. No scope creep.

## Issues Encountered

- `hasHeader(name, value)` compares the raw header value; the "Bearer " prefix only exists with `withToken()`. Fixed test to assert the raw token.

## User Setup Required

None - no external service configuration required (env keys documented; real token set by operator later).

## Next Phase Readiness

- 08-05 (papers-page UI) can call `AiService::search()` with graceful fallback to SQL search on `AiServiceUnavailableException`.
- 08-06 (sync) can call `AiService::rebuildIndex()` from observers/jobs.

---
*Phase: 08-hybrid-search-foundation*
*Completed: 2026-08-13*
