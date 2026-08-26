# Research: $0, No-Credit-Card Hosting for CEIT-Library (Laravel + Livewire)

Ticket: #50 · Researched: 2026-08-26 · All facts verified against 2025–2026 primary sources where available.

## Decision

**Winner: Render — free Web Service, Docker runtime.**
**Runner-up: Vercel Hobby via the `vercel-php` community runtime.**
**Plan B (always-on alternative): alwaysdata free tier.**

PostgreSQL stays where it already is: **Supabase** (project is pre-wired for it), so the host never needs to provide a database. The AI sidecar stays on **FastAPI Cloud**, injected purely as an env var (`SIDECAR_URL`).

## Comparison table

| Host | Free limits | Card required? | Postgres story | Cold start / sleep | Deploy method for Laravel |
|---|---|---|---|---|---|
| **Render** ✅ | 750 free instance-hrs/workspace/mo; 512 MB RAM / 0.1 CPU; ~100 GB bandwidth/mo; ~500 build min/mo | **No** — account works with no payment method; if bandwidth runs out, services suspend instead of billing | Free Postgres expires after **30 days** (+14-day grace, then deleted) → don't use it; keep Supabase | Spins down after 15 min idle; wake ≈ 30–60 s | Docker runtime (official Laravel+Docker guide & template exist); git-push autodeploy |
| Vercel Hobby 🥈 | $0, 100 GB transfer, 1M fn invocations, 6000 build min/mo; **personal/non-commercial license only** | **No** | None built-in → external DB mandatory (Supabase ✓) | Serverless cold starts (seconds); scale-to-zero inherent | Community `vercel-php` runtime; Laravel needs `api/lambda.php` entrypoint + `/tmp` cache paths; hacky but documented |
| alwaysdata 🔁 | €0 forever, no expiry; ~100 MB disk (sources conflict: some list up to 1 GB); 256 MB RAM; unlimited MariaDB/**PostgreSQL** | **No** ("Registration without credit card") | Managed PostgreSQL included | **Always-on — no sleep** (shared hosting) | SSH + git + composer on server; most manual of the three |
| Railway ❌ | Trial: one-time $5 credit / 30 days, then Free plan **$1/month** non-rolling credit; credits exhausted ⇒ services pause | **No** (pricing page states "No credit card required") | Postgres possible but eats the same credits | N/A — budget dies mid-month | Git/Nixpacks |
| Koyeb ❌ | `free` instance type (512 MB / 0.1 vCPU) still exists, scale-to-zero after 1 h idle | **Yes — since ~2025/26 signup requires a card** ($29 pre-auth verification; FAQ confirms) | Free Postgres hours limited | Wake in seconds | Git/buildpacks incl. PHP |
| Northflank ❌ | Developer Sandbox: 2 services, 1 DB, 2 cron jobs, always-on | **Yes — card demanded at signup** as anti-abuse verification (multiple 2025–2026 reports) | 1 free addon | None (always-on sandbox) | Git/Docker |
| Fly.io ❌ | Legacy free allowances removed Oct 2024; new orgs get a token trial (≈2 VM hrs / 7 days) then must attach payment | **Yes** | Paid machines | N/A | flyctl CLI + Dockerfile |
| Heroku / Glitch / Replit / Netlify ❌ | Heroku: no free tier since Nov 2022. Glitch: **hosting shut down Jul 8, 2025**. Replit: deployments effectively paid. Netlify: no PHP runtime. | — | — | — | — |
| Oracle/GCP/AWS/Azure ❌ | Generous always-free VMs exist | **Card mandatory at signup** (identity verification) | — | — | — |

## Why Render wins

1. **Genuinely no card.** Render's own April 2026 free-tier roundup and its docs describe full operation *without* a payment method: exceeding bandwidth suspends free services rather than billing them. Multiple independent 2026 reviews confirm signup/deploy with zero payment info.
2. **Official first-party Laravel path.** `render.com/docs/deploy-php-laravel-docker` documents exactly our shape (Laravel + Docker + external pgsql env vars), and the repo **already has a root `Dockerfile`** — the packaging step is mostly configuration, not code.
3. **The two classic Render free-tier traps don't apply here.**
   - *Free Postgres 30-day expiry* → irrelevant: we keep Supabase.
   - *Ephemeral filesystem* → acceptable: sessions/cache/queue already use the database driver; uploads are demo-grade (see caveats).
4. Cold starts (15-min sleep, ~30–60 s wake) are explicitly acceptable per the ticket constraints, and a free external pinger can keep it warm.

Runner-up rationale (Vercel): no card, generous free quota, real Laravel-on-Vercel recipes — but serverless constraints (function-duration caps, `/tmp`-only writes, Hobby cron limits, non-commercial ToS) make Livewire uploads/scheduler fragile. Plan B (alwaysdata): the only **never-sleeps** no-card option, but ~100 MB disk makes Laravel `vendor/` tight and deployment is fully manual SSH/composer.

## Deployment recipe — Render free Web Service (Docker)

### One-time setup
1. Push this branch/repo to GitHub (Render deploys from Git).
2. Sign up at render.com with GitHub SSO — **no card anywhere in the flow**.
3. Dashboard → **New → Web Service** → select the `PSergio984/CEIT-Library` repo.
4. Settings:
   - **Runtime:** `Docker` (auto-detects the root `Dockerfile`; add `.dockerignore` if missing).
   - **Instance Type:** `Free`.
   - **Region:** closest free region to graders/audience (e.g., Singapore or Frankfurt).
   - **Health Check Path:** `/` (or an existing health route); raise **Health Check Grace Period** to cover boot + migrations.
   - Auto-deploy: yes, on push to chosen branch.

### Environment variables (names only — values go straight into Render's encrypted env store, never into git)
| Name | Purpose |
|---|---|
| `APP_ENV` | set to production value |
| `APP_DEBUG` | disable debug output |
| `APP_KEY` | Laravel encryption key (`php artisan key:generate --show`) |
| `APP_URL` | the public `https://<service>.onrender.com` URL so URLs/assets/QRs are correct |
| `QR_HMAC_SECRET` | QR signing secret (app already supports via env) |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | point at the existing Supabase PostgreSQL connection settings (Supabase requires TLS — enable SSL mode in the DSN/settings) |
| `SIDECAR_URL` | **base URL of the FastAPI Cloud sidecar deployment** — this is how the sidecar is provided: pure env var, consumed by `config/services.php` (`ai_sidecar.base_url`) |
| `SIDECAR_TOKEN` | shared bearer token expected by the sidecar |
| `RUN_DB_SEED` + `SEED_SUPER_ADMIN_EMAIL` / `SEED_SUPER_ADMIN_PASSWORD` | optional, first-boot seeding of the super admin |
| `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` | leave at `database` defaults — no Redis needed, survives spin-downs |

> Note: docker-compose maps `SIDECAR_BASE_URL` → `SIDECAR_URL`; on Render you set `SIDECAR_URL` directly.

### Database migration
Run `php artisan migrate --force` once against Supabase from a local machine (or bake it into the container's start script as the official Render example does). Don't rely on Render Shell (paid).

### Keeping the demo awake (optional)
Free services sleep after 15 min idle. A free external pinger (cron-job.org, UptimeRobot) hitting the public URL every ~10 min keeps it warm within the 750 free instance-hours/month budget (≈744 hrs needed for 24/7 — fits, barely; skip the pinger if you'd rather bank hours).

### Accepted tradeoffs
- ~30–60 s cold start on first hit after idle.
- Local/uploads storage is ephemeral (lost on redeploy/spin-down) — fine for the demo; future fix: Supabase Storage.
- If monthly bandwidth is exhausted with no card on file, the service suspends until next month (not billed — safe).
- Laravel scheduler: not available as a background worker on free; not required by current features.

## Sources (accessed 2026-08-26)
- Render docs — Deploy for Free (spin-down, ephemeral FS, no-payment suspension, Free Postgres 30-day expiry): https://render.com/docs/free
- Render docs — Deploy a PHP Web App with Laravel and Docker: https://render.com/docs/deploy-php-laravel-docker · template: https://render.com/templates/laravel
- Render — "Platforms with a real free tier for developers in 2026" (750 hrs, no card; Fly.io/Railway/Northflank characterizations): https://render.com/articles/platforms-with-a-real-free-tier-for-developers-in-2026 (2026-04-23)
- Koyeb pricing FAQ — card requirement, $29 pre-auth, free instance specs: https://www.koyeb.com/docs/faqs/pricing
- Northflank card-at-signup reports: https://reviewbolt.com/r/northflank.com (2025 reviews), https://aicredits.dev/submissions/170-northflank-free-developer-sandbox-2-services-database (2026-05-09)
- Railway trial policy (official): https://docs.railway.com/pricing/free-trial · https://railway.com/pricing ("No credit card required", "$1 per month")
- Fly.io free-tier removal (Oct 2024) + trial/card: https://www.gappsy.com/compare/fly-io-vs-northflank (2026-07-17)
- Vercel Hobby plan terms/limits: https://vercel.com/docs/plans/hobby · vercel-php runtime: https://github.com/vercel-community/php
- alwaysdata free plan ("Registration without credit card", PHP/PostgreSQL/SSH): https://www.alwaysdata.com/en/
- Glitch shutdown (Jul 8, 2025): https://blog.glitch.com/post/changes-are-coming-to-glitch
- Heroku free-tier sunset & PHP-host landscape recap: https://www.deployhq.com/blog/how-to-deploy-a-php-website-for-free (2026-04-24)
