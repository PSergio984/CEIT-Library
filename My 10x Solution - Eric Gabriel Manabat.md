# My 10x Solution — Eric Gabriel Manabat

**Project:** CEIT Library — an AI library assistant for the CEIT academic-paper catalog
**Repository:** https://github.com/PSergio984/CEIT-Library (plus its companion search service, `PSergio984/ceit-ai-sidecar`)
**Live sidecar:** https://ceit-ai-sidecar.fastapicloud.dev

## What is the problem you are solving?

CEIT students hunt for the right academic papers and materials through a scattered catalog: information buried in catalog codes, thesis PDFs, and a printed rulebook. Searches take minutes, assume you already know what you're looking for, and often end in wrong or empty results — so paraphrased questions, "what's similar to X?", and simple policy questions ("what happens if I return late?") still end up needing a human at the circulation desk.

**Who has this problem:** CEIT students doing coursework and research (primary); library staff fielding repeated lookup requests (secondary).

**The 10x claim:** *Students at CEIT find the right paper/material in seconds through grounded AI answers with citations, instead of minutes digging through the library catalog.* The system's measured retrieval quality against golden evaluation cases (recall/precision reported in the repository's evaluation docs) backs that shift from minutes of digging to seconds of asking.

## How did you implement your solution?

The system is a Laravel 13 + Livewire 4 web application backed by PostgreSQL (Supabase in production, Postgres in the local Docker Compose stack), talking over HTTP to a FastAPI search sidecar that owns a hybrid (vector + keyword, RRF-fused) index of the library's corpora. An LLM (OpenRouter) turns retrieved documents into grounded answers with numbered citations — and refuses instead of guessing when retrieval finds nothing usable.

The brief's five program concepts are all implemented in this codebase — **zero swaps**:

| Concept | Where it lives | Swapped? |
|---|---|---|
| **API endpoints** | HTTP entrypoints in `routes/web.php` / `routes/auth.php` and Livewire component actions under `app/Livewire/**` (chat streaming, search, feedback, admin CRUD surfaces) | None |
| **Database** | 33 migrations and 24 Eloquent models (`database/migrations/`, `app/Models/`): users/roles, papers, authors, inventory, borrowing, attendance, violations, credit scores, rules, AI conversations/messages | None |
| **Authentication** | Session guard + rate-limited login (`config/auth.php`, `app/Livewire/Forms/LoginForm.php`), one-click reviewer demo login, role/status middleware (`AdminOnly`, `LibrarianOrAdmin`, `CheckAccountStatus`), route-level `can:` authorization in `routes/web.php` | None |
| **Background jobs** | Queued jobs dispatched by model observers and console tooling (`app/Jobs/AiIndexRebuildJob.php`, `SendPushNotificationJob.php`; `app/Observers/*`), database queue driver, hourly scheduler for corpus sync and index reconciliation in `routes/console.php` | None |
| **LLM integration** | Token-gated sidecar gateway with SSE streaming, bounded retries and typed failures (`app/Services/AiService.php`); chat widget with citations, thumbs feedback, and conversation persistence (`app/Livewire/ChatWidget.php`); citation payloads validated against mirrored schema constants before render; corpus lifecycle commands (`ExportAiCorpus`, `PushAiCorpus`, `SyncAiIndex`) keeping the AI index fresh hourly | None |

Two layers guard answer trustworthiness, which is the heart of the product: Laravel validates every citation frame against a mirrored schema before rendering it, and the sidecar validates LLM tool calls against a closed schema before touching the index — so the assistant either shows a real, checkable source or admits it doesn't know.

## Steps to run it

Full detail lives in the README; the short version on a clean machine with Docker:

1. Clone both repositories (`CEIT-Library` and `ceit-ai-sidecar`).
2. Copy `.env.example` to `.env` in each and fill the marked values (database URL, OpenRouter key, shared sidecar token).
3. `docker compose up --build` — app, Postgres, and nginx come up together.
4. Inside the app container: `php artisan migrate --seed` for schema and demo data.
5. Start the queue worker (`php artisan queue:work`) so AI-index rebuild and notification jobs are consumed.
6. Open the app, press the **reviewer demo** login button, and ask the chat widget something like *"papers about machine learning"* — a streamed, cited answer comes back in seconds. The 5-minute click-path is written out in the README.
