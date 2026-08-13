# Phase 8: Hybrid Search Foundation — Pattern Mapping

**Mapped:** 2026-08-13
**Feeds:** `08-PLAN.md` (planner) — exact code excerpts, no re-reading needed
**Sources:** `08-CONTEXT.md` (D-01..D-21), `08-RESEARCH.md` §2-§9, live codebase (Laravel 12/13 + Livewire 4 + Mary UI), WSL `~/workspace/rag-search-engine`, `D:\ai-eng\llm-zc`, `.planning/research/{STACK,ARCHITECTURE}.md`

**Global repo conventions (apply everywhere below):**
- PHP 8.4, Laravel 12/13 skeleton — **no `app/Exceptions/` dir** (exceptions configured in `bootstrap/app.php`); custom exceptions must create `app/Exceptions/` themselves.
- No `declare(strict_types=1)` anywhere in `app/` — follow suit.
- Tests: PHPUnit only (no Pest), `tests/Feature/*.php` with `use RefreshDatabase`; **both** `/** @test */` docblock and `#[Test]` PHP 8 attribute styles coexist (`ExportTest.php` vs `CatalogSequenceTest.php`) — either is acceptable, prefer `#[Test]`.
- SQLite in-memory in tests (`phpunit.xml`: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `QUEUE_CONNECTION=sync`) — never write tests depending on Postgres FTS (R11).
- Scheduler registration lives in `routes/console.php` (not a Kernel class).
- **Phase 8 introduces NO migrations** (jobs table exists; observers/pivot need no schema change).

---

## LARAVEL — CREATE

### app/Services/CorpusExporter.php
- **Role:** Service — pure serialization, testable without console (research §2.4).
- **Data flow:** `AcademicPaper::with([authors, researchAdviser, technicalAdviser, dean])` + `RuleHeader::with(ruleRegulations)` → JSON doc arrays (§2.1 shapes) → `storage/app/ai-corpus/{catalog,policies}.json`.
- **Closest analog:** `app/Services/NotificationService.php` — plain service class, no interface, no DI ceremony, `App\Services` namespace.
- **Pattern excerpt:**
  ```php
  namespace App\Services;

  use App\Models\Notification;
  use Illuminate\Support\Facades\Log;

  class NotificationService
  {
      public function notify(User $user, string $category, string $title, string $message, array $data = [])
      {
          // ... side effects guarded by try/catch + Log::error(...)
      }
  }
  ```
- **Key conventions:** static-free instance methods (`exportAll()`, `exportCatalog()`, `exportPolicies()`); return the doc arrays so `ExportAiCorpus` handles file I/O (or accept a `$path`); **never `Log::info()` document contents** (PII token hygiene §2.3); field caps: title ≤ 500, names ≤ 200, regulation content ≤ 20k; strip control chars; R10 — `RuleHeader::ruleRegulations()` currently does `->orderBy('order')` but `rule_regulations` has NO `order` column → use `->orderBy('id')` in the exporter and flag.

### app/Services/AiService.php
- **Role:** Service — sole HTTP gateway to the sidecar (ARCHITECTURE.md: "Never direct — always through AiService").
- **Data flow:** `search/rebuildIndex/health` args → `Http::withToken(...)` POST/GET → decoded `array`; failures → `AiServiceUnavailableException` / `AiServiceAuthException`.
- **Closest analog:** `app/Services/NotificationService.php` for class shape; ARCHITECTURE.md line 59 + 156 for the `Http` facade chain (no existing HTTP usage in app — this introduces the pattern).
- **Pattern excerpt:**
  ```php
  $response = Http::withToken(config('services.ai_sidecar.token'))
      ->baseUrl(config('services.ai_sidecar.base_url'))
      ->connectTimeout(3)->timeout(10)
      ->retry(2, 250, throw: false)   // /search ONLY; rebuildIndex: timeout(120), retry(1)
      ->post('/search', [...]);        // research §4.3
  ```
- **Key conventions:** fresh client per call (Octane-safe, R12); config keys `services.ai_sidecar.{base_url,token,corpus_path}`; error mapping keeps Livewire layer clean; signature style per §4.3: `search(string $query, array $filters = [], ?string $corpus = 'catalog', int $limit = 10): array`.

### app/Console/Commands/ExportAiCorpus.php
- **Role:** Command — `ai:export-corpus [--corpus=all|catalog|policy]`.
- **Data flow:** (none) → `CorpusExporter` → JSON files on disk → `0`; `--corpus` switches which file(s) to write.
- **Closest analog:** `app/Console/Commands/UpdateLibrarianBatchStatuses.php` — the repo's class-based command style (note: some commands are inline closures in `routes/console.php`; research chose class-based — both exist, class-based is the newer convention).
- **Pattern excerpt:**
  ```php
  namespace App\Console\Commands;

  use Illuminate\Console\Command;

  class UpdateLibrarianBatchStatuses extends Command
  {
      protected $signature = 'librarian:update-batch-statuses';
      protected $description = 'Update librarian batch statuses based on current date (inactive -> active -> expired)';

      public function handle()
      {
          $this->info('Updating librarian batch statuses...');
          // ... work, $this->line(...) progress, $this->error(...) failures
          return 0;
      }
  }
  ```
- **Key conventions:** `$signature` with options (`--corpus=all|catalog|policy`), `handle(): int` returning `0`, `$this->info/line/error` progress; must create `storage/app/ai-corpus/` if missing.

### app/Console/Commands/SyncAiIndex.php
- **Role:** Command — `ai:sync-index` → `POST /index/rebuild`.
- **Data flow:** (none) → `AiService::rebuildIndex()` → response logged (sanitized) → `0`.
- **Closest analog:** same as ExportAiCorpus (`UpdateLibrarianBatchStatuses.php`).
- **Pattern excerpt:** (same skeleton as above — `protected $signature = 'ai:sync-index';`)
- **Key conventions:** no params; idempotent by design (D-12); log rebuild counts only, never doc contents.

### app/Console/Commands/ReconcileAiIndex.php
- **Role:** Command — `ai:reconcile-index --check|--repair` (nightly).
- **Data flow:** fresh DB counts (`AcademicPaper::count()` etc.) + `AiService::health()` → compare `by_corpus` + `source_generated_at` freshness (< 26h) → warning log / dispatch `AiIndexRebuildJob`.
- **Closest analog:** same command skeleton; count-and-flag logic mirrors `routes/console.php` inline `attendance:check-missing-timeouts` (which scans, reports, and acts in one handle()).
- **Key conventions:** `--check` = read-only report, `--repair` = dispatch rebuild job; sanitized `Log::warning` on mismatch (PITFALLS §3).

### app/Observers/AcademicPaperObserver.php
- **Role:** Observer — created/updated → debounced job; deleted → immediate job (D-10/D-11).
- **Data flow:** Eloquent event on `AcademicPaper` → `AiIndexRebuildJob::dispatch()` / `AiIndexRebuildImmediateJob::dispatch()`.
- **Closest analog:** none in-repo (`app/Observers/` does not exist yet — research verified). Standard Laravel observer: `public function created(AcademicPaper $paper): void`, `updated`, `deleted`; registration in `AppServiceProvider::boot()` via `AcademicPaper::observe(AcademicPaperObserver::class)`.
- **Key conventions:** keep bodies one-liners (dispatch only); `$touches` on pivot covers author attach/detach via parent `updated` — see `AcademicPaperAuthor.php` section (flag: relation needs `->using()` added, see below).

### app/Observers/PeopleNameObserver.php
- **Role:** Observer — same behavior for 4 name models (`Author`, `ResearchAdviser`, `TechnicalAdviser`, `Dean`).
- **Data flow:** event on any people model → debounced/immediate rebuild job.
- **Closest analog:** `app/Models/Author.php` for model shape; observer pattern same as `AcademicPaperObserver`.
- **Pattern excerpt:**
  ```php
  class Author extends Model
  {
      use HasFactory;
      protected $fillable = ['name'];
  }
  ```
- **Key conventions:** people names are single `name` strings ("LASTNAME, FIRSTNAME M."); register all four `X::observe(PeopleNameObserver::class)` lines in `AppServiceProvider::boot()`; adviser rename must queue the debounced job (test asserts this).

### app/Observers/RulebookObserver.php
- **Role:** Observer — same for `RuleHeader` + `RuleRegulation` (policy corpus).
- **Data flow:** policy edit event → rebuild job.
- **Closest analog:** same as above; models `app/Models/RuleHeader.php` / `RuleRegulation.php` (note `RuleRegulation` fillable `['rule_header_id', 'content']`, cascade delete on header).
- **Key conventions:** `RuleRegulation` delete → immediate job (policy must vanish fast, same trust rule as D-11).

### app/Jobs/AiIndexRebuildJob.php
- **Role:** Job (debounced) — `ShouldQueue` + `ShouldBeUnique(uniqueId: 'ai-index-rebuild', uniqueFor: 300)`, dispatched with `->delay(now()->addSeconds(60))`.
- **Data flow:** queue → `CorpusExporter::exportAll()` → `AiService::rebuildIndex()` (research §2.4 job internals).
- **Closest analog:** `app/Jobs/SendPushNotificationJob.php` — the repo's only job; queue = `database` driver, `jobs` table exists.
- **Pattern excerpt:**
  ```php
  namespace App\Jobs;

  use Illuminate\Contracts\Queue\ShouldQueue;
  use Illuminate\Foundation\Queue\Queueable;

  class SendPushNotificationJob implements ShouldQueue
  {
      use Queueable;

      public function __construct(
          public User $user,
          public Notification $notification
      ) {}

      public function handle(): void
      {
          // ... guarded by try/catch + logger()->error(...)
      }
  }
  ```
- **Key conventions:** constructor-promoted public props; `handle(): void`; no constructor args needed here (no-op `__construct` or omit); add `public function uniqueId(): string { return 'ai-index-rebuild'; }`.

### app/Jobs/AiIndexRebuildImmediateJob.php
- **Role:** Job (immediate, deletions) — no delay, `uniqueId: 'ai-index-rebuild-immediate'`.
- **Data flow:** queue → same export → rebuild pipeline as the debounced job.
- **Closest analog:** same `SendPushNotificationJob` skeleton; the two unique IDs ensure a pending debounced job can't swallow a deletion rebuild (research §5.1).
- **Key conventions:** identical body to `AiIndexRebuildJob` — consider a shared static helper or base class only if planner prefers; uniqueness contract is the load-bearing part.

### app/Models/AcademicPaperAuthor.php
- **Role:** Model (custom Pivot) — makes author attach/detach touch the parent paper so the debounced job fires.
- **Data flow:** pivot write → `$touches = ['academicPaper']` → parent `updated` event → observer → job.
- **Closest analog:** ⚠️ **discrepancy flag for planner:** RESEARCH §2.4 says `belongsToMany()->using(AcademicPaperAuthor::class)` "already matches" — it does NOT. Actual code in `app/Models/AcademicPaper.php`:
  ```php
  public function authors()
  {
      return $this->belongsToMany(Author::class, 'academic_paper_authors')->withTimestamps();
  }
  ```
  Required change: create `AcademicPaperAuthor extends Pivot` with `public $touches = ['academicPaper'];` + `public function academicPaper()` and add `->using(AcademicPaperAuthor::class)` to the relation. Fallback safety net per research: hourly export + nightly reconcile catch anything missed.
- **Key conventions:** pivot table has `id` + `timestamps` (migration verified), so custom Pivot is supported; models use `@property` docblocks heavily (`Author.php` shown above) — keep that style.

### app/Exceptions/AiServiceUnavailableException.php
- **Role:** Exception — connection refused/timeout/5xx from sidecar.
- **Data flow:** `AiService` catch → throw → Livewire catches → graceful LIKE-search fallback.
- **Closest analog:** none in-repo (no `app/Exceptions/` dir — Laravel 12 slim skeleton configures via `bootstrap/app.php`). Convention: `class AiServiceUnavailableException extends \RuntimeException {}` — minimal, message carried through.

### app/Exceptions/AiServiceAuthException.php
- **Role:** Exception — 401 from sidecar (config misconfig); log sanitized, never log the token.
- **Data flow:** same as above; auth failures surface as warnings for ops, fallback path also applies.
- **Closest analog:** none in-repo; same minimal `\RuntimeException` subclass.

### tests/Feature/ExportAiCorpusTest.php
- **Role:** Feature test — schema/shape/anti-PII asserts for the export (§8.1).
- **Data flow:** factories seed → `$this->artisan('ai:export-corpus')` → read JSON from disk (use `Storage` or raw `storage_path`) → asserts.
- **Closest analog:** `tests/Feature/CatalogSequenceTest.php` (artifact-less style) + `tests/Feature/ExportTest.php` (uses `RefreshDatabase`, role-id helper, `#[Test]` attributes, TC-numbered docblocks).
- **Pattern excerpt:**
  ```php
  class CatalogSequenceTest extends TestCase
  {
      use RefreshDatabase;

      #[Test]
      public function it_generates_sequential_catalog_codes_for_same_department_and_year(): void
      {
          $paper1 = AcademicPaper::create([...]);
          $this->assertEquals('CEIT-IT-25-01', $paper1->catalog_code);
      }
  }
  ```
- **Key conventions:** factories exist for ALL corpus models (`AcademicPaperFactory`, `AuthorFactory`, `DeanFactory`, `ResearchAdviserFactory`, `TechnicalAdviserFactory`, `RuleHeaderFactory`, `RuleRegulationFactory`); `AcademicPaperFactory` already pins departments (`Civil Engineering`, `Information Technology`, `Electrical Engineering`) + paper types (`Thesis, Feasib, Capstone, Research, Practicum, Report`); assert **no `copy_number`/`status` keys anywhere** (R3), `count == documents.length`, regulation `text` starts `Section: <header title>`.

### tests/Feature/AiIndexObserverTest.php
- **Role:** Feature test — trigger matrix verification.
- **Data flow:** `Bus::fake()` → model CRUD → assert `AiIndexRebuildJob` queued with 60s delay (created/updated) vs `AiIndexRebuildImmediateJob` no delay (deleted); adviser rename; `RuleRegulation` delete.
- **Closest analog:** `CatalogSequenceTest.php` style + Laravel standard `Queue::fake()`/`Bus::fake()` (no in-repo analog — repo tests don't fake jobs today; research §8.1 specifies `Bus::fake`).
- **Key conventions:** `#[Test]` + `RefreshDatabase`; assert delay via `Bus::assertDispatched(AiIndexRebuildJob::class, fn ($job) => ...)`.

### tests/Feature/AiServiceTest.php
- **Role:** Feature test — HTTP gateway contract (no live sidecar).
- **Data flow:** `Http::fake()` → call `AiService` → assert URL, `X-Sidecar-Token` header, body shape, retry config; 401 → `AiServiceAuthException`; connection refused → `AiServiceUnavailableException`.
- **Closest analog:** `tests/Feature/ExportTest.php` test shape; `Http::fake()` is standard Laravel (no in-repo precedent — this repo has no HTTP outbound calls today).
- **Key conventions:** retries asserted only for `/search`; timeout/retry config asserted per §4.3.

### tests/Feature/ReconcileAiIndexTest.php
- **Role:** Feature test — reconciliation logic.
- **Data flow:** seed DB + `Http::fake` health payload → `artisan('ai:reconcile-index --check')` → assert repair dispatch on count mismatch / stale `source_generated_at`; no-op on match.
- **Closest analog:** `CatalogSequenceTest.php` + `Http::fake` pattern.

### tests/Feature/AcademicPaperIndexHybridTest.php
- **Role:** Feature test — papers-page wiring + fallback.
- **Data flow:** `Http::fake` sidecar results → `Livewire::test(AcademicPaperIndex::class)` → assert sidecar-ordered cards render; sidecar down → page still renders via existing SQL search (no 500).
- **Closest analog:** `tests/Feature/Livewire/*` dir exists (repo already Livewire-tests components — `QrScannerTest.php`, `FiltersTest.php` etc.).
- **Key conventions:** `Livewire::test()` facade; assert order follows sidecar `score`, not DB id.

### tests/Feature/SidecarLiveTest.php
- **Role:** Feature test — opt-in live integration (SEARCH-07 round-trip).
- **Data flow:** real export → rebuild → search → assert; skipped unless `SIDECAR_LIVE_TEST=1`.
- **Closest analog:** none in-repo; convention: group annotation `@group sidecar` + env-gated skip (`markTestSkipped`).

### tests/fixtures/ai-sidecar/*.json
- **Role:** Test fixtures — recorded sidecar responses (`health.json`, `search.json`, `rebuild.json`) per §8.3 contract fixtures; never hit live service in CI.
- **Data flow:** static JSON → consumed via `Http::fake(['127.0.0.1:8310/*' => Http::response(...)])`.
- **Closest analog:** none in-repo (`tests/fixtures/` does not exist — create it); shapes = exact response bodies from research §4.2.
- **Key conventions:** include `contract_version: "v1"` in every fixture; keep `X-Sidecar-Token` OUT of fixtures where possible (fake by URL + assert header separately).

---

## LARAVEL — MODIFY

### config/services.php
- **Role:** Config — add `ai_sidecar` block (research §4.3).
- **Data flow:** `.env` → config → `AiService` reads `config('services.ai_sidecar.*')`.
- **Closest analog:** same file, existing blocks:
  ```php
  'slack' => [
      'notifications' => [
          'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
          'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
      ],
  ],
  ```
- **Key conventions:** new block per §4.3: `'ai_sidecar' => ['base_url' => env('SIDECAR_URL', 'http://127.0.0.1:8310'), 'token' => env('SIDECAR_TOKEN'), 'corpus_path' => env('AI_CORPUS_PATH', storage_path('app/ai-corpus'))]`.

### .env.example
- **Role:** Config template — add `SIDECAR_URL`, `SIDECAR_TOKEN`, `AI_CORPUS_PATH`.
- **Closest analog:** tail of current file (flat `KEY=value` style, comments for grouped sections).
- **Key conventions:** append a commented `# AI Sidecar (hybrid search)` block; token placeholder empty (never commit a real value, R4).

### routes/console.php
- **Role:** Scheduler — hourly export+sync, nightly reconcile (D-10).
- **Data flow:** cron → schedule → commands.
- **Closest analog:** same file — exact existing registration style:
  ```php
  // Schedule librarian batch status updates to run every hour
  Schedule::command('librarian:update-batch-statuses')->hourly();
  ```
  and `Schedule::command('attendance:check-missing-timeouts')->dailyAt('00:30');`
- **Key conventions:** research §2.4: `Schedule::command('ai:export-corpus')->hourlyAt(5);` then `Schedule::command('ai:sync-index')->hourlyAt(10);` (after export), `Schedule::command('ai:reconcile-index')->dailyAt('02:00');` register next to existing `librarian:update-batch-statuses` block with comment style matching neighbors.

### app/Providers/AppServiceProvider.php
- **Role:** Provider — register the three observers in `boot()`.
- **Data flow:** boot → `X::observe(...)` for 7 models.
- **Closest analog:** same file — `boot()` is a long list of `RateLimiter::for(...)` + `Gate::define(...)` blocks; observe calls append in the same file (research: no observer registration exists anywhere yet).
- **Pattern excerpt:**
  ```php
  public function boot(): void
  {
      RateLimiter::for('search', function (Request $request) {
          return $this->rateLimitForUser($request, 500, 60);
      });
      // ...
      AcademicPaper::observe(AcademicPaperObserver::class);
  }
  ```
- **Key conventions:** Gates unchanged — Phase 8 respects existing role gates; existing `throttle:search` rate limiter already covers the search surface.

### app/Livewire/Pages/Student/AcademicPaperIndex.php
- **Role:** Livewire component — wire search box to `AiService` (D-07/D-08), keep availability logic local.
- **Data flow:** `$search` + filter props → `AiService::search(...)` (NOT in `#[Computed]` — no HTTP in computed props) → map results to ids → hydrate `AcademicPaper::with('authors','copies')->findMany(ids)` → render cards ordered by sidecar score; sidecar down → existing `academicPapers()` computed (local LIKE search) + subtle notice.
- **Closest analog:** same file — full excerpt captured above; key existing surface:
  ```php
  #[Title('Academic Paper List')]
  #[Layout('components.layouts.app')]
  #[Lazy]
  class AcademicPaperIndex extends Component
  {
      use CreatesQrCanonicalMessage, WithPagination;

      #[Validate('string|max:100|nullable')]
      public string $search = '';

      #[Computed]
      public function academicPapers() { /* local LIKE + filters */ }

      public function updatedSearch(): void
      {
          $this->resetPage('academic-papers-index');
      }
  }
  ```
- **Key conventions:** add `public ?array $hybridResults = null;` + an action method (e.g. `runHybridSearch()` triggered from `updatedSearch`); keep `$search` length ≥ 3 guard; filters (`departmentFilter`, `paperTypeFilter`, `yearFilter`, `yearFromFilter`, `yearToFilter`) already exist — pass through, don't rebuild; status/availability filters stay SQL-only (D-04); named paginator page name is `'academic-papers-index'` (do NOT use default).

### app/Models/AcademicPaper.php
- **Role:** Model — add `->using(AcademicPaperAuthor::class)` to `authors()` (see AcademicPaperAuthor section for the discrepancy).
- **Closest analog:** same file:
  ```php
  public function authors()
  {
      return $this->belongsToMany(Author::class, 'academic_paper_authors')->withTimestamps();
  }
  ```
- **Key conventions:** relations in this file are untyped (`public function copies()` style), heavy `@property`/`@method` docblocks — add docblock entries if introducing anything new; do not touch `boot()`/`generateUniqueCatalogCode()` (code-gen path, `CatalogSequenceTest` pins it).

### resources/views/livewire/pages/student/academic-paper-index.blade.php
- **Role:** View — render hybrid result cards (sidecar-ordered) with a subtle fallback notice.
- **Data flow:** `$this->hybridResults` → Mary UI card markup.
- **Closest analog:** same file — existing mobile/desktop card sections:
  ```blade
  @forelse ($this->academicPapers as $paper)
      <div wire:key="mobile-paper-{{ $paper->id }}" class="bg-base-100 border border-base-300 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow">
          <span class="badge badge-sm {{ $paper->status === 'Available' ? 'badge-success' : 'badge-error' }}">
              {{ $paper->status }}
          </span>
          <span class="badge badge-sm badge-outline">{{ $paper->catalog_code }}</span>
          <h3 class="font-semibold text-sm sm:text-base line-clamp-2 break-words">{{ $paper->title }}</h3>
  ```
- **Key conventions:** daisyUI classes (`bg-base-100`, `badge-*`), Mary components (`<x-mary-button icon="o-eye">`); reuse `showPaperDetails($paper->id)` action for the detail modal; new markup mirrors the existing card blocks rather than new components. Note: search input already lives in `resources/views/components/academic-paper-filters.blade.php` with `wire:model.live.debounce.300ms="search"` — research §4.4 mentions 400ms debounce; **actual repo value is 300ms — planner keeps or adjusts deliberately (agent discretion), don't assume 400ms exists.**

---

## SIDECAR (separate repo `ceit-ai-sidecar/` — D-15/D-16)

Reference analogs: `rag-search-engine` (WSL) for search/eval/fusion; `llm-zc` (D:\ai-eng\llm-zc) for ingest/persistence; STACK.md pins versions (FastAPI 0.141, uvicorn 0.52, sentence-transformers 5.7, sqlitesearch 0.3, pydantic-settings 2.15, Python 3.13, uv + ruff + pytest).

### pyproject.toml
- **Role:** Package manifest (uv-managed).
- **Closest analog:** `~/workspace/rag-search-engine/pyproject.toml`:
  ```toml
  [project]
  name = "rag-search-engine"
  version = "0.1.0"
  requires-python = ">=3.12"
  dependencies = [
      "nltk==3.9.1",
      "numpy==2.3.3",
      "sentence-transformers>=5.6.0",
  ]
  [tool.uv]
  package = false
  ```
- **Key conventions:** Python 3.13; deps per STACK.md §uv add list (fastapi, uvicorn[standard], sentence-transformers, sqlitesearch, pydantic, pydantic-settings, numpy; dev: pytest, httpx, ruff); `[tool.uv] package = false`; commit `uv.lock`.

### app/main.py
- **Role:** FastAPI app — token middleware (constant-time compare, 401 otherwise), routes (`/health`, `/search`, `/index/rebuild`, `/metrics`), uvicorn entry.
- **Closest analog:** no FastAPI in either reference repo (llm-zc `main.py` is Streamlit-oriented but shows env-driven client/provider detection; rag-search-engine uses CLIs). ARCHITECTURE.md §67-87 pins the layout; STACK.md pins FastAPI/uvicorn versions.
- **Pattern excerpt (convention to follow — FastAPI 0.141, from ARCHITECTURE.md structure):**
  ```python
  app = FastAPI(title="ceit-ai-sidecar")

  @app.middleware("http")
  async def require_token(request, call_next):
      if not secrets.compare_digest(request.headers.get("X-Sidecar-Token", ""), settings.sidecar_token):
          return JSONResponse(status_code=401, content={"error": {"code": "invalid_request", ...}})
      return await call_next(request)

  if __name__ == "__main__":
      uvicorn.run("app.main:app", host="127.0.0.1", port=8310)
  ```
- **Key conventions:** loopback-only bind `127.0.0.1:8310`; response envelope `{"error": {"code": ..., "message": ...}}` (§4.1); `contract_version: "v1"` on responses; `metrics` as minimal hand-rolled JSON dict this phase (prometheus-client Phase 14).

### app/config.py
- **Role:** Config — pydantic-settings.
- **Closest analog:** references use `python-dotenv` (rag-search-engine `.env`) — STACK.md overrides: "prefer pydantic-settings in new code."
- **Pattern excerpt:**
  ```python
  from pydantic_settings import BaseSettings

  class Settings(BaseSettings):
      sidecar_token: str
      corpus_path: Path
      model_name: str = "paraphrase-multilingual-MiniLM-L12-v2"
      host: str = "127.0.0.1"
      port: int = 8310
      model_config = SettingsConfigDict(env_file=".env")
  ```
- **Key conventions:** env keys `SIDECAR_TOKEN`, `CORPUS_PATH`, `MODEL_NAME`; no defaults for secrets.

### app/ingest.py
- **Role:** Ingest — `catalog.json` + `policies.json` (files, never HTTP, D-17) → doc store + persisted `cache/docs.json`.
- **Closest analog:** `D:\ai-eng\llm-zc\ingest_sqlite.py` — the FTS5 persistence pattern STACK.md chose:
  ```python
  from sqlitesearch import TextSearchIndex

  index = TextSearchIndex(
      text_fields=["question", "section", "answer"],
      keyword_fields=["course"],
      db_path=DB_PATH,
  )
  for doc in docs_llm:
      index.add(doc)
  index.close()
  ```
  (Phase 8 maps to `text_fields=["text"]`, `keyword_fields=["corpus", "department", "paper_type", ...]`; ALSO llm-zc `ingest.py` shows `build_index(documents)` + `load_*` function split — pure functions, testable.)
- **Key conventions:** validate loudly: `schema_version`, `generated_at`, required fields per doc; reject duplicate ids; per-corpus tags from export `corpus` field; whole-doc embedding (one vector per document — no sentence chunking, R6).

### app/search.py
- **Role:** Search — BM25 (FTS5) + semantic (numpy cosine) + RRF k=60, post-retrieval filters (D-03, §3.3).
- **Closest analog:** `~/workspace/rag-search-engine/cli/lib/hybrid_search.py::rrf_search` — port the math VERBATIM, drop the `limit * 500` pool (retrieve-all at corpus scale, PITFALLS §4):
  ```python
  def rrf_search(self, query: str, k: int, limit: int = 10) -> list[dict]:
      """... RRF score = sum of 1/(k + rank) across every system ..."""
      bm25_ranks: dict[int, int] = {}
      for rank, (doc_id, _) in enumerate(bm25_pairs, 1):
          bm25_ranks[doc_id] = rank
      sem_ranks: dict[int, int] = {}
      for rank, r in enumerate(semantic_results, 1):
          sem_ranks[r["id"]] = rank
      all_ids = set(bm25_ranks.keys()) | set(sem_ranks.keys())
      # per id: rrf_score = (1/(k+bm25_rank) if present) + (1/(k+sem_rank) if present)
      # result dicts: id, title, score, bm25_rank, semantic_rank, metadata (§4.2 response)
  ```
- **Key conventions:** filters applied on expanded candidate lists BEFORE fusion (§3.3); code-exact pin for `^CEIT-[A-Z]{2}-\d{2}` queries → rank 1 (§3.4); no weighted_search/alpha path at all in Phase 8 (R1); empty union → `[]`.

### app/rebuild.py
- **Role:** Rebuild — full rebuild + atomic swap + `threading.Lock` serialization (D-12, §3.5).
- **Closest analog:** llm-zc `main.py` persistent-index "open-vs-build" pattern (ARCHITECTURE.md line 62/329) — Phase 8 inverts it to always-full-rebuild (Pitfall-3 build-once bug designed out, R2).
- **Key conventions:** build FTS5 + `.npy` + `docs.json` into temp files → `os.replace()` atomic swap → update `index_state` (`built_at`, `source_generated_at`, counts per corpus, `model_name`, `contract_version`); concurrent rebuild requests return the in-flight result; searches serve old index mid-rebuild.

### app/health.py
- **Role:** Health — assemble index state for `GET /health` (§4.2).
- **Closest analog:** llm-zc `metrics.py` dataclass bookkeeping pattern (`LLMCallRecord` dataclass with typed fields) → an `IndexState` dataclass fits the house style:
  ```python
  @dataclass
  class LLMCallRecord:
      model: str
      response_time: float
      timestamp: datetime = field(default_factory=datetime.now)
  ```
- **Key conventions:** health = index loaded AND `documents == embedded` (embedding coverage); echoes `source_generated_at` for reconciliation; degraded state reported, not hidden.

### app/eval.py
- **Role:** Eval CLI — golden-set runner: `uv run eval --limit 5 [--corpus ...] [--json]` (P@k/R@k/F1 + negative pass rate).
- **Closest analog:** `~/workspace/rag-search-engine/cli/evaluation_cli.py` — port structure; CHANGE title-matching → **id-matching** (research §3.2: titles collide, ids stable):
  ```python
  parser = argparse.ArgumentParser(description="Search Evaluation CLI")
  parser.add_argument("--limit", type=int, default=5)
  ...
  for tc in golden["test_cases"]:
      query = tc["query"]
      relevant = set(tc["relevant_docs"])
      results = hs.rrf_search(query, k=60, limit=limit)
      num_relevant_retrieved = sum(1 for r in results if r["id"] in relevant)
      precision = num_relevant_retrieved / limit if limit > 0 else 0.0
  ```
- **Key conventions:** negatives scored as pass = zero relevant-ids retrieved (separate metric, not P/R); `--json` output for CI gates; sys.path bootstrap so imports resolve from repo root.

### data/golden_dataset.json
- **Role:** Data — golden set (D-19/D-20; lives in the SIDECAR repo, per research §6).
- **Closest analog:** `~/workspace/rag-search-engine/data/golden_dataset.json` shape `{"test_cases": [{"query", "relevant_docs"}]}` — Phase 8 extends to:
  ```json
  {"version": 1, "catalog_snapshot": "<generated_at>",
   "test_cases": [{"query": "...", "corpus": "catalog", "filters": {...}, "relevant_docs": ["paper-42"], "negative": false}]}
  ```
- **Key conventions:** 25 draft cases in research §6 (categories: exact title, catalog code, paraphrase, people, Taglish, policy, negative); id-based; `catalog_snapshot` stamped (PITFALLS §9); planner replaces `paper-N` refs with real DB ids from a real export; user reviews (D-19).

### tests/test_rrf.py
- **Role:** pytest — fusion math pins (R1): k extremes, `Σ1/(k+rank)` values, rank-1-both dominance, one-ranker-only contribution, monotonic ordering, empty union → [].
- **Closest analog:** `~/workspace/rag-search-engine/tests/test_rerank.py` (repo already pytest-based); STACK.md: pytest + ruff.
- **Key conventions:** hand-computed expected values, no tolerance slop on exact RRF sums.

### tests/test_ingest.py
- **Role:** pytest — JSON parsing, corpus tags, dedupe, loud failure on malformed/missing `generated_at`, model encodes Taglish → 384-dim (D-13), PII-free asserts.
- **Closest analog:** same pytest style; fixtures mirror §2.2 JSON shapes.
- **Key conventions:** multilingual model load is slow → mark/skip if model cache absent in fast CI (or fixture the encoder); 384-dim assertion for `paraphrase-multilingual-MiniLM-L12-v2`.

### tests/test_filters.py
- **Role:** pytest — post-retrieval filter behavior (§3.3): department/paper_type/year/range, filter+corpus combos, code-exact pin (§3.4).
- **Closest analog:** same pytest style.
- **Key conventions:** assert filtering happens on expanded candidate lists before fusion (filtered doc can never outrank an unfiltered relevant one).

### tests/test_api.py
- **Role:** pytest — FastAPI `TestClient`: 401 without token / 200 with; `/search` response schema (§4.2); `/health` coverage fields; rebuild atomicity (old index served mid-rebuild — simulate slow embed); concurrent rebuild serialization.
- **Closest analog:** standard FastAPI TestClient (httpx in dev deps, STACK.md).
- **Key conventions:** envelope errors `{"error": {"code", "message"}}`; `contract_version` present; token via `headers={"X-Sidecar-Token": ...}`.

---

## Cross-cutting notes for the planner

- **Discrepancies found (verified against live code):** (1) `AcademicPaperAuthor` pivot + `->using()` does NOT exist despite RESEARCH §2.4's "already matches" — plan the pivot model + relation change (section above). (2) Debounce in blade is 300ms, RESEARCH says 400ms — pick one deliberately. (3) R10: `RuleHeader::ruleRegulations()->orderBy('order')` with no `order` column — exporter must use `orderBy('id')`. (4) No `app/Exceptions/` dir in this skeleton — create it.
- **No new migrations, no new Composer packages** (Http facade suffices, research §1).
- **Existing `throttle:search` rate limiter + Gates already cover the search surface** — Phase 8 adds nothing to auth.
