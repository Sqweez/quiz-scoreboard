# Repository Guidelines

## Stack and Structure

This is a Laravel 13 + Inertia + Vue monolith backed by PostgreSQL 17. It is
the only live application: production runs at `https://quiz.ariesdev.kz` on
the VPS, and real data was migrated from Supabase on 2026-11-14. The old Nuxt
app, Supabase backend and Netlify site are retired.

PHP application code lives in `app/`, HTTP routes in `routes/web.php`,
scheduled tasks in `routes/console.php`, schema migrations in
`database/migrations/`, and Vue pages/components/stores in `resources/js/`.
Shared quiz types and scoring functions live in `resources/js/lib/quiz/`. Static assets
belong in `public/`. Docker and server files live in `docker/` and
`docker/production/`. Product context and the visual system are described in
`PRODUCT.md` and `DESIGN.md`; follow them for UI changes.

## Commands

Use Composer with PHP 8.4 and npm with Node 24 (`composer.lock` and
`package-lock.json` are committed).

- `composer install` and `npm ci`: install dependencies.
- `php artisan serve` and `npm run dev`: local app and Vite dev server.
- `php artisan test`: PostgreSQL-backed feature tests.
- `npm test`: client unit tests.
- `npm run typecheck` and `npm run build`: TypeScript and production assets.
- `docker compose build` and `docker compose up -d`: local container stack.

## Style and Scope

Use Vue single-file components with `<script setup lang="ts">`, two-space
indentation, PascalCase component names and `use` composable prefixes.
PHP follows Laravel conventions. Make surgical changes and avoid unrelated
refactoring, extra configurability, silent fallbacks and swallowed exceptions.
Validate HTTP and import input at boundaries; preserve server ownership checks.

## Verification

Write focused tests for behavior changes. PHP tests use a disposable `quiz_test`
database at localhost:55439; see README. Never run them against
production. Preserve autosave race tests.
Do not use Playwright unless the user asks.

## Deployment and Production

Read `docs/deployment.md` before touching CI, Docker or server files. Every
push to `main` (including PR merges) runs tests in GitHub Actions, builds
`app` and `nginx` images to GHCR and deploys over SSH via `/srv/quiz/deploy.sh`,
which backs up the database and runs migrations. Do not run production
migrations or deploys by hand; work in feature branches and merge to `main`.

Migrations must stay compatible with live data: never drop or rewrite existing
records, and keep UUIDs, password hashes, timestamps, ordering and fractional
scores intact. There is no automatic schema or data rollback.

Production and CI use the pinned `postgres:17-bookworm` image; Alpine fails
`initdb` on the VPS seccomp. Never run `docker compose down -v` on the server,
since it deletes the production volume. Server scripts and the host Nginx
config are installed manually by the admin; Actions does not update them.

Do not commit credentials, `.env`, database snapshots or machine-specific files.
Use `.env.example` for documented configuration. Keep changes scoped and use
concise imperative commit messages; PRs include verification and visible UI
screenshots when applicable.
