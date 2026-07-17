# Hyperion — Backend

Stateless JSON API for **Hyperion**, a mobile-first workout tracker (build
training programs, run workout sessions, review history/progress, and share
programs publicly).

This repo is the **Laravel 13 API**. The client lives in a separate repo
([hyperion-fe](https://github.com/andrawsMalallah/hyperion-fe)).

## Stack

- **Laravel 13** on **PHP 8.4**
- **Laravel Passport** — OAuth Bearer tokens (`auth:api` middleware)
- **PostgreSQL** in production (Neon), **SQLite** locally
- **Brevo HTTP API** for transactional mail (reset / verification)
- **Sentry** for error monitoring (enabled only when a DSN is set)

## Architecture

Standard thin-controller slice per resource:

```
Controller → FormRequest (validation) → Model (Eloquent) → API Resource (response)
```

Heavier logic lives in `app/Services/` (e.g. `ProgramDaySync`, `ProgressStats`,
`ExerciseGrouping`). Auth routes are in `routes/auth.php`; the rest of the API is
in `routes/api.php`. **Email verification is a hard requirement** — data routes
sit behind the `verified` middleware.

## Prerequisites

- **PHP 8.4**
- **Composer**

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan passport:install
```

## Commands

| Task | Command |
| --- | --- |
| Run tests | `php artisan test --compact` |
| Format (required after editing PHP) | `vendor/bin/pint --dirty` |
| Serve | `php artisan serve --host=127.0.0.1 --port=8000` |
| List API routes | `php artisan route:list --path=api` |

> On Windows + Herd, `php` is not on `PATH` — use the full path to the PHP 8.4
> binary (see `CLAUDE.md`).

## Key environment variables

Names only — set real values in `.env` (local) or the host's env (production):

- `APP_URL` — public API origin (email-verification links are signed against it)
- `FRONTEND_URL` — SPA origin; reset/verify links **and** CORS are built from it
- `DB_CONNECTION` / `DB_*` — database connection
- `MAIL_MAILER=brevo-api` + `BREVO_API_KEY` — transactional mail
- `SENTRY_LARAVEL_DSN` — error reporting (optional)

## Deploy

Production is **Render** (auto-deploys on push to `main`) backed by **Neon
Postgres**. The service builds from `Dockerfile`; `docker/entrypoint.sh` is the
deploy hook (runs migrations, `passport:purge`, and `php artisan optimize`).

## More

See `CLAUDE.md` for the full architecture, data model, and pre-deploy checklist,
and `AGENTS.md` for the Laravel Boost conventions.
