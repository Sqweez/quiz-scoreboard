# Repository Guidelines

## Stack and Structure

This is a Laravel 13 + Inertia + Vue monolith. PHP application code lives in
`app/`, HTTP routes in `routes/web.php`, schema migrations in `database/migrations/`,
and Vue pages/components/stores in `resources/js/`. Shared quiz types and scoring
functions live in `shared/quiz/`. Static assets belong in `public/`.

## Commands

Use Composer with PHP 8.4 and npm (`composer.lock` and `package-lock.json` are committed).

- `composer install` and `npm ci`: install dependencies.
- `php artisan serve` and `npm run dev`: local app and Vite dev server.
- `php artisan test`: PostgreSQL-backed feature tests.
- `npm test`: client unit tests.
- `npm run typecheck` and `npm run build`: TypeScript and production assets.
- `docker compose build` and `docker compose up -d`: VPS images and services.
- `docker compose run --rm app php artisan migrate --force`: production migrations.

## Style and Scope

Use Vue single-file components with `<script setup lang="ts">`, two-space
indentation, PascalCase component names and `use` composable prefixes.
PHP follows Laravel conventions. Make surgical changes and avoid unrelated
refactoring, extra configurability, silent fallbacks and swallowed exceptions.
Validate HTTP and import input at boundaries; preserve server ownership checks.

## Verification

Write focused tests for behavior changes. PHP tests use disposable `quiz_test`
and `quiz_source` databases at localhost:55439; see README. Never run them against
production. Preserve autosave race tests and import field-level verification.
Do not use Playwright unless the user asks.

## Migration and Deployment

Read `docs/data-migration.md` before importing data. Preserve UUIDs, password
hashes, timestamps, ordering and fractional scores. Never skip incompatible
accounts or claim production migration based only on synthetic tests.
Deployment and backup commands live in `docs/deployment.md`.

Do not commit credentials, `.env`, database snapshots or machine-specific files.
Use `.env.example` for documented configuration. Keep changes scoped and use
concise imperative commit messages; PRs include verification and visible UI
screenshots when applicable.
