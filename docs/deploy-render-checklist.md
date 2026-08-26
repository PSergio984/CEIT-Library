# Deploy checklist — Render free tier (no credit card)

The hosting decision lives in the map's Decisions-so-far (hosting research branch
`research/hosting-choice`). `render.yaml` at the repo root is the blueprint: Render
reads it and pre-creates the service. Postgres stays on Supabase; the AI sidecar
stays on FastAPI Cloud.

## One-time human steps (~15 minutes)

1. **Wake the database.** The `.env` Supabase project was paused at last check.
   Open the Supabase dashboard, restore the project, and copy from
   *Project Settings → Database*:
   - pooler host (`aws-0-...pooler.supabase.com`) → `DB_HOST`
   - port `6543` (already set in the blueprint)
   - user / password → `DB_USERNAME` / `DB_PASSWORD`
2. **Generate an app key** on any machine with PHP + this repo:
   `php artisan key:generate --show` → value including `base64:` → `APP_KEY`.
3. **Pick a QR HMAC secret**: any long random string → `QR_HMAC_SECRET`.
4. **Sidecar URL**: the FastAPI Cloud deployment URL → `SIDECAR_URL`,
   its shared token → `SIDECAR_TOKEN`.

## Render steps

1. Sign in at render.com with GitHub (free, no card).
2. **New → Blueprint**, select `PSergio984/CEIT-Library`, branch
   `fix/docker-assets-and-qr-support` (or the default branch after merge).
   Render reads `render.yaml`; fill every `sync: false` value from the list above.
3. First deploy builds the Docker image and runs migrations via `Docker/start.sh`.
4. After the service is live: set `APP_URL` to the rendered
   `https://ceit-library.onrender.com` and redeploy (config-only change).

## Free-tier realities (accepted trade-offs)

- Service sleeps after ~15 min idle; first request pays a ~1-minute cold start.
- No persistent disk: uploaded files live on the mounted volume only until the
  next deploy — demo data must be seeded, not precious.
- 750 instance-hours/month is plenty for one always-on free service.

## Verify

- `https://<service>.onrender.com` loads the login page; the demo-student button works.
- Ask the assistant one question — a grounded answer with citations proves the
  sidecar URL + token wiring end to end.
- A line lands in `storage/logs/ai-cost.log` for that answer (cost log working).
